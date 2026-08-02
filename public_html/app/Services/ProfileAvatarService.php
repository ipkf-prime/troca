<?php

namespace App\Services;

use App\Repositories\ProfileAvatarRepository;
use Throwable;

class ProfileAvatarService extends BaseService
{
    private const MAX_BYTES = 2_097_152;
    private const MIN_DIMENSION = 64;
    private const MAX_DIMENSION = 6000;
    private const OUTPUT_DIMENSION = 512;
    private const WEBP_QUALITY = 82;

    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private ?ProfileAvatarRepository $avatars = null
    ) {
        $this->avatars ??=
            new ProfileAvatarRepository();
    }

    public function urlForUser(int $userId): string
    {
        try {
            return $this->avatars->urlForUser($userId);
        } catch (Throwable) {
            return '';
        }
    }

    public function store(
        int $userId,
        array $file
    ): array {
        if ($userId < 1) {
            return $this->error('user_not_found');
        }

        $error = (int) (
            $file['error'] ?? UPLOAD_ERR_NO_FILE
        );

        if ($error !== UPLOAD_ERR_OK) {
            return $this->error(
                $this->uploadErrorCode($error)
            );
        }

        $temporaryPath = (string) (
            $file['tmp_name'] ?? ''
        );
        $size = (int) ($file['size'] ?? 0);

        if (
            $temporaryPath === ''
            || !is_uploaded_file($temporaryPath)
        ) {
            return $this->error('invalid_upload');
        }

        if ($size < 1 || $size > self::MAX_BYTES) {
            return $this->error('avatar_too_large');
        }

        $mime = $this->mimeType($temporaryPath);
        $extension = self::MIME_EXTENSIONS[$mime]
            ?? null;

        if ($extension === null) {
            return $this->error('avatar_type_invalid');
        }

        $image = @getimagesize($temporaryPath);

        if (!is_array($image)) {
            return $this->error('avatar_image_invalid');
        }

        $width = (int) ($image[0] ?? 0);
        $height = (int) ($image[1] ?? 0);

        if (
            $width < self::MIN_DIMENSION
            || $height < self::MIN_DIMENSION
            || $width > self::MAX_DIMENSION
            || $height > self::MAX_DIMENSION
        ) {
            return $this->error('avatar_dimensions_invalid');
        }

        $directory = $this->userDirectory($userId);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0755, true)
            && !is_dir($directory)
        ) {
            return $this->error('avatar_directory_failed');
        }

        $filename = bin2hex(random_bytes(18))
            . '.webp';
        $destination = $directory . '/' . $filename;

        if (!$this->writeOptimizedAvatar(
            $temporaryPath,
            $destination,
            $mime,
            $width,
            $height
        )) {
            return $this->error('avatar_move_failed');
        }

        @chmod($directory, 0755);
        @chmod($destination, 0644);

        $url = $this->userUrlPrefix($userId)
            . '/'
            . $filename;
        $previous = $this->urlForUser($userId);

        try {
            if (!$this->avatars->updateUrl(
                $userId,
                $url
            )) {
                @unlink($destination);
                return $this->error('avatar_save_failed');
            }
        } catch (Throwable) {
            @unlink($destination);
            return $this->error('avatar_save_failed');
        }

        $this->deleteManagedFile(
            $userId,
            $previous,
            $url
        );

        return [
            'ok' => true,
            'url' => $url,
        ];
    }

    public function remove(int $userId): array
    {
        if ($userId < 1) {
            return $this->error('user_not_found');
        }

        $previous = $this->urlForUser($userId);

        try {
            $this->avatars->updateUrl(
                $userId,
                null
            );
        } catch (Throwable) {
            return $this->error('avatar_remove_failed');
        }

        $this->deleteManagedFile(
            $userId,
            $previous,
            ''
        );

        return ['ok' => true];
    }

    public function statusMessage(string $status): ?array
    {
        return match ($status) {
            'avatar_saved' => [
                'type' => 'success',
                'text' => 'تصویر پروفایل ذخیره شد.',
            ],
            'avatar_removed' => [
                'type' => 'success',
                'text' => 'تصویر پروفایل حذف شد.',
            ],
            'avatar_too_large' => [
                'type' => 'danger',
                'text' => 'حجم تصویر باید حداکثر ۲ مگابایت باشد.',
            ],
            'avatar_type_invalid' => [
                'type' => 'danger',
                'text' => 'فقط فایل JPEG، PNG یا WebP پذیرفته می‌شود.',
            ],
            'avatar_dimensions_invalid' => [
                'type' => 'danger',
                'text' => 'ابعاد تصویر معتبر نیست. حداقل ۶۴×۶۴ پیکسل لازم است.',
            ],
            'upload_missing' => [
                'type' => 'danger',
                'text' => 'فایلی برای بارگذاری انتخاب نشده است.',
            ],
            'upload_partial',
            'invalid_upload',
            'avatar_image_invalid',
            'avatar_directory_failed',
            'avatar_move_failed',
            'avatar_save_failed',
            'avatar_remove_failed' => [
                'type' => 'danger',
                'text' => 'ذخیره تصویر پروفایل انجام نشد. دوباره تلاش کنید.',
            ],
            default => null,
        };
    }

    private function mimeType(string $path): string
    {
        if (class_exists(\finfo::class)) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($path);

            if (is_string($mime)) {
                return strtolower(trim($mime));
            }
        }

        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($path);

            if (is_string($mime)) {
                return strtolower(trim($mime));
            }
        }

        return '';
    }

    private function writeOptimizedAvatar(
        string $sourcePath,
        string $destination,
        string $mime,
        int $width,
        int $height
    ): bool {
        if (!function_exists('imagewebp')) {
            return false;
        }

        $source = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => @imagecreatefromwebp($sourcePath),
            default => false,
        };

        if ($source === false) {
            return false;
        }

        if ($mime === 'image/jpeg') {
            [$source, $width, $height] = $this->orientJpeg(
                $source,
                $sourcePath,
                $width,
                $height
            );
        }

        $side = min($width, $height);
        $sourceX = (int) floor(($width - $side) / 2);
        $sourceY = (int) floor(($height - $side) / 2);
        $outputSize = min(self::OUTPUT_DIMENSION, $side);
        $output = imagecreatetruecolor($outputSize, $outputSize);

        if ($output === false) {
            imagedestroy($source);
            return false;
        }

        imagealphablending($output, false);
        imagesavealpha($output, true);
        $transparent = imagecolorallocatealpha($output, 0, 0, 0, 127);
        imagefill($output, 0, 0, $transparent);

        $resampled = imagecopyresampled(
            $output,
            $source,
            0,
            0,
            $sourceX,
            $sourceY,
            $outputSize,
            $outputSize,
            $side,
            $side
        );
        $written = $resampled
            && imagewebp($output, $destination, self::WEBP_QUALITY);

        imagedestroy($output);
        imagedestroy($source);

        return $written && is_file($destination);
    }

    private function orientJpeg(
        \GdImage $source,
        string $path,
        int $width,
        int $height
    ): array {
        if (!function_exists('exif_read_data')) {
            return [$source, $width, $height];
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return [$source, $width, $height];
        }

        $rotated = imagerotate($source, $angle, 0);

        if ($rotated === false) {
            return [$source, $width, $height];
        }

        imagedestroy($source);

        return [
            $rotated,
            imagesx($rotated),
            imagesy($rotated),
        ];
    }

    private function userDirectory(int $userId): string
    {
        return BASE_PATH
            . '/public/uploads/admin/avatars/user-'
            . $userId;
    }

    private function userUrlPrefix(int $userId): string
    {
        return '/uploads/admin/avatars/user-'
            . $userId;
    }

    private function deleteManagedFile(
        int $userId,
        string $url,
        string $exceptUrl
    ): void {
        $url = trim($url);

        if (
            $url === ''
            || $url === $exceptUrl
            || !str_starts_with(
                $url,
                $this->userUrlPrefix($userId) . '/'
            )
        ) {
            return;
        }

        $filename = basename(
            (string) parse_url($url, PHP_URL_PATH)
        );

        if (
            $filename === ''
            || $filename === '.'
            || $filename === '..'
        ) {
            return;
        }

        $path = $this->userDirectory($userId)
            . '/'
            . $filename;

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function uploadErrorCode(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_NO_FILE => 'upload_missing',
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE => 'avatar_too_large',
            UPLOAD_ERR_PARTIAL => 'upload_partial',
            default => 'invalid_upload',
        };
    }

    private function error(string $status): array
    {
        return [
            'ok' => false,
            'status' => $status,
        ];
    }
}
