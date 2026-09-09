<?php

declare(strict_types=1);


final class PlatformFoundationDebtScanner
{
    public const SCHEMA_VERSION = 2;


    public static function categories(): array
    {
        return [
            'legacy_control',
            'inline_style_attribute',
            'style_block',
            'inline_event_handler',
            'hardcoded_color',
            'hardcoded_dimension',
            'html_locale_missing',
        ];
    }


    public static function scanText(
        string $text
    ): array {
        return [
            'legacy_control' =>
                self::countLegacyControls(
                    $text
                ),

            'inline_style_attribute' =>
                self::countPattern(
                    '/\sstyle\s*=/i',
                    $text
                ),

            'style_block' =>
                self::countPattern(
                    '/<style\b/i',
                    $text
                ),

            'inline_event_handler' =>
                self::countPattern(
                    '/\son[a-z][a-z0-9_-]*\s*=/i',
                    $text
                ),

            /*
             * Deliberately CSS-declaration oriented.
             *
             * href="#section" is not a hard-coded UI color.
             */
            'hardcoded_color' =>
                self::countPattern(
                    '/\b(?:'
                    . 'color'
                    . '|background'
                    . '|background-color'
                    . '|border'
                    . '|border-color'
                    . '|border-top-color'
                    . '|border-right-color'
                    . '|border-bottom-color'
                    . '|border-left-color'
                    . '|fill'
                    . '|stroke'
                    . ')\s*:\s*#[0-9a-f]{3,8}\b/i',
                    $text
                ),

            'hardcoded_dimension' =>
                self::countPattern(
                    '/\b(?:'
                    . 'width'
                    . '|height'
                    . '|min-width'
                    . '|max-width'
                    . '|min-height'
                    . '|max-height'
                    . ')\s*:\s*'
                    . '\d+(?:\.\d+)?'
                    . '(?:px|rem|em)\b/i',
                    $text
                ),

            'html_locale_missing' =>
                self::countHtmlLocaleDebt(
                    $text
                ),
        ];
    }


    private static function countPattern(
        string $pattern,
        string $text
    ): int {
        $count =
            preg_match_all(
                $pattern,
                $text
            );

        return $count !== false
            ? $count
            : 0;
    }


    private static function countLegacyControls(
        string $text
    ): int {
        $matched =
            preg_match_all(
                '/<(input|select|textarea|button)\b[^>]*>/is',
                $text,
                $tags,
                PREG_SET_ORDER
            );


        if (
            $matched === false
            || $matched === 0
        ) {
            return 0;
        }


        $debt = 0;


        foreach (
            $tags
            as $match
        ) {
            $name =
                strtolower(
                    (string) (
                        $match[1]
                        ?? ''
                    )
                );

            $tag =
                (string) (
                    $match[0]
                    ?? ''
                );


            if (
                $name === 'input'
            ) {
                $type =
                    strtolower(
                        trim(
                            self::attribute(
                                $tag,
                                'type'
                            )
                            ?? 'text'
                        )
                    );


                /*
                 * Hidden fields are not visual controls.
                 */
                if (
                    $type === 'hidden'
                ) {
                    continue;
                }


                if (
                    in_array(
                        $type,
                        [
                            'button',
                            'submit',
                            'reset',
                            'image',
                        ],
                        true
                    )
                ) {
                    if (
                        !self::hasAnyClass(
                            $tag,
                            [
                                'ui-button',
                            ]
                        )
                    ) {
                        $debt++;
                    }

                    continue;
                }


                if (
                    $type === 'checkbox'
                ) {
                    if (
                        !self::hasAnyClass(
                            $tag,
                            [
                                'ui-checkbox',
                            ]
                        )
                    ) {
                        $debt++;
                    }

                    continue;
                }


                /*
                 * A canonical radio component has not yet been defined.
                 * A new raw radio therefore remains debt until the shared
                 * component contract exists.
                 */
                if (
                    $type === 'radio'
                ) {
                    $debt++;
                    continue;
                }


                if (
                    !self::hasAnyClass(
                        $tag,
                        [
                            'ui-input',
                            'ui-control',
                        ]
                    )
                ) {
                    $debt++;
                }

                continue;
            }


            if (
                $name === 'select'
            ) {
                if (
                    !self::hasAnyClass(
                        $tag,
                        [
                            'ui-select',
                        ]
                    )
                ) {
                    $debt++;
                }

                continue;
            }


            if (
                $name === 'textarea'
            ) {
                if (
                    !self::hasAnyClass(
                        $tag,
                        [
                            'ui-textarea',
                        ]
                    )
                ) {
                    $debt++;
                }

                continue;
            }


            if (
                $name === 'button'
            ) {
                if (
                    !self::hasAnyClass(
                        $tag,
                        [
                            'ui-button',
                        ]
                    )
                ) {
                    $debt++;
                }
            }
        }


        return $debt;
    }


    private static function countHtmlLocaleDebt(
        string $text
    ): int {
        $matched =
            preg_match_all(
                '/<html\b[^>]*>/i',
                $text,
                $tags
            );


        if (
            $matched === false
            || $matched === 0
        ) {
            return 0;
        }


        $debt = 0;


        foreach (
            $tags[0]
            as $tag
        ) {
            $hasLocale =
                preg_match(
                    '/\blang\s*=\s*["\']fa-IR["\']/i',
                    $tag
                ) === 1;

            $hasDirection =
                preg_match(
                    '/\bdir\s*=\s*["\']rtl["\']/i',
                    $tag
                ) === 1;


            if (
                !$hasLocale
                || !$hasDirection
            ) {
                $debt++;
            }
        }


        return $debt;
    }


    private static function attribute(
        string $tag,
        string $name
    ): ?string {
        $pattern =
            '/\b'
            . preg_quote(
                $name,
                '/'
            )
            . '\s*=\s*(["\'])(.*?)\1/is';


        if (
            preg_match(
                $pattern,
                $tag,
                $match
            ) !== 1
        ) {
            return null;
        }


        return (string) (
            $match[2]
            ?? ''
        );
    }


    private static function hasAnyClass(
        string $tag,
        array $classes
    ): bool {
        $value =
            self::attribute(
                $tag,
                'class'
            );


        if ($value === null) {
            return false;
        }


        foreach (
            $classes
            as $class
        ) {
            if (
                preg_match(
                    '/(?:^|\s)'
                    . preg_quote(
                        $class,
                        '/'
                    )
                    . '(?:\s|$)/',
                    $value
                ) === 1
            ) {
                return true;
            }
        }


        return false;
    }
}
