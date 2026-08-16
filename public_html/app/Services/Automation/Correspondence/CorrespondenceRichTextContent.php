<?php

namespace App\Services\Automation\Correspondence;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

final class CorrespondenceRichTextContent
{
    public const PREFIX =
        '<!--IPKF-RICH-TEXT-V1-->';

    private const MAX_TEXT_LENGTH = 8000;

    private const MAX_HTML_INPUT_LENGTH = 40000;

    private const ALLOWED_TAGS = [
        'p',
        'br',
        'strong',
        'b',
        'em',
        'i',
        'u',
        's',
        'strike',
        'h2',
        'h3',
        'ul',
        'ol',
        'li',
        'blockquote',
        'a',
    ];

    private const DROP_TAGS = [
        'script',
        'style',
        'iframe',
        'object',
        'embed',
        'svg',
        'math',
        'form',
        'input',
        'button',
        'textarea',
        'select',
        'option',
        'meta',
        'link',
    ];

    private const ALIGNMENTS = [
        'right',
        'center',
        'left',
        'justify',
    ];


    public function encodeHtml(
        mixed $value
    ): string {
        $html =
            $this->sanitizeFragment(
                (string) (
                    $value
                    ?? ''
                )
            );

        if ($html === '') {
            return '';
        }

        return self::PREFIX
            . $html;
    }


    public function isRich(
        mixed $value
    ): bool {
        return str_starts_with(
            (string) (
                $value
                ?? ''
            ),
            self::PREFIX
        );
    }


    public function renderStored(
        mixed $value
    ): string {
        $stored =
            (string) (
                $value
                ?? ''
            );

        if ($this->isRich($stored)) {
            return $this->sanitizeFragment(
                substr(
                    $stored,
                    strlen(self::PREFIX)
                )
            );
        }

        return nl2br(
            htmlspecialchars(
                $stored,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            )
        );
    }


    public function editorHtml(
        mixed $value,
        string $formatHint = ''
    ): string {
        $value =
            (string) (
                $value
                ?? ''
            );

        if ($this->isRich($value)) {
            return $this->sanitizeFragment(
                substr(
                    $value,
                    strlen(self::PREFIX)
                )
            );
        }

        if (
            strtolower(
                trim($formatHint)
            ) === 'html'
        ) {
            return $this->sanitizeFragment(
                $value
            );
        }

        return nl2br(
            htmlspecialchars(
                $value,
                ENT_QUOTES
                | ENT_SUBSTITUTE,
                'UTF-8'
            )
        );
    }


    public function editorPlainText(
        mixed $value,
        string $formatHint = ''
    ): string {
        $value =
            (string) (
                $value
                ?? ''
            );

        if ($this->isRich($value)) {
            return $this->plainTextFromHtml(
                $this->sanitizeFragment(
                    substr(
                        $value,
                        strlen(self::PREFIX)
                    )
                )
            );
        }

        if (
            strtolower(
                trim($formatHint)
            ) === 'html'
        ) {
            return $this->plainTextFromHtml(
                $this->sanitizeFragment(
                    $value
                )
            );
        }

        return $value;
    }


    private function sanitizeFragment(
        string $html
    ): string {
        if ($html === '') {
            return '';
        }

        if (
            function_exists(
                'mb_substr'
            )
        ) {
            $html =
                mb_substr(
                    $html,
                    0,
                    self::MAX_HTML_INPUT_LENGTH,
                    'UTF-8'
                );
        } else {
            $html =
                substr(
                    $html,
                    0,
                    self::MAX_HTML_INPUT_LENGTH
                );
        }


        $previous =
            libxml_use_internal_errors(
                true
            );

        try {
            $document =
                new DOMDocument(
                    '1.0',
                    'UTF-8'
                );

            $loaded =
                $document->loadHTML(
                    '<?xml encoding="UTF-8">'
                    . '<!doctype html>'
                    . '<html><body>'
                    . '<div id="ipkf-rich-root">'
                    . $html
                    . '</div>'
                    . '</body></html>',
                    LIBXML_HTML_NOIMPLIED
                    | LIBXML_HTML_NODEFDTD
                    | LIBXML_NONET
                );

            if ($loaded !== true) {
                return '';
            }

            $xpath =
                new DOMXPath(
                    $document
                );

            $root =
                $xpath
                    ->query(
                        '//*[@id="ipkf-rich-root"]'
                    )
                    ?->item(0);

            if (
                !$root instanceof DOMElement
            ) {
                return '';
            }

            $this->sanitizeChildren(
                $root
            );

            $remaining =
                self::MAX_TEXT_LENGTH;

            $this->truncateText(
                $root,
                $remaining
            );

            $output = '';

            foreach (
                iterator_to_array(
                    $root->childNodes
                )
                as $child
            ) {
                $output .=
                    $document->saveHTML(
                        $child
                    );
            }

            $output =
                trim($output);

            if (
                trim(
                    preg_replace(
                        '/\s+/u',
                        '',
                        $this->plainTextFromHtml(
                            $output
                        )
                    ) ?? ''
                ) === ''
            ) {
                return '';
            }

            return $output;

        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previous
            );
        }
    }


    private function sanitizeChildren(
        DOMNode $parent
    ): void {
        $child =
            $parent->firstChild;

        while ($child !== null) {
            $next =
                $child->nextSibling;

            if (
                $child->nodeType
                === XML_TEXT_NODE
            ) {
                $child = $next;

                continue;
            }

            if (
                $child->nodeType
                !== XML_ELEMENT_NODE
            ) {
                $parent->removeChild(
                    $child
                );

                $child = $next;

                continue;
            }

            /** @var DOMElement $child */
            $tag =
                strtolower(
                    $child->tagName
                );

            if (
                in_array(
                    $tag,
                    self::DROP_TAGS,
                    true
                )
            ) {
                $parent->removeChild(
                    $child
                );

                $child = $next;

                continue;
            }

            if (
                !in_array(
                    $tag,
                    self::ALLOWED_TAGS,
                    true
                )
            ) {
                $this->sanitizeChildren(
                    $child
                );

                while (
                    $child->firstChild
                    !== null
                ) {
                    $parent->insertBefore(
                        $child->firstChild,
                        $child
                    );
                }

                $parent->removeChild(
                    $child
                );

                $child = $next;

                continue;
            }

            $this->sanitizeElement(
                $child,
                $tag
            );

            $this->sanitizeChildren(
                $child
            );

            $child = $next;
        }
    }


    private function sanitizeElement(
        DOMElement $element,
        string $tag
    ): void {
        $attributes = [];

        foreach (
            iterator_to_array(
                $element->attributes
            )
            as $attribute
        ) {
            $attributes[
                strtolower(
                    $attribute->nodeName
                )
            ] =
                $attribute->nodeValue;

            $element->removeAttribute(
                $attribute->nodeName
            );
        }


        if (
            $tag === 'a'
        ) {
            $href =
                trim(
                    (string) (
                        $attributes[
                            'href'
                        ] ?? ''
                    )
                );

            if (
                $this->safeHref(
                    $href
                )
            ) {
                $element->setAttribute(
                    'href',
                    $href
                );

                $element->setAttribute(
                    'rel',
                    'noopener noreferrer'
                );
            }
        }


        if (
            in_array(
                $tag,
                [
                    'p',
                    'h2',
                    'h3',
                    'blockquote',
                    'li',
                ],
                true
            )
        ) {
            $alignment =
                strtolower(
                    trim(
                        (string) (
                            $attributes[
                                'data-align'
                            ] ?? ''
                        )
                    )
                );

            if (
                in_array(
                    $alignment,
                    self::ALIGNMENTS,
                    true
                )
            ) {
                $element->setAttribute(
                    'data-align',
                    $alignment
                );
            }


            $indent =
                (int) (
                    $attributes[
                        'data-indent'
                    ] ?? 0
                );

            if (
                $indent >= 1
                && $indent <= 4
            ) {
                $element->setAttribute(
                    'data-indent',
                    (string) $indent
                );
            }
        }
    }


    private function safeHref(
        string $href
    ): bool {
        if ($href === '') {
            return false;
        }

        if (
            preg_match(
                '/[\x00-\x1F\x7F]/',
                $href
            ) === 1
        ) {
            return false;
        }

        return preg_match(
            '#^(?:https?://|mailto:|tel:|/|\\#)#iu',
            $href
        ) === 1;
    }


    private function truncateText(
        DOMNode $node,
        int &$remaining
    ): void {
        $child =
            $node->firstChild;

        while ($child !== null) {
            if (
                $child->nodeType
                === XML_TEXT_NODE
            ) {
                $value =
                    (string) (
                        $child->nodeValue
                        ?? ''
                    );

                $length =
                    function_exists(
                        'mb_strlen'
                    )
                        ? mb_strlen(
                            $value,
                            'UTF-8'
                        )
                        : strlen(
                            $value
                        );

                if ($remaining <= 0) {
                    $child->nodeValue =
                        '';

                } elseif (
                    $length > $remaining
                ) {
                    $child->nodeValue =
                        function_exists(
                            'mb_substr'
                        )
                            ? mb_substr(
                                $value,
                                0,
                                $remaining,
                                'UTF-8'
                            )
                            : substr(
                                $value,
                                0,
                                $remaining
                            );

                    $remaining = 0;

                } else {
                    $remaining -=
                        $length;
                }

            } else {
                $this->truncateText(
                    $child,
                    $remaining
                );
            }

            $child =
                $child->nextSibling;
        }
    }


    private function plainTextFromHtml(
        string $html
    ): string {
        $html =
            preg_replace(
                '#<br\s*/?>#iu',
                "\n",
                $html
            ) ?? $html;

        $html =
            preg_replace(
                '#</(?:p|h2|h3|li|blockquote)>#iu',
                "\n",
                $html
            ) ?? $html;

        $text =
            html_entity_decode(
                strip_tags(
                    $html
                ),
                ENT_QUOTES
                | ENT_HTML5,
                'UTF-8'
            );

        $text =
            preg_replace(
                "/[ \t]+\n/u",
                "\n",
                $text
            ) ?? $text;

        $text =
            preg_replace(
                "/\n{3,}/u",
                "\n\n",
                $text
            ) ?? $text;

        return trim(
            $text
        );
    }
}
