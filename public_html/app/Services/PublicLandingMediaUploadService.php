<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

class PublicLandingMediaUploadService
{
    private const MAX_BYTES = 8388608;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function store(?array $upload): ?string
    {
        return $this->storeIn(
            $upload,
            '/uploads/landing'
        );
    }

    public function storeLogo(?array $upload): ?string
    {
        return $this->storeIn(
            $upload,
            '/uploads/admin/logos'
        );
    }

    private function storeIn(
        ?array $upload,
        string $publicRoot
    ): ?string {
        if (
            !is_array($upload)
            || (int) (
                $upload['error']
                ?? UPLOAD_ERR_NO_FILE
            ) === UPLOAD_ERR_NO_FILE
        ) {
            return null;
        }

        if (
            (int) ($upload['error'] ?? -1)
                !== UPLOAD_ERR_OK
            || !is_uploaded_file(
                (string) (
                    $upload['tmp_name'] ?? ''
                )
            )
        ) {
            throw new RuntimeException(
                'landing_upload_invalid'
            );
        }

        $tmp =
            (string) $upload['tmp_name'];

        $size =
            (int) ($upload['size'] ?? 0);

        if (
            $size < 1
            || $size > self::MAX_BYTES
        ) {
            throw new RuntimeException(
                'landing_upload_size_invalid'
            );
        }

        $mime = (string) (
            new \finfo(FILEINFO_MIME_TYPE)
        )->file($tmp);

        $extension =
            self::MIME_EXTENSIONS[$mime]
            ?? null;

        if ($extension === null) {
            throw new RuntimeException(
                'landing_upload_type_invalid'
            );
        }

        if (@getimagesize($tmp) === false) {
            throw new RuntimeException(
                'landing_upload_image_invalid'
            );
        }

        $relativeDirectory =
            rtrim($publicRoot, '/')
            . '/'
            . gmdate('Y/m');

        $directory =
            BASE_PATH
            . '/public'
            . $relativeDirectory;

        if (
            !is_dir($directory)
            && !mkdir(
                $directory,
                0755,
                true
            )
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'landing_upload_directory_failed'
            );
        }

        $this->protectUploadRoot(
            $publicRoot
        );

        $filename =
            bin2hex(random_bytes(20))
            . '.'
            . $extension;

        $destination =
            $directory
            . '/'
            . $filename;

        if (
            !move_uploaded_file(
                $tmp,
                $destination
            )
        ) {
            throw new RuntimeException(
                'landing_upload_store_failed'
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
    }

    private function protectUploadRoot(
        string $publicRoot
    ): void {
        $root =
            BASE_PATH
            . '/public'
            . rtrim($publicRoot, '/');

        if (!is_dir($root)) {
            mkdir(
                $root,
                0755,
                true
            );
        }

        $file =
            $root
            . '/.htaccess';

        if (is_file($file)) {
            return;
        }

        file_put_contents(
            $file,
            "Options -Indexes\n"
            . "<FilesMatch "
            . "\"\\.(php|phtml|phar|cgi|pl|py|sh)$\">\n"
            . "Require all denied\n"
            . "</FilesMatch>\n"
        );
    }
}
