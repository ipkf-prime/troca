<?php

namespace App\Services;

use App\Repositories\AppSettingRepository;
use IPKF\Database\Database;
use IPKF\Support\Env;

class AdminThemeService extends BaseService
{
    private const NAMESPACE = 'admin.theme';
    private const DEFAULT_LOGO_URL = '/assets/admin/images/logos/default-logo.svg';
    private const DEFAULT_AVATAR_URL = '/assets/admin/images/avatars/default-avatar.svg';

    public function __construct(protected ?AppSettingRepository $settings = null)
    {
        $this->settings ??= new AppSettingRepository();
    }

    public function presets(): array
    {
        $fontStack = $this->fontOptions()['vazirmatn'];

        return [
            'cooperative_light' => [
                'title' => 'تعاونی روشن',
                'tokens' => [
                    'font_family' => $fontStack,
                    'font_size_base' => '15px',
                    'line_height_base' => '1.8',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '600',
                    'font_weight_bold' => '700',
                    'primary' => '#2f8f5b',
                    'primary_hover' => '#247449',
                    'primary_soft' => '#e8f5ee',
                    'accent' => '#f2c94c',
                    'bg' => '#f6faf7',
                    'bg_gradient_start' => '#f6faf7',
                    'bg_gradient_end' => '#e8f5ee',
                    'surface' => '#ffffff',
                    'surface_muted' => '#f1f7f3',
                    'text' => '#1f2933',
                    'text_muted' => '#64748b',
                    'border' => '#d8e7dd',
                    'danger' => '#dc3545',
                    'warning' => '#f2c94c',
                    'success' => '#2f8f5b',
                    'radius' => '16px',
                    'shadow' => '0 12px 32px rgba(31, 41, 51, .08)',
                    'sidebar_width' => '280px',
                    'topbar_height' => '76px',
                ],
            ],
            'cooperative_classic' => [
                'title' => 'سبز کلاسیک',
                'tokens' => [
                    'font_family' => $fontStack,
                    'font_size_base' => '15px',
                    'line_height_base' => '1.8',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '600',
                    'font_weight_bold' => '700',
                    'primary' => '#1f6f4a',
                    'primary_hover' => '#18583b',
                    'primary_soft' => '#e4f0e8',
                    'accent' => '#d6aa42',
                    'bg' => '#f4f8f5',
                    'bg_gradient_start' => '#f4f8f5',
                    'bg_gradient_end' => '#dfeee5',
                    'surface' => '#ffffff',
                    'surface_muted' => '#edf5f0',
                    'text' => '#16241d',
                    'text_muted' => '#5f7169',
                    'border' => '#cadfd2',
                    'danger' => '#b83232',
                    'warning' => '#d6aa42',
                    'success' => '#1f6f4a',
                    'radius' => '14px',
                    'shadow' => '0 12px 28px rgba(15, 63, 49, .09)',
                    'sidebar_width' => '280px',
                    'topbar_height' => '76px',
                ],
            ],
            'neutral_light' => [
                'title' => 'روشن خنثی',
                'tokens' => [
                    'font_family' => $fontStack,
                    'font_size_base' => '15px',
                    'line_height_base' => '1.75',
                    'font_weight_normal' => '400',
                    'font_weight_medium' => '600',
                    'font_weight_bold' => '700',
                    'primary' => '#3f7f6b',
                    'primary_hover' => '#326657',
                    'primary_soft' => '#eef5f2',
                    'accent' => '#e5b93f',
                    'bg' => '#f7f9fb',
                    'bg_gradient_start' => '#f7f9fb',
                    'bg_gradient_end' => '#eef2f7',
                    'surface' => '#ffffff',
                    'surface_muted' => '#f1f5f9',
                    'text' => '#1f2937',
                    'text_muted' => '#64748b',
                    'border' => '#dbe3ea',
                    'danger' => '#dc3545',
                    'warning' => '#e5b93f',
                    'success' => '#2f8f5b',
                    'radius' => '16px',
                    'shadow' => '0 12px 32px rgba(15, 23, 42, .08)',
                    'sidebar_width' => '280px',
                    'topbar_height' => '76px',
                ],
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

    public function theme(): array
    {
        $settings = $this->settingsAvailable() ? $this->settingMap() : [];
        $preset = (string) ($settings['active_preset'] ?? 'cooperative_light');

        if (!isset($this->presets()[$preset])) {
            $preset = 'cooperative_light';
        }

        $tokens = $this->presets()[$preset]['tokens'];

        foreach ($tokens as $key => $default) {
            $custom = $settings['token.' . $key] ?? null;

            if ($custom !== null && $this->validTokenValue($key, $custom)) {
                $tokens[$key] = $custom;
            }
        }

        return [
            'active_preset' => $preset,
            'preset_title' => $this->presets()[$preset]['title'],
            'brand_name' => $this->cleanBrand((string) ($settings['brand_name'] ?? Env::get('ADMIN_BRAND_NAME', 'پنل مدیریت تروکا'))),
            'logo_url' => $this->cleanAssetUrl((string) ($settings['logo_url'] ?? Env::get('ADMIN_LOGO_URL', self::DEFAULT_LOGO_URL)), self::DEFAULT_LOGO_URL),
            'default_avatar_url' => $this->cleanAssetUrl((string) ($settings['default_avatar_url'] ?? Env::get('ADMIN_DEFAULT_AVATAR_URL', self::DEFAULT_AVATAR_URL)), self::DEFAULT_AVATAR_URL),
            'tokens' => $tokens,
        ];
    }

    public function cssVariables(): string
    {
        $tokens = $this->theme()['tokens'];
        $lines = [':root {'];

        foreach ($tokens as $key => $value) {
            $lines[] = '  --admin-' . str_replace('_', '-', $key) . ': ' . $value . ';';
        }

        $lines[] = '}';

        return implode("\n", $lines);
    }

    public function update(array $input): array
    {
        if (!$this->settingsAvailable()) {
            return ['ok' => false, 'errors' => ['settings_unavailable']];
        }

        $errors = [];
        $preset = (string) ($input['active_preset'] ?? 'cooperative_light');

        if (!isset($this->presets()[$preset])) {
            $errors[] = 'invalid_preset';
        }

        $brandName = $this->cleanBrand((string) ($input['brand_name'] ?? ''));
        $logoUrl = $this->cleanAssetUrl((string) ($input['logo_url'] ?? ''), '');
        $defaultAvatarUrl = $this->cleanAssetUrl((string) ($input['default_avatar_url'] ?? ''), '');

        if ($brandName === '') {
            $errors[] = 'invalid_brand_name';
        }

        if ((string) ($input['logo_url'] ?? '') !== '' && $logoUrl === '') {
            $errors[] = 'invalid_logo_url';
        }

        if ((string) ($input['default_avatar_url'] ?? '') !== '' && $defaultAvatarUrl === '') {
            $errors[] = 'invalid_default_avatar_url';
        }

        foreach (array_keys($this->presets()['cooperative_light']['tokens']) as $key) {
            $value = trim((string) ($input['token_' . $key] ?? ''));

            if ($value !== '' && !$this->validTokenValue($key, $value)) {
                $errors[] = 'invalid_' . $key;
            }
        }

        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $this->settings->put(self::NAMESPACE, 'active_preset', $preset, 'string', true);
        $this->settings->put(self::NAMESPACE, 'brand_name', $brandName, 'string', true);
        $this->settings->put(self::NAMESPACE, 'logo_url', $logoUrl, 'string', true);
        $this->settings->put(self::NAMESPACE, 'default_avatar_url', $defaultAvatarUrl, 'string', true);

        foreach (array_keys($this->presets()['cooperative_light']['tokens']) as $key) {
            $value = trim((string) ($input['token_' . $key] ?? ''));
            $this->settings->put(self::NAMESPACE, 'token.' . $key, $value, 'string', true);
        }

        return ['ok' => true, 'errors' => []];
    }

    public function settingsAvailable(): bool
    {
        return Database::tableExists('app_settings');
    }

    private function settingMap(): array
    {
        $map = [];

        foreach ($this->settings->list(self::NAMESPACE) as $setting) {
            $map[(string) $setting['setting_key']] = (string) ($setting['setting_value'] ?? '');
        }

        return $map;
    }

    private function validTokenValue(string $key, string $value): bool
    {
        if (in_array($key, ['radius', 'sidebar_width', 'topbar_height'], true)) {
            return preg_match('/^\d{1,3}px$/', $value) === 1;
        }

        if ($key === 'font_family') {
            return in_array($value, $this->fontOptions(), true);
        }

        if ($key === 'font_size_base') {
            return preg_match('/^(\d{1,2}px|1(\.\d{1,2})?rem)$/', $value) === 1;
        }

        if ($key === 'line_height_base') {
            return preg_match('/^(1(\.\d{1,2})?|2(\.0{1,2})?|[1-2]\d?px)$/', $value) === 1;
        }

        if (in_array($key, ['font_weight_normal', 'font_weight_medium', 'font_weight_bold'], true)) {
            return preg_match('/^[1-9]00$/', $value) === 1;
        }

        if ($key === 'shadow') {
            return preg_match('/^[a-zA-Z0-9\s\(\),\.\#\-]+$/', $value) === 1
                && strlen($value) <= 120
                && !str_contains(strtolower($value), 'url');
        }

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
    }

    private function cleanBrand(string $value): string
    {
        $value = trim(strip_tags($value));

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 80)
            : substr($value, 0, 160);
    }

    private function cleanAssetUrl(string $value, string $fallback): string
    {
        $value = trim(strip_tags($value));

        if ($value === '') {
            return $fallback;
        }

        if (str_contains($value, '..')) {
            return $fallback;
        }

        if (preg_match('/^\/(?:assets\/admin\/images\/|uploads\/admin\/(?:logos|avatars)\/)[A-Za-z0-9_\-\/\.]+\.(?:svg|png|jpg|jpeg|webp|gif)$/i', $value) === 1) {
            return $value;
        }

        return $fallback;
    }
}
