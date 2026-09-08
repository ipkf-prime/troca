<?php

declare(strict_types=1);

namespace App\Services\Ticketing;

use App\Services\Infrastructure\ClamAvProcessScanner;
use RuntimeException;
use Throwable;


/**
 * TICKETING_PORTAL_MEDIA_UPLOAD_V1
 *
 * HTTP upload
 *   -> private quarantine
 *   -> MIME + image validation
 *   -> ClamAV fail-closed scan
 *   -> public Portal media namespace
 *
 * Accepted:
 *   JPEG / PNG / WEBP
 */
final class PortalMediaUploadService
{
    private const MAX_BYTES =
        8 * 1024 * 1024;


    private const MIME_EXTENSIONS = [
        'image/jpeg' =>
            'jpg',

        'image/png' =>
            'png',

        'image/webp' =>
            'webp',
    ];


    public function __construct(
        private ?ClamAvProcessScanner $scanner = null
    ) {
        $this->scanner ??=
            new ClamAvProcessScanner();
    }


    public function store(
        ?array $upload,
        string $portalReference,
        string $kind = 'media'
    ): ?string {

        if (
            !is_array($upload)
            ||
            (int) (
                $upload['error']
                ?? UPLOAD_ERR_NO_FILE
            ) === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }


        if (
            (int) (
                $upload['error']
                ?? -1
            ) !== UPLOAD_ERR_OK
        ) {
            throw new RuntimeException(
                'portal_media_upload_failed'
            );
        }


        $temporary =
            trim(
                (string) (
                    $upload['tmp_name']
                    ?? ''
                )
            );


        if (
            $temporary === ''
            || !is_uploaded_file(
                $temporary
            )
        ) {
            throw new RuntimeException(
                'portal_media_upload_invalid'
            );
        }


        $size =
            filesize(
                $temporary
            );


        if (
            $size === false
            || (int) $size < 1
            || (int) $size > self::MAX_BYTES
        ) {
            throw new RuntimeException(
                'portal_media_upload_size_invalid'
            );
        }


        $quarantineRoot =
            BASE_PATH
            . '/storage/quarantine/ticketing-portal';


        if (
            !is_dir(
                $quarantineRoot
            )
            &&
            !mkdir(
                $quarantineRoot,
                0700,
                true
            )
            &&
            !is_dir(
                $quarantineRoot
            )
        ) {
            throw new RuntimeException(
                'portal_media_quarantine_unavailable'
            );
        }


        @chmod(
            $quarantineRoot,
            0700
        );


        $quarantine =
            $quarantineRoot
            . '/'
            . bin2hex(
                random_bytes(24)
            )
            . '.upload';


        if (
            !move_uploaded_file(
                $temporary,
                $quarantine
            )
        ) {
            throw new RuntimeException(
                'portal_media_quarantine_store_failed'
            );
        }


        @chmod(
            $quarantine,
            0600
        );


        try {

            $mime =
                (string) (
                    new \finfo(
                        FILEINFO_MIME_TYPE
                    )
                )->file(
                    $quarantine
                );


            $extension =
                self::MIME_EXTENSIONS[
                    $mime
                ]
                ?? null;


            if ($extension === null) {
                throw new RuntimeException(
                    'portal_media_type_invalid'
                );
            }


            if (
                @getimagesize(
                    $quarantine
                ) === false
            ) {
                throw new RuntimeException(
                    'portal_media_image_invalid'
                );
            }


            $scan =
                $this->scanner
                    ->scan(
                        $quarantine
                    );


            if (
                $scan
                === ClamAvProcessScanner::RESULT_INFECTED
            ) {
                throw new RuntimeException(
                    'portal_media_infected'
                );
            }


            if (
                $scan
                !== ClamAvProcessScanner::RESULT_CLEAN
            ) {
                throw new RuntimeException(
                    'portal_media_scan_failed'
                );
            }


            $portalSegment =
                $this->safeSegment(
                    $portalReference,
                    'portal'
                );


            $kindSegment =
                $this->safeSegment(
                    $kind,
                    'media'
                );


            $relativeDirectory =
                '/uploads/ticketing/portal/'
                . $portalSegment
                . '/'
                . $kindSegment
                . '/'
                . gmdate('Y/m');


            $publicDirectory =
                BASE_PATH
                . '/public'
                . $relativeDirectory;


            if (
                !is_dir(
                    $publicDirectory
                )
                &&
                !mkdir(
                    $publicDirectory,
                    0755,
                    true
                )
                &&
                !is_dir(
                    $publicDirectory
                )
            ) {
                throw new RuntimeException(
                    'portal_media_public_directory_failed'
                );
            }


            $this->protectPublicRoot();


            $filename =
                bin2hex(
                    random_bytes(24)
                )
                . '.'
                . $extension;


            $destination =
                $publicDirectory
                . '/'
                . $filename;


            if (
                !@rename(
                    $quarantine,
                    $destination
                )
            ) {

                if (
                    !@copy(
                        $quarantine,
                        $destination
                    )
                ) {
                    throw new RuntimeException(
                        'portal_media_promote_failed'
                    );
                }


                @unlink(
                    $quarantine
                );
            }


            @chmod(
                $destination,
                0644
            );


            return
                $relativeDirectory
                . '/'
                . $filename;


        } catch (Throwable $exception) {

            @unlink(
                $quarantine
            );


            throw $exception;
        }
    }


    public function remove(
        ?string $publicUrl
    ): void {

        $publicUrl =
            trim(
                (string) $publicUrl
            );


        if (
            $publicUrl === ''
            ||
            !str_starts_with(
                $publicUrl,
                '/uploads/ticketing/portal/'
            )
        ) {
            return;
        }


        $relative =
            ltrim(
                $publicUrl,
                '/'
            );


        if (
            str_contains(
                $relative,
                '..'
            )
        ) {
            return;
        }


        $path =
            BASE_PATH
            . '/public/'
            . $relative;


        if (is_file($path)) {
            @unlink($path);
        }
    }


    private function safeSegment(
        string $value,
        string $fallback
    ): string {

        $value =
            strtolower(
                trim($value)
            );


        $value =
            preg_replace(
                '/[^a-z0-9_-]+/',
                '-',
                $value
            )
            ?? '';


        $value =
            trim(
                $value,
                '-_'
            );


        return
            $value !== ''
                ? substr(
                    $value,
                    0,
                    80
                )
                : $fallback;
    }


    private function protectPublicRoot(): void
    {
        $root =
            BASE_PATH
            . '/public/uploads/ticketing/portal';


        if (
            !is_dir(
                $root
            )
            &&
            !mkdir(
                $root,
                0755,
                true
            )
            &&
            !is_dir(
                $root
            )
        ) {
            throw new RuntimeException(
                'portal_media_public_root_failed'
            );
        }


        $guard =
            $root
            . '/.htaccess';


        if (is_file($guard)) {
            return;
        }


        $content =
            "Options -Indexes\n"
            . "<FilesMatch "
            . "\"\\.(php|phtml|phar|cgi|pl|py|sh|htm|html|svg)$\">\n"
            . "Require all denied\n"
            . "</FilesMatch>\n";


        if (
            file_put_contents(
                $guard,
                $content
            ) === false
        ) {
            throw new RuntimeException(
                'portal_media_public_guard_failed'
            );
        }
    }
}
