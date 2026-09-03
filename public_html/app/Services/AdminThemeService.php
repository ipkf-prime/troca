<?php

namespace App\Services;

use App\Repositories\AppSettingRepository;
use IPKF\Database\Database;
use IPKF\Support\Env;

class AdminThemeService extends BaseService
{
    private const NAMESPACE = 'admin.theme';
    private const SYSTEM_USER_ID = 0;
    private const DEFAULT_LOGO_URL = '/assets/admin/images/logos/default-logo.svg';
    private const DEFAULT_AVATAR_URL = '/assets/admin/images/avatars/default-avatar.svg';
    private const DEFAULT_BRAND_NAME = 'سامانه هوشمند تروکا';
    private const DEFAULT_BRAND_SUBTITLE = 'سامانه یکپارچه خدمات سازمانی';
    private const DEFAULT_FOOTER_TEXT = 'کلیه حقوق این وب‌سایت متعلق به سامانه هوشمند تروکا می‌باشد.';
    private const DEFAULT_PRESET = 'official_emerald';
    public const RUNTIME_FIX_VERSION = 'theme-runtime-forensics-v1';

    private const PRESET_ALIASES = [
        'cooperative_official' => 'official_emerald',
        'cooperative_light' => 'modern_light',
        'cooperative_classic' => 'classic_green',
        'golden_green' => 'green_gold',
        'official_emerald' => 'official_emerald',
        'modern_light' => 'modern_light',
        'classic_green' => 'classic_green',
        'neutral_light' => 'neutral_light',
        'green_gold' => 'green_gold',
    ];

    private const PERSONAL_KEYS = [
        'active_preset',
    ];

    public function __construct(protected ?AppSettingRepository $settings = null)
    {
        $this->settings ??= new AppSettingRepository();
    }

    public function presets(): array
    {
        $fontStack = $this->envFontStack();

        return [
            'official_emerald' => [
                'title' => 'زمردی رسمی',
                'description' => 'سایدبار سبز عمیق، تاکید طلایی و سطح روشن برای فضای مدیریتی رسمی.',
                'tokens' => $this->baseTokens($fontStack, [
                    'primary' => '#0f7a3f',
                    'primary_hover' => '#0b6533',
                    'primary_dark' => '#07582f',
                    'primary_soft' => '#e8f5ee',
                    'accent' => '#ffd33d',
                    'accent_hover' => '#f2c120',
                    'bg' => '#f4f7f8',
                    'bg_gradient_start' => '#f7faf8',
                    'bg_gradient_end' => '#eef5f0',
                    'surface' => '#ffffff',
                    'surface_muted' => '#f7faf8',
                    'text' => '#1f2933',
                    'text_muted' => '#64748b',
                    'border' => '#dfe8e3',
                    'sidebar_bg' => '#07582f',
                    'sidebar_bg_2' => '#0b6b3a',
                    'sidebar_text' => '#ffffff',
                    'sidebar_text_muted' => '#d7f5e2',
                    'sidebar_active_bg' => '#ffd33d',
                    'sidebar_active_text' => '#123524',
                    'header_bg' => '#ffffff',
                    'footer_bg' => '#f7faf8',
                    'footer_text' => '#64748b',
                    'radius' => '18px',
                    'shadow' => '0 10px 30px rgba(15, 80, 43, 0.08)',
                ]),
            ],
            'modern_light' => [
                'title' => 'روشن مدرن',
                'description' => 'ظاهر سفید و سبک با تاکیدهای سبز ملایم برای کار روزمره و خوانایی بالا.',
                'tokens' => $this->baseTokens($fontStack, [
                    'primary' => '#2f8f5b',
                    'primary_hover' => '#247449',
                    'primary_dark' => '#1f6f4a',
                    'primary_soft' => '#e8f5ee',
                    'accent' => '#f2c94c',
                    'accent_hover' => '#dfb43b',
                    'bg' => '#f6faf7',
                    'bg_gradient_start' => '#f9fbfa',
                    'bg_gradient_end' => '#edf7f1',
                    'surface' => '#ffffff',
                    'surface_muted' => '#f1f7f3',
                    'text' => '#1f2933',
                    'text_muted' => '#64748b',
                    'border' => '#d8e7dd',
                    'sidebar_bg' => '#ffffff',
                    'sidebar_bg_2' => '#f1f7f3',
                    'sidebar_text' => '#1f2933',
                    'sidebar_text_muted' => '#64748b',
                    'sidebar_active_bg' => '#e8f5ee',
                    'sidebar_active_text' => '#247449',
                    'header_bg' => '#ffffff',
                    'footer_bg' => '#f6faf7',
                    'footer_text' => '#64748b',
                    'radius' => '16px',
                    'shadow' => '0 12px 32px rgba(31, 41, 51, 0.08)',
                ]),
            ],
            'classic_green' => [
                'title' => 'سبز کلاسیک',
                'description' => 'سبز سنتی‌تر با حس سازمانی، مناسب چیدمان‌های رسمی و کمتر مینیمال.',
                'tokens' => $this->baseTokens($fontStack, [
                    'primary' => '#1f6f4a',
                    'primary_hover' => '#18583b',
                    'primary_dark' => '#12442d',
                    'primary_soft' => '#e4f0e8',
                    'accent' => '#d6aa42',
                    'accent_hover' => '#bf9535',
                    'bg' => '#f4f8f5',
                    'bg_gradient_start' => '#f4f8f5',
                    'bg_gradient_end' => '#dfeee5',
                    'surface' => '#ffffff',
                    'surface_muted' => '#edf5f0',
                    'text' => '#16241d',
                    'text_muted' => '#5f7169',
                    'border' => '#cadfd2',
                    'sidebar_bg' => '#12442d',
                    'sidebar_bg_2' => '#1f6f4a',
                    'sidebar_text' => '#ffffff',
                    'sidebar_text_muted' => '#cbe7d5',
                    'sidebar_active_bg' => '#d6aa42',
                    'sidebar_active_text' => '#16241d',
                    'header_bg' => '#ffffff',
                    'footer_bg' => '#edf5f0',
                    'footer_text' => '#5f7169',
                    'radius' => '14px',
                    'shadow' => '0 12px 28px rgba(15, 63, 49, 0.09)',
                ]),
            ],
            'neutral_light' => [
                'title' => 'روشن خنثی',
                'description' => 'ظاهر اداری خاکستری و سفید با سبز کنترل‌شده.',
                'tokens' => $this->baseTokens($fontStack, [
                    'primary' => '#3f7f6b',
                    'primary_hover' => '#326657',
                    'primary_dark' => '#294f45',
                    'primary_soft' => '#eef5f2',
                    'accent' => '#e5b93f',
                    'accent_hover' => '#d1a82f',
                    'bg' => '#f7f9fb',
                    'bg_gradient_start' => '#f9fafb',
                    'bg_gradient_end' => '#eef2f7',
                    'surface' => '#ffffff',
                    'surface_muted' => '#f1f5f9',
                    'text' => '#1f2937',
                    'text_muted' => '#64748b',
                    'border' => '#dbe3ea',
                    'sidebar_bg' => '#ffffff',
                    'sidebar_bg_2' => '#f1f5f9',
                    'sidebar_text' => '#1f2937',
                    'sidebar_text_muted' => '#64748b',
                    'sidebar_active_bg' => '#eef5f2',
                    'sidebar_active_text' => '#326657',
                    'header_bg' => '#ffffff',
                    'footer_bg' => '#f1f5f9',
                    'footer_text' => '#64748b',
                    'radius' => '16px',
                    'shadow' => '0 12px 32px rgba(15, 23, 42, 0.08)',
                ]),
            ],
            'green_gold' => [
                'title' => 'سبز طلایی',
                'description' => 'سبز تیره با تاکید طلایی پررنگ‌تر، نزدیک‌تر به هویت تبلیغاتی تروکا.',
                'tokens' => $this->baseTokens($fontStack, [
                    'primary' => '#0d6b3b',
                    'primary_hover' => '#09542f',
                    'primary_dark' => '#063f25',
                    'primary_soft' => '#e9f4ee',
                    'accent' => '#f4b740',
                    'accent_hover' => '#e19f22',
                    'bg' => '#f7f4ec',
                    'bg_gradient_start' => '#fbf8ef',
                    'bg_gradient_end' => '#edf5ef',
                    'surface' => '#ffffff',
                    'surface_muted' => '#fff8e7',
                    'text' => '#18251f',
                    'text_muted' => '#6a716b',
                    'border' => '#e7dec8',
                    'sidebar_bg' => '#063f25',
                    'sidebar_bg_2' => '#0d6b3b',
                    'sidebar_text' => '#ffffff',
                    'sidebar_text_muted' => '#e5f4eb',
                    'sidebar_active_bg' => '#f4b740',
                    'sidebar_active_text' => '#16241d',
                    'header_bg' => '#ffffff',
                    'footer_bg' => '#fff8e7',
                    'footer_text' => '#6a716b',
                    'radius' => '20px',
                    'shadow' => '0 14px 34px rgba(76, 49, 8, 0.10)',
                ]),
            ],
        ];
    }

    public function fontOptions(): array
    {
        return [
            'vazirmatn' => '"Vazirmatn", "IRANSans", "Tahoma", "Segoe UI", sans-serif',
            'tahoma' => '"Tahoma", "Segoe UI", sans-serif',
            'segoe_ui' => '"Segoe UI", "Tahoma", sans-serif',
            'system_ui' => 'system-ui, "Segoe UI", "Tahoma", sans-serif',
        ];
    }

    public function logoOptions(): array
    {
        return $this->assetOptions('/assets/admin/images/logos/', BASE_PATH . '/public/assets/admin/images/logos');
    }

    public function avatarOptions(): array
    {
        return $this->assetOptions('/assets/admin/images/avatars/', BASE_PATH . '/public/assets/admin/images/avatars');
    }

    public function theme(?int $userId = null): array
    {
        $system = $this->settingsAvailable() ? $this->settingMap(self::SYSTEM_USER_ID) : [];
        $personal = $userId !== null && $userId > self::SYSTEM_USER_ID && $this->scopeSupport()
            ? $this->personalSettingMap($userId)
            : [];
        $settings = array_replace($system, $personal);
        $preset = $this->validPreset((string) ($settings['active_preset'] ?? $this->envDefaultPreset()));
        $tokens = $this->presets()[$preset]['tokens'];

        return [
            'active_preset' => $preset,
            'canonical_preset' => $preset,
            'preset_title' => $this->presets()[$preset]['title'],
            'brand_name' => $this->textSetting($system, 'brand_name', (string) Env::get('ADMIN_BRAND_NAME', self::DEFAULT_BRAND_NAME), self::DEFAULT_BRAND_NAME),
            'brand_subtitle' => $this->textSetting($system, 'brand_subtitle', self::DEFAULT_BRAND_SUBTITLE, self::DEFAULT_BRAND_SUBTITLE, 120),
            'logo_url' => $this->cleanAssetUrl((string) ($system['logo_url'] ?? Env::get('ADMIN_LOGO_URL', self::DEFAULT_LOGO_URL)), self::DEFAULT_LOGO_URL),
            'default_avatar_url' => $this->cleanAssetUrl((string) ($system['default_avatar_url'] ?? Env::get('ADMIN_DEFAULT_AVATAR_URL', self::DEFAULT_AVATAR_URL)), self::DEFAULT_AVATAR_URL),
            'footer_text' => $this->textSetting($system, 'footer_text', self::DEFAULT_FOOTER_TEXT, self::DEFAULT_FOOTER_TEXT, 140),
            'footer_enabled' => (string) ($system['footer_enabled'] ?? '1') !== '0',
            'show_user_name' => (string) ($system['show_user_name'] ?? '1') !== '0',
            'show_active_role' => (string) ($system['show_active_role'] ?? '1') !== '0',
            'tokens' => $tokens,
            'has_personal_override' => $personal !== [],
        ];
    }

    public function systemTheme(): array
    {
        return $this->theme(null);
    }

    public function personalTheme(int $userId): array
    {
        return $this->theme($userId);
    }

    public function cssVariables(?int $userId = null): string
    {
        $tokens = $this->theme($userId)['tokens'];
        $lines = [':root, body[data-admin-theme] {'];

        foreach ($tokens as $key => $value) {
            $lines[] = '  --admin-' . str_replace('_', '-', $key) . ': ' . $value . ';';
        }

        if (isset($tokens['text_muted'])) {
            $lines[] = '  --admin-muted: ' . $tokens['text_muted'] . ';';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    public function saveSystemIdentity(array $input): array
    {
        if (!$this->settingsAvailable()) {
            return [
                'ok' => false,
                'errors' => ['settings_unavailable'],
            ];
        }

        $current =
            $this->settingMap(self::SYSTEM_USER_ID);

        $brandName = $this->cleanBrand(
            (string) (
                $input['brand_name']
                ?? $current['brand_name']
                ?? self::DEFAULT_BRAND_NAME
            )
        );

        $brandSubtitle = $this->cleanBrand(
            (string) (
                $input['brand_subtitle']
                ?? $current['brand_subtitle']
                ?? self::DEFAULT_BRAND_SUBTITLE
            ),
            120
        );

        $footerText = $this->cleanBrand(
            (string) (
                $input['footer_text']
                ?? $current['footer_text']
                ?? self::DEFAULT_FOOTER_TEXT
            ),
            140
        );

        if ($brandName === '') {
            return [
                'ok' => false,
                'errors' => ['invalid_brand_name'],
            ];
        }

        $logoUrl = $this->cleanAssetUrl(
            (string) (
                $input['logo_url']
                ?? $current['logo_url']
                ?? self::DEFAULT_LOGO_URL
            ),
            self::DEFAULT_LOGO_URL
        );

        $footerEnabled =
            isset($input['footer_enabled'])
                ? '1'
                : '0';

        $settings = [
            'brand_name' => [
                $brandName,
                'string',
            ],
            'brand_subtitle' => [
                $brandSubtitle,
                'string',
            ],
            'logo_url' => [
                $logoUrl,
                'string',
            ],
            'footer_text' => [
                $footerText,
                'string',
            ],
            'footer_enabled' => [
                $footerEnabled,
                'bool',
            ],
        ];

        foreach (
            $settings
            as $key => [$value, $type]
        ) {
            $this->settings->put(
                self::NAMESPACE,
                $key,
                $value,
                $type,
                true,
                self::SYSTEM_USER_ID
            );
        }

        return [
            'ok' => true,
            'errors' => [],
        ];
    }

    public function update(array $input): array
    {
        return $this->saveSystemTheme($input);
    }

    public function updateSystem(array $input): array
    {
        return $this->saveSystemTheme($input);
    }

    public function saveSystemTheme(array $input): array
    {
        if (!$this->settingsAvailable()) {
            return [
                'ok' => false,
                'errors' => ['settings_unavailable'],
            ];
        }

        $currentSystem =
            $this->settingMap(self::SYSTEM_USER_ID);

        $currentPreset =
            $this->validPreset(
                (string) (
                    $currentSystem['active_preset']
                    ?? $this->envDefaultPreset()
                )
            );

        $requestedPreset =
            $this->validPreset(
                (string) (
                    $input['active_preset']
                    ?? $currentPreset
                )
            );

        $presetChanged =
            $requestedPreset !== $currentPreset;

        $result =
            $this->sanitizeSystemInput(
                $input,
                $presetChanged
            );

        if ($result['errors'] !== []) {
            return [
                'ok' => false,
                'errors' => $result['errors'],
            ];
        }

        /*
         * Identity belongs to Pages Management,
         * not to the default visual theme.
         */
        foreach ([
            'brand_name',
            'brand_subtitle',
            'logo_url',
            'footer_text',
            'footer_enabled',
        ] as $identityKey) {
            unset(
                $result['settings'][$identityKey]
            );
        }

        $this->deleteTokenOverrides(
            self::SYSTEM_USER_ID
        );

        foreach (
            $result['settings']
            as $key => [$value, $type]
        ) {
            $this->settings->put(
                self::NAMESPACE,
                $key,
                $value,
                $type,
                true,
                self::SYSTEM_USER_ID
            );
        }

        return [
            'ok' => true,
            'errors' => [],
        ];
    }

    public function updatePersonal(int $userId, array $input): array
    {
        return $this->savePersonalTheme($userId, $input);
    }

    public function savePersonalTheme(int $userId, array $input): array
    {
        if ($userId <= self::SYSTEM_USER_ID || !$this->scopeSupport()) {
            return ['ok' => false, 'errors' => ['settings_unavailable']];
        }

        $settings = [];
        $preset = $this->validPreset((string) ($input['active_preset'] ?? $this->envDefaultPreset()));
        $settings['active_preset'] = [$preset, 'string'];

        $this->deleteTokenOverrides($userId);

        foreach ($settings as $key => [$value, $type]) {
            $this->settings->put(self::NAMESPACE, $key, $value, $type, true, $userId);
        }

        return ['ok' => true, 'errors' => []];
    }

    public function resetSystem(): void
    {
        $this->resetSystemTheme();
    }

    public function resetSystemTheme(): void
    {
        if (!$this->settingsAvailable()) {
            return;
        }

        $this->deleteTokenOverrides(
            self::SYSTEM_USER_ID
        );

        if ($this->scopeSupport()) {
            $this->settings->delete(
                self::NAMESPACE,
                'active_preset',
                self::SYSTEM_USER_ID
            );
        }

        $this->settings->put(
            self::NAMESPACE,
            'active_preset',
            self::DEFAULT_PRESET,
            'string',
            true,
            self::SYSTEM_USER_ID
        );
    }

    public function resetUser(int $userId): void
    {
        $this->resetPersonalTheme($userId);
    }

    public function resetPersonalTheme(int $userId): void
    {
        if ($userId > self::SYSTEM_USER_ID && $this->scopeSupport()) {
            $this->settings->deleteNamespace(self::NAMESPACE, $userId);
        }
    }

    public function seedDefaults(bool $force = false): void
    {
        if (!$this->settingsAvailable()) {
            return;
        }

        $defaults = [
            'active_preset' => self::DEFAULT_PRESET,
            'brand_name' => $this->looksCorrupted((string) Env::get('ADMIN_BRAND_NAME', self::DEFAULT_BRAND_NAME))
                ? self::DEFAULT_BRAND_NAME
                : (string) Env::get('ADMIN_BRAND_NAME', self::DEFAULT_BRAND_NAME),
            'logo_url' => self::DEFAULT_LOGO_URL,
            'default_avatar_url' => self::DEFAULT_AVATAR_URL,
            'footer_text' => self::DEFAULT_FOOTER_TEXT,
            'footer_enabled' => '1',
            'show_user_name' => '1',
            'show_active_role' => '1',
        ];

        foreach ($defaults as $key => $value) {
            $current = $this->settings->get(self::NAMESPACE, $key, self::SYSTEM_USER_ID);
            $stored = (string) ($current['setting_value'] ?? '');

            if ($force || $current === null || $stored === '' || $this->looksCorrupted($stored)) {
                $this->settings->put(self::NAMESPACE, $key, (string) $value, 'string', true, self::SYSTEM_USER_ID);
            }
        }
    }

    public function persianDefaultsOk(): bool
    {
        $theme = $this->systemTheme();
        $values = [
            $theme['brand_name'],
            $theme['preset_title'],
            $theme['footer_text'],
            'پنل مدیریت',
            'پوسته پنل',
        ];

        foreach ($values as $value) {
            if ($this->looksCorrupted((string) $value)) {
                return false;
            }
        }

        return true;
    }

    public function settingsAvailable(): bool
    {
        return Database::tableExists('app_settings');
    }

    public function scopeSupport(): bool
    {
        return $this->settingsAvailable() && $this->settings->scoped();
    }

    public function assetsCanonical(): bool
    {
        return is_readable(BASE_PATH . '/public/assets/admin/css/admin.css')
            && is_readable(BASE_PATH . '/public/assets/admin/js/admin.js')
            && is_dir(BASE_PATH . '/public/assets/admin/webfonts')
            && is_dir(BASE_PATH . '/public/assets/admin/images/icons')
            && is_readable(BASE_PATH . '/public/assets/admin/images/logos/default-logo.svg')
            && is_readable(BASE_PATH . '/public/assets/admin/images/avatars/default-avatar.svg');
    }

    public function localIconsAvailable(): bool
    {
        return is_readable(BASE_PATH . '/public/assets/admin/css/icons.css')
            || count(glob(BASE_PATH . '/public/assets/admin/webfonts/fa-*') ?: []) > 0;
    }

    public function webfontsPathAvailable(): bool
    {
        return is_dir(BASE_PATH . '/public/assets/admin/webfonts');
    }

    public function currentThemeResolverAvailable(): bool
    {
        return $this->settingsAvailable()
            && isset($this->presets()[self::DEFAULT_PRESET])
            && method_exists($this, 'theme')
            && method_exists($this, 'personalTheme')
            && method_exists($this, 'systemTheme');
    }

    public function themeUserScopeSupported(): bool
    {
        return $this->scopeSupport();
    }

    public function resolvedPresetSource(?int $userId): string
    {
        if ($userId !== null && $userId > self::SYSTEM_USER_ID && $this->scopeSupport()) {
            $personal = $this->personalSettingMap($userId);

            if (isset($personal['active_preset']) && $personal['active_preset'] !== '') {
                return 'personal';
            }
        }

        $system = $this->settingsAvailable() ? $this->settingMap(self::SYSTEM_USER_ID) : [];

        return isset($system['active_preset']) && $system['active_preset'] !== '' ? 'system' : 'default';
    }

    public function systemPresetExists(): bool
    {
        $system = $this->settingsAvailable() ? $this->settingMap(self::SYSTEM_USER_ID) : [];

        return isset($system['active_preset']) && $system['active_preset'] !== '';
    }

    public function personalPresetExists(?int $userId): bool
    {
        if ($userId === null || $userId <= self::SYSTEM_USER_ID || !$this->scopeSupport()) {
            return false;
        }

        $personal = $this->personalSettingMap($userId);

        return isset($personal['active_preset']) && $personal['active_preset'] !== '';
    }

    public function assetUrls(): array
    {
        return [
            'admin_css' => '/assets/admin/css/admin.css?v=' . $this->assetVersion('/public/assets/admin/css/admin.css'),
            'icons_css' => '/assets/admin/css/icons.css?v=' . $this->assetVersion('/public/assets/admin/css/icons.css'),
            'admin_js' => '/assets/admin/js/admin.js?v=' . $this->assetVersion('/public/assets/admin/js/admin.js'),
        ];
    }

    public function visualTokens(?int $userId = null): array
    {
        $tokens = $this->theme($userId)['tokens'];
        $keys = [
            'primary', 'primary_dark', 'accent', 'bg', 'surface', 'text',
            'text_muted', 'sidebar_bg', 'sidebar_text', 'sidebar_active_bg',
            'sidebar_active_text', 'header_bg', 'border', 'shadow', 'radius',
        ];
        $visual = [];

        foreach ($keys as $key) {
            $visual[$key] = $tokens[$key] ?? null;
        }

        $visual['muted'] = $tokens['text_muted'] ?? null;

        return $visual;
    }

    public function forensics(?int $userId, array $context = []): array
    {
        $theme = $this->theme($userId);

        return [
            'runtime' => [
                'route' => $_SERVER['REQUEST_URI'] ?? '',
                'user_id' => $userId,
                'username' => $context['user']['username'] ?? null,
                'email' => $context['user']['email'] ?? null,
                'mobile' => $context['user']['mobile'] ?? null,
                'active_role' => $context['active_assignment']['role_code'] ?? null,
                'app_env' => Env::get('APP_ENV', 'production'),
                'app_debug' => Env::isDebug(),
                'resolver_class' => self::class,
                'runtime_fix_version' => self::RUNTIME_FIX_VERSION,
            ],
            'system_rows' => $this->safeRows(self::SYSTEM_USER_ID),
            'personal_rows' => $userId !== null ? $this->safeRows($userId) : [],
            'other_user_theme_row_count' => $userId !== null ? $this->settings->otherScopedUserCount(self::NAMESPACE, $userId) : 0,
            'token_override_rows_count' => $this->tokenOverrideRowsCount(self::SYSTEM_USER_ID),
            'personal_token_override_rows_count' => $userId !== null ? $this->tokenOverrideRowsCount($userId) : 0,
            'token_override_rows_ignored' => true,
            'resolved_theme' => [
                'resolved_source' => $this->resolvedPresetSource($userId),
                'active_preset' => $theme['active_preset'],
                'canonical_preset' => $theme['canonical_preset'],
                'preset_title' => $theme['preset_title'],
                'has_personal_override' => $theme['has_personal_override'],
                'has_system_theme' => $this->systemPresetExists(),
            ],
            'visual_tokens' => $this->visualTokens($userId),
            'css_variables' => $this->cssVariables($userId),
            'assets' => $this->assetUrls(),
        ];
    }

    private function sanitizeSystemInput(array $input, bool $presetChanged = false): array
    {
        $errors = [];
        $settings = [];
        $currentSystem = $this->settingMap(self::SYSTEM_USER_ID);
        $rawPreset = trim((string) ($input['active_preset'] ?? $this->envDefaultPreset()));
        $preset = $this->validPreset($rawPreset);

        if (!$this->knownPresetInput($rawPreset)) {
            $errors[] = 'invalid_preset';
            $preset = self::DEFAULT_PRESET;
        }

        $brandName = $this->cleanBrand((string) ($input['brand_name'] ?? ''));
        $footerText = $this->cleanBrand((string) ($input['footer_text'] ?? self::DEFAULT_FOOTER_TEXT), 140);
        $logoInput = trim((string) ($input['logo_url_manual'] ?? '')) !== ''
            ? (string) $input['logo_url_manual']
            : (string) ($input['logo_url'] ?? '');
        $avatarInput = trim((string) ($input['default_avatar_url_manual'] ?? '')) !== ''
            ? (string) $input['default_avatar_url_manual']
            : (string) ($input['default_avatar_url'] ?? '');
        $logoUrl = $this->cleanAssetUrl($logoInput, '');
        $defaultAvatarUrl = $this->cleanAssetUrl($avatarInput, '');

        if ($brandName === '') {
            $errors[] = 'invalid_brand_name';
        }

        if ($logoInput !== '' && $logoUrl === '') {
            $errors[] = 'invalid_logo_url';
        }

        if ($avatarInput !== '' && $defaultAvatarUrl === '') {
            $errors[] = 'invalid_default_avatar_url';
        }

        $settings['active_preset'] = [$preset, 'string'];
        $settings['brand_name'] = [$brandName, 'string'];
        $settings['logo_url'] = [$logoUrl !== '' ? $logoUrl : self::DEFAULT_LOGO_URL, 'string'];
        $settings['default_avatar_url'] = [$defaultAvatarUrl !== '' ? $defaultAvatarUrl : self::DEFAULT_AVATAR_URL, 'string'];
        $settings['footer_text'] = [$footerText, 'string'];
        $settings['footer_enabled'] = [$this->booleanString($input['footer_enabled'] ?? ($currentSystem['footer_enabled'] ?? '0')), 'bool'];
        $settings['show_user_name'] = [$this->booleanString($input['show_user_name'] ?? ($currentSystem['show_user_name'] ?? '1')), 'bool'];
        $settings['show_active_role'] = [$this->booleanString($input['show_active_role'] ?? ($currentSystem['show_active_role'] ?? '1')), 'bool'];

        return ['errors' => $errors, 'settings' => $settings];
    }

    private function deleteTokenOverrides(int $userId): void
    {
        foreach (array_keys($this->presets()[self::DEFAULT_PRESET]['tokens']) as $key) {
            $this->settings->delete(self::NAMESPACE, 'token.' . $key, $userId);
        }
    }

    private function tokenOverrideRowsCount(int $userId): int
    {
        return $this->settings->tokenOverrideCount(self::NAMESPACE, $userId);
    }

    private function settingMap(int $userId): array
    {
        $map = [];

        foreach ($this->settings->list(self::NAMESPACE, $userId) as $setting) {
            $map[(string) $setting['setting_key']] = (string) ($setting['setting_value'] ?? '');
        }

        return $map;
    }

    private function personalSettingMap(int $userId): array
    {
        return array_intersect_key(
            $this->settingMap($userId),
            array_flip(self::PERSONAL_KEYS)
        );
    }

    private function safeRows(int $userId): array
    {
        return array_map(
            fn (array $row): array => [
                'setting_key' => (string) ($row['setting_key'] ?? ''),
                'setting_value' => $this->safeDebugValue((string) ($row['setting_value'] ?? '')),
                'value_type' => (string) ($row['value_type'] ?? ''),
                'user_id' => (int) ($row['user_id'] ?? $userId),
                'scope' => $userId === self::SYSTEM_USER_ID ? 'system' : 'personal',
                'updated_at' => (string) ($row['updated_at'] ?? ''),
            ],
            $this->settings->scopedRows(self::NAMESPACE, $userId)
        );
    }

    private function safeDebugValue(string $value): string
    {
        return strlen($value) > 160 ? substr($value, 0, 157) . '...' : $value;
    }

    private function assetVersion(string $path): string
    {
        return (string) (@filemtime(BASE_PATH . $path) ?: '1');
    }

    private function envDefaultPreset(): string
    {
        return $this->validPreset((string) Env::get('ADMIN_DEFAULT_THEME', self::DEFAULT_PRESET));
    }

    private function validPreset(string $preset): string
    {
        $preset = trim($preset);
        $preset = self::PRESET_ALIASES[$preset] ?? $preset;

        return isset($this->presets()[$preset]) ? $preset : self::DEFAULT_PRESET;
    }

    private function knownPresetInput(string $preset): bool
    {
        $preset = trim($preset);

        return isset(self::PRESET_ALIASES[$preset]) || isset($this->presets()[$preset]);
    }

    private function baseTokens(string $fontStack, array $overrides): array
    {
        return array_replace([
            'font_family' => $fontStack,
            'font_size_base' => '15px',
            'font_size_sm' => '13px',
            'font_size_lg' => '18px',
            'line_height_base' => '1.8',
            'font_weight_normal' => '400',
            'font_weight_medium' => '600',
            'font_weight_bold' => '700',
            'danger' => '#dc3545',
            'warning' => '#f2c94c',
            'success' => '#2f8f5b',
            'sidebar_width' => '280px',
            'topbar_height' => '78px',
        ], $overrides);
    }

    private function envFontStack(): string
    {
        $key = strtolower(str_replace([' ', '-'], '_', (string) Env::get('ADMIN_FONT_FAMILY', 'Vazirmatn')));
        $aliases = [
            'vazirmatn' => 'vazirmatn',
            'iransans' => 'vazirmatn',
            'tahoma' => 'tahoma',
            'segoe_ui' => 'segoe_ui',
            'system_ui' => 'system_ui',
        ];

        return $this->fontOptions()[$aliases[$key] ?? 'vazirmatn'];
    }

    private function validTokenValue(string $key, string $value): bool
    {
        if (in_array($key, [
            'primary', 'primary_hover', 'primary_dark', 'primary_soft',
            'accent', 'accent_hover', 'bg', 'bg_gradient_start', 'bg_gradient_end',
            'surface', 'surface_muted', 'text', 'text_muted', 'border',
            'danger', 'warning', 'success', 'sidebar_bg', 'sidebar_bg_2',
            'sidebar_text', 'sidebar_text_muted', 'sidebar_active_bg',
            'sidebar_active_text', 'header_bg', 'footer_bg', 'footer_text',
        ], true)) {
            return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
        }

        if (in_array($key, ['sidebar_width', 'topbar_height'], true)) {
            return preg_match('/^\d{2,3}px$/', $value) === 1;
        }

        if ($key === 'radius') {
            return in_array($value, ['8px', '12px', '16px', '18px', '20px', '24px'], true);
        }

        if ($key === 'font_family') {
            return in_array($value, $this->fontOptions(), true);
        }

        if ($key === 'font_size_base') {
            return in_array($value, ['13px', '14px', '15px', '16px', '1rem'], true);
        }

        if (in_array($key, ['font_size_sm', 'font_size_lg'], true)) {
            return preg_match('/^\d{2}px$/', $value) === 1;
        }

        if ($key === 'line_height_base') {
            return in_array($value, ['1.5', '1.6', '1.7', '1.8'], true);
        }

        if (in_array($key, ['font_weight_normal', 'font_weight_medium', 'font_weight_bold'], true)) {
            return in_array($value, ['400', '500', '600', '700'], true);
        }

        if ($key === 'shadow') {
            return preg_match('/^[a-zA-Z0-9\s\(\),\.\#\-]+$/', $value) === 1
                && strlen($value) <= 120
                && !str_contains(strtolower($value), 'url')
                && !str_contains(strtolower($value), 'expression');
        }

        return false;
    }

    private function cleanBrand(string $value, int $limit = 80): string
    {
        $value = trim(strip_tags($value));

        return function_exists('mb_substr')
            ? mb_substr($value, 0, $limit)
            : substr($value, 0, $limit * 2);
    }

    private function textSetting(array $settings, string $key, string $fallback, string $safeDefault, int $limit = 80): string
    {
        $value = (string) ($settings[$key] ?? $fallback);

        if ($this->looksCorrupted($value)) {
            $value = $this->looksCorrupted($fallback) ? $safeDefault : $fallback;
        }

        return $this->cleanBrand($value, $limit);
    }

    private function cleanAssetUrl(string $value, string $fallback): string
    {
        $value = trim(strip_tags($value));

        if ($value === '') {
            return $fallback;
        }

        $lower = strtolower($value);

        if (str_contains($value, '..')
            || str_contains($lower, 'javascript:')
            || str_contains($lower, 'data:')
            || str_contains($lower, 'url(')
            || preg_match('/^https?:\/\//i', $value) === 1
        ) {
            return $fallback;
        }

        if (preg_match('/^\/(?:assets\/admin\/images\/|uploads\/admin\/(?:logos|avatars)\/)[A-Za-z0-9_\-\/\.]+\.(?:svg|png|jpg|jpeg|webp|gif)$/i', $value) !== 1) {
            return $fallback;
        }

        $path = BASE_PATH . '/public' . $value;

        return is_readable($path) ? $value : $fallback;
    }

    private function assetOptions(string $publicPrefix, string $directory): array
    {
        $options = [];

        if (!is_dir($directory)) {
            return $options;
        }

        foreach (scandir($directory) ?: [] as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $path = $publicPrefix . $file;

            if ($this->cleanAssetUrl($path, '') !== '') {
                $options[$file] = $path;
            }
        }

        return $options;
    }

    private function booleanString(mixed $value): string
    {
        return in_array((string) $value, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
    }

    private function looksCorrupted(string $value): bool
    {
        return $value === ''
            || str_contains($value, '???')
            || str_contains($value, 'ط·آ§')
            || str_contains($value, 'ط¸â€ ')
            || str_contains($value, 'ط؛إ’')
            || str_contains($value, 'ط¹آ©')
            || str_contains($value, 'أ¢');
    }
}
