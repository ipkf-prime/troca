<?php

namespace App\Services;

use App\Repositories\AppSettingRepository;
use IPKF\Database\Database;
use IPKF\Support\Env;

class AdminThemeService extends BaseService
{
    private const NAMESPACE = 'admin.theme';

    public function __construct(protected ?AppSettingRepository $settings = null)
    {
        $this->settings ??= new AppSettingRepository();
    }

    public function presets(): array
    {
        return [
            'cooperative_light' => [
                'title' => 'تعاونی روشن',
                'tokens' => [
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
            'logo_url' => $this->cleanLogoUrl((string) ($settings['logo_url'] ?? Env::get('ADMIN_LOGO_URL', ''))),
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
        $logoUrl = $this->cleanLogoUrl((string) ($input['logo_url'] ?? ''));

        if ($brandName === '') {
            $errors[] = 'invalid_brand_name';
        }

        if ((string) ($input['logo_url'] ?? '') !== '' && $logoUrl === '') {
            $errors[] = 'invalid_logo_url';
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

    private function cleanLogoUrl(string $value): string
    {
        $value = trim(strip_tags($value));

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\/[A-Za-z0-9_\-\/\.]+$/', $value) === 1) {
            return $value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) && preg_match('/^https?:\/\//', $value) === 1
            ? $value
            : '';
    }
}
