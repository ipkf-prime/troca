<?php

namespace App\Services\Infrastructure;

use App\Repositories\AppSettingRepository;
use IPKF\Database\Database;
use IPKF\Support\Env;
use Throwable;

/**
 * Global file infrastructure settings.
 *
 * Precedence:
 *
 * app_settings
 * -> shared ENV
 * -> legacy ENV
 * -> automatic/default discovery
 */
final class SharedFileInfrastructureSettingsService
{
    private const NAMESPACE =
        'system.file_infrastructure';

    private const SYSTEM_USER_ID = 0;

    private const DEFAULT_SCAN_TIMEOUT_SECONDS = 45;


    public function __construct(
        private ?AppSettingRepository $settings = null
    ) {
        $this->settings ??=
            new AppSettingRepository();
    }


    public function snapshot(): array
    {
        $map =
            $this->settingMap();

        return [
            'settings_available' =>
                $this->settingsAvailable(),

            'storage_driver' =>
                'filesystem',

            'scanner_driver' =>
                'clamav_process',

            'storage_root_configured' =>
                trim(
                    (string) (
                        $map['storage_root']
                        ?? ''
                    )
                ),

            'storage_root_effective' =>
                $this->effectiveStorageRoot(),

            'storage_root_source' =>
                $this->storageRootSource(
                    $map
                ),

            'scanner_binary_configured' =>
                trim(
                    (string) (
                        $map['clamav_binary_path']
                        ?? ''
                    )
                ),

            'scanner_binary_effective' =>
                $this->effectiveScannerBinary(),

            'scanner_binary_source' =>
                $this->scannerBinarySource(
                    $map
                ),

            'scan_timeout_seconds' =>
                $this->effectiveScanTimeoutSeconds(),

            'scan_timeout_source' =>
                $this->scanTimeoutSource(
                    $map
                ),
        ];
    }


    public function effectiveStorageRoot(): ?string
    {
        $map =
            $this->settingMap();

        $configured =
            trim(
                (string) (
                    $map['storage_root']
                    ?? ''
                )
            );

        if ($configured !== '') {
            return
                $this->normalizePath(
                    $configured
                );
        }

        return
            $this->storageFallbackWithoutSetting();
    }


    public function effectiveScannerBinary(): ?string
    {
        $map =
            $this->settingMap();

        $configured =
            trim(
                (string) (
                    $map['clamav_binary_path']
                    ?? ''
                )
            );

        if ($configured !== '') {
            return $configured;
        }

        return
            $this->scannerFallbackWithoutSetting();
    }


    public function effectiveScanTimeoutSeconds(): int
    {
        $map =
            $this->settingMap();

        if (
            isset(
                $map['scan_timeout_seconds']
            )
            && is_numeric(
                $map['scan_timeout_seconds']
            )
        ) {
            return
                $this->boundedTimeout(
                    (int) $map[
                        'scan_timeout_seconds'
                    ]
                );
        }

        return
            $this->timeoutFallbackWithoutSetting();
    }


    public function save(
        array $input
    ): array {
        if (!$this->settingsAvailable()) {
            return [
                'ok' => false,

                'message' =>
                    'زیرساخت تنظیمات عمومی سامانه در دسترس نیست.',
            ];
        }

        $storageRoot =
            trim(
                (string) (
                    $input['storage_root']
                    ?? ''
                )
            );

        if ($storageRoot !== '') {
            $error =
                $this->storageRootValidationError(
                    $storageRoot
                );

            if ($error !== null) {
                return [
                    'ok' => false,
                    'message' => $error,
                ];
            }

            $storageRoot =
                (string) realpath(
                    $storageRoot
                );
        }

        $scannerBinary =
            trim(
                (string) (
                    $input['clamav_binary_path']
                    ?? ''
                )
            );

        if ($scannerBinary !== '') {
            if (
                !$this->isAbsolutePath(
                    $scannerBinary
                )
                || !is_file(
                    $scannerBinary
                )
                || !is_executable(
                    $scannerBinary
                )
            ) {
                return [
                    'ok' => false,

                    'message' =>
                        'مسیر اجرایی ClamAV معتبر یا قابل اجرا نیست.',
                ];
            }

            $scannerBinary =
                (string) realpath(
                    $scannerBinary
                );
        }

        $timeout =
            (int) (
                $input['scan_timeout_seconds']
                ?? 0
            );

        if (
            $timeout < 5
            || $timeout > 300
        ) {
            return [
                'ok' => false,

                'message' =>
                    'مهلت اسکن باید بین ۵ تا ۳۰۰ ثانیه باشد.',
            ];
        }

        $this->settings->put(
            self::NAMESPACE,
            'storage_driver',
            'filesystem',
            'string',
            false,
            self::SYSTEM_USER_ID
        );

        $this->settings->put(
            self::NAMESPACE,
            'storage_root',
            $storageRoot,
            'string',
            false,
            self::SYSTEM_USER_ID
        );

        $this->settings->put(
            self::NAMESPACE,
            'scanner_driver',
            'clamav_process',
            'string',
            false,
            self::SYSTEM_USER_ID
        );

        $this->settings->put(
            self::NAMESPACE,
            'clamav_binary_path',
            $scannerBinary,
            'string',
            false,
            self::SYSTEM_USER_ID
        );

        $this->settings->put(
            self::NAMESPACE,
            'scan_timeout_seconds',
            (string) $timeout,
            'integer',
            false,
            self::SYSTEM_USER_ID
        );

        return [
            'ok' => true,

            'message' =>
                'تنظیمات زیرساخت فایل با موفقیت ذخیره شد.',
        ];
    }


    public function testStorage(
        array $input
    ): array {
        $root =
            $this->storageCandidateFromInput(
                $input
            );

        if (
            $root === null
            || trim($root) === ''
        ) {
            return [
                'ok' => false,

                'message' =>
                    'مسیر مشترک ذخیره‌سازی هنوز تنظیم نشده است.',
            ];
        }

        $error =
            $this->storageRootValidationError(
                $root
            );

        if ($error !== null) {
            return [
                'ok' => false,
                'message' => $error,
            ];
        }

        $realRoot =
            (string) realpath(
                $root
            );

        $probe =
            $realRoot
            . DIRECTORY_SEPARATOR
            . '.ipkf-storage-probe-'
            . bin2hex(
                random_bytes(10)
            );

        $handle =
            @fopen(
                $probe,
                'x'
            );

        if ($handle === false) {
            return [
                'ok' => false,

                'message' =>
                    'نوشتن آزمایشی در فضای ذخیره‌سازی ممکن نیست.',
            ];
        }

        $written =
            fwrite(
                $handle,
                'IPKF private storage health probe'
            );

        fclose(
            $handle
        );

        if ($written === false) {
            @unlink(
                $probe
            );

            return [
                'ok' => false,

                'message' =>
                    'نوشتن آزمایشی در فضای ذخیره‌سازی کامل نشد.',
            ];
        }

        if (!@unlink($probe)) {
            return [
                'ok' => false,

                'message' =>
                    'فایل آزمایشی ایجاد شد اما حذف آن ممکن نبود.',
            ];
        }

        return [
            'ok' => true,

            'message' =>
                'دسترسی خواندن، نوشتن و حذف در فضای ذخیره‌سازی تأیید شد.',
        ];
    }


    public function testScanner(
        array $input
    ): array {
        $binary =
            $this->scannerCandidateFromInput(
                $input
            );

        if (
            $binary === null
            || !is_file(
                $binary
            )
            || !is_executable(
                $binary
            )
        ) {
            return [
                'ok' => false,

                'message' =>
                    'موتور ClamAV قابل اجرا پیدا نشد.',
            ];
        }

        $timeout =
            $this->timeoutCandidateFromInput(
                $input
            );

        /*
         * The probe itself may live in a noexec /tmp.
         * ClamAV only reads the target file; it does not execute it.
         */
        $probe =
            tempnam(
                sys_get_temp_dir(),
                'ipkf-scan-probe-'
            );

        if ($probe === false) {
            return [
                'ok' => false,

                'message' =>
                    'ایجاد فایل آزمایشی Scanner ممکن نیست.',
            ];
        }

        try {
            if (
                file_put_contents(
                    $probe,
                    "IPKF malware scanner health probe\n"
                )
                === false
            ) {
                return [
                    'ok' => false,

                    'message' =>
                        'نوشتن فایل آزمایشی Scanner ممکن نیست.',
                ];
            }

            $scanner =
                new ClamAvProcessScanner(
                    $binary,
                    $timeout,
                    $this
                );

            $result =
                $scanner->scan(
                    $probe
                );

            if (
                $result
                !== ClamAvProcessScanner::RESULT_CLEAN
            ) {
                return [
                    'ok' => false,

                    'message' =>
                        'ClamAV اجرا شد اما فایل سالم آزمایشی را clean تشخیص نداد.'
                        . ' وضعیت: '
                        . $result,
                ];
            }

            return [
                'ok' => true,

                'message' =>
                    'اجرای ClamAV و تشخیص فایل سالم با موفقیت تأیید شد.',
            ];

        } finally {
            @unlink(
                $probe
            );
        }
    }


    private function settingsAvailable(): bool
    {
        return
            Database::tableExists(
                'app_settings'
            );
    }


    private function settingMap(): array
    {
        if (!$this->settingsAvailable()) {
            return [];
        }

        try {
            $rows =
                $this->settings->list(
                    self::NAMESPACE,
                    self::SYSTEM_USER_ID
                );

            $map = [];

            foreach ($rows as $row) {
                $key =
                    trim(
                        (string) (
                            $row['setting_key']
                            ?? ''
                        )
                    );

                if ($key === '') {
                    continue;
                }

                $map[$key] =
                    (string) (
                        $row['setting_value']
                        ?? ''
                    );
            }

            return $map;

        } catch (Throwable) {
            return [];
        }
    }


    private function storageFallbackWithoutSetting(): ?string
    {
        /*
         * Only the explicit shared ENV key represents a
         * platform-wide physical storage root.
         *
         * PRIVATE_FILE_STORAGE_PATH is intentionally excluded
         * here. It is a legacy module-level setting currently
         * used by Automation and Work and must never silently
         * become the storage root of Ticketing or other modules.
         */
        $shared =
            trim(
                (string) Env::get(
                    'IPKF_PRIVATE_FILE_STORAGE_PATH',
                    ''
                )
            );

        if ($shared !== '') {
            return
                $this->normalizePath(
                    $shared
                );
        }

        return null;
    }


    private function scannerFallbackWithoutSetting(): ?string
    {
        $shared =
            trim(
                (string) Env::get(
                    'IPKF_MALWARE_CLAMSCAN_PATH',
                    ''
                )
            );

        if ($shared !== '') {
            return $shared;
        }

        /*
         * Existing Automation compatibility.
         */
        $legacy =
            trim(
                (string) Env::get(
                    'AUTOMATION_ATTACHMENT_CLAMSCAN_PATH',
                    ''
                )
            );

        if ($legacy !== '') {
            return $legacy;
        }

        return
            $this->autoDetectScannerBinary();
    }


    private function timeoutFallbackWithoutSetting(): int
    {
        $shared =
            (int) Env::get(
                'IPKF_MALWARE_SCAN_TIMEOUT_SECONDS',
                0
            );

        if ($shared > 0) {
            return
                $this->boundedTimeout(
                    $shared
                );
        }

        $legacy =
            (int) Env::get(
                'AUTOMATION_ATTACHMENT_SCAN_TIMEOUT_SECONDS',
                self::DEFAULT_SCAN_TIMEOUT_SECONDS
            );

        return
            $this->boundedTimeout(
                $legacy
            );
    }


    private function storageCandidateFromInput(
        array $input
    ): ?string {
        if (
            array_key_exists(
                'storage_root',
                $input
            )
        ) {
            $value =
                trim(
                    (string) $input[
                        'storage_root'
                    ]
                );

            if ($value !== '') {
                return
                    $this->normalizePath(
                        $value
                    );
            }

            return
                $this->storageFallbackWithoutSetting();
        }

        return
            $this->effectiveStorageRoot();
    }


    private function scannerCandidateFromInput(
        array $input
    ): ?string {
        if (
            array_key_exists(
                'clamav_binary_path',
                $input
            )
        ) {
            $value =
                trim(
                    (string) $input[
                        'clamav_binary_path'
                    ]
                );

            if ($value !== '') {
                return $value;
            }

            return
                $this->scannerFallbackWithoutSetting();
        }

        return
            $this->effectiveScannerBinary();
    }


    private function timeoutCandidateFromInput(
        array $input
    ): int {
        if (
            array_key_exists(
                'scan_timeout_seconds',
                $input
            )
        ) {
            return
                $this->boundedTimeout(
                    (int) $input[
                        'scan_timeout_seconds'
                    ]
                );
        }

        return
            $this->effectiveScanTimeoutSeconds();
    }


    private function storageRootValidationError(
        string $root
    ): ?string {
        $root =
            trim(
                $root
            );

        if (
            $root === ''
            || str_contains(
                $root,
                "\0"
            )
            || !$this->isAbsolutePath(
                $root
            )
        ) {
            return
                'مسیر ذخیره‌سازی باید یک مسیر مطلق معتبر باشد.';
        }

        $normalizedInput =
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    $root
                ),
                '/'
            );

        $publicInput =
            rtrim(
                str_replace(
                    '\\',
                    '/',
                    BASE_PATH
                    . '/public'
                ),
                '/'
            );

        if (
            $normalizedInput === $publicInput
            || str_starts_with(
                $normalizedInput,
                $publicInput
                . '/'
            )
        ) {
            return
                'فضای پیوست خصوصی نباید داخل مسیر عمومی وب قرار گیرد.';
        }

        $real =
            realpath(
                $root
            );

        if (
            $real === false
            || !is_dir(
                $real
            )
        ) {
            return
                'مسیر ذخیره‌سازی روی سرور وجود ندارد.';
        }

        $publicReal =
            realpath(
                BASE_PATH
                . '/public'
            );

        if (
            is_string(
                $publicReal
            )
            && (
                $real === $publicReal
                || str_starts_with(
                    $real,
                    rtrim(
                        $publicReal,
                        '/\\'
                    )
                    . DIRECTORY_SEPARATOR
                )
            )
        ) {
            return
                'فضای پیوست خصوصی نباید داخل Document Root قرار گیرد.';
        }

        if (
            !is_readable(
                $real
            )
            || !is_writable(
                $real
            )
        ) {
            return
                'مسیر ذخیره‌سازی برای کاربر اجرای PHP خواندنی و نوشتنی نیست.';
        }

        return null;
    }


    private function autoDetectScannerBinary(): ?string
    {
        $candidates = [];

        $path =
            (string) getenv(
                'PATH'
            );

        foreach (
            explode(
                PATH_SEPARATOR,
                $path
            )
            as $directory
        ) {
            $directory =
                trim(
                    $directory
                );

            if ($directory === '') {
                continue;
            }

            $candidates[] =
                rtrim(
                    $directory,
                    '/\\'
                )
                . DIRECTORY_SEPARATOR
                . 'clamscan';
        }

        $candidates =
            array_merge(
                $candidates,
                [
                    '/usr/bin/clamscan',
                    '/usr/local/bin/clamscan',
                    '/usr/local/sbin/clamscan',

                    /*
                     * cPanel is only a deployment fallback.
                     */
                    '/usr/local/cpanel/3rdparty/bin/clamscan',
                ]
            );

        foreach (
            array_values(
                array_unique(
                    $candidates
                )
            )
            as $candidate
        ) {
            if (
                is_file(
                    $candidate
                )
                && is_executable(
                    $candidate
                )
            ) {
                return $candidate;
            }
        }

        return null;
    }


    private function storageRootSource(
        array $map
    ): string {
        if (
            trim(
                (string) (
                    $map['storage_root']
                    ?? ''
                )
            )
            !== ''
        ) {
            return 'app_settings';
        }

        if (
            trim(
                (string) Env::get(
                    'IPKF_PRIVATE_FILE_STORAGE_PATH',
                    ''
                )
            )
            !== ''
        ) {
            return 'shared_env';
        }

        /*
         * Module-specific legacy storage is resolved by
         * SharedPrivateStorageService::legacyRoots().
         */
        return 'module_legacy_default';
    }


    private function scannerBinarySource(
        array $map
    ): string {
        if (
            trim(
                (string) (
                    $map['clamav_binary_path']
                    ?? ''
                )
            )
            !== ''
        ) {
            return 'app_settings';
        }

        if (
            trim(
                (string) Env::get(
                    'IPKF_MALWARE_CLAMSCAN_PATH',
                    ''
                )
            )
            !== ''
        ) {
            return 'shared_env';
        }

        if (
            trim(
                (string) Env::get(
                    'AUTOMATION_ATTACHMENT_CLAMSCAN_PATH',
                    ''
                )
            )
            !== ''
        ) {
            return 'legacy_automation_env';
        }

        return 'auto_detect';
    }


    private function scanTimeoutSource(
        array $map
    ): string {
        if (
            isset(
                $map['scan_timeout_seconds']
            )
            && trim(
                (string) $map[
                    'scan_timeout_seconds'
                ]
            )
            !== ''
        ) {
            return 'app_settings';
        }

        if (
            (int) Env::get(
                'IPKF_MALWARE_SCAN_TIMEOUT_SECONDS',
                0
            )
            > 0
        ) {
            return 'shared_env';
        }

        return 'legacy_automation_env';
    }


    private function boundedTimeout(
        int $timeout
    ): int {
        return
            max(
                5,
                min(
                    300,
                    $timeout > 0
                        ? $timeout
                        : self::DEFAULT_SCAN_TIMEOUT_SECONDS
                )
            );
    }


    private function normalizePath(
        string $path
    ): string {
        if ($path === '/') {
            return '/';
        }

        return
            rtrim(
                $path,
                '/\\'
            );
    }


    private function isAbsolutePath(
        string $path
    ): bool {
        return
            str_starts_with(
                $path,
                '/'
            );
    }
}
