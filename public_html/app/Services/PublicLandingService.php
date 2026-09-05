<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use IPKF\Database\Database;
use IPKF\Support\Clock;
use IPKF\Support\PersianDate;
use IPKF\Support\Version;
use PDO;
use RuntimeException;

class PublicLandingService
{
    private const ITEM_TYPES = [
        'nav', 'slide', 'announcement', 'card', 'footer_link',
    ];

    private const SETTING_KEYS = [
        'meta_description', 'status_text',
        'show_status', 'show_version',
        'show_deploy_date', 'show_register',
        'login_label', 'register_label', 'register_url',
        'runtime_status_position',
        'runtime_online_position',
        'runtime_datetime_position',
        'runtime_version_position',
        'runtime_deploy_position',
    ];

    public function __construct(
        private ?PDO $db = null,
        private ?PublicLandingMediaUploadService $uploader = null
    ) {
        $this->db ??= Database::connect();
        $this->uploader ??= new PublicLandingMediaUploadService();
    }

    public function publicPage(): array
    {
        $settings = $this->settings();
        $theme = class_exists(AdminThemeService::class)
            ? (new AdminThemeService())->systemTheme()
            : [];

        $groups = array_fill_keys(self::ITEM_TYPES, []);

        foreach ($this->runtimeItems() as $item) {
            $groups[$item['item_type']][] = $item;
        }

        $runtime =
            $this->runtimeMetadata();

        return [
            'settings' => $settings,
            'theme' => $theme,
            'navigation' => $groups['nav'],
            'slides' => $groups['slide'],
            'announcements' => $groups['announcement'],
            'cards' => $groups['card'],
            'footer_links' => $groups['footer_link'],
            'runtime' => $runtime,
            'runtime_slots' =>
                $this->runtimeSlots(
                    $settings,
                    $runtime
                ),
        ];
    }

    public function adminPage(int $editId = 0): array
    {
        $editing = null;

        if ($editId > 0) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM public_page_items
                WHERE id = ?
                LIMIT 1
            ");
            $stmt->execute([$editId]);
            $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

            if (is_array($editing)) {
                $editing += $this->scheduleForForm($editing);
            }
        }

        return [
            'settings' => $this->settings(false),
            'system_identity' =>
                (new AdminThemeService())->systemTheme(),
            'items' => $this->db->query("
                SELECT *
                FROM public_page_items
                ORDER BY item_type, sort_order, id
            ")->fetchAll(PDO::FETCH_ASSOC) ?: [],
            'editing' => $editing,
            'item_types' => self::ITEM_TYPES,
        ];
    }

    public function saveSettings(array $input, int $userId, ?array $logoUpload = null): void
    {
        $this->authorize($userId);

        $uploadedLogo =
            $this->uploader->storeLogo(
                $logoUpload
            );

        if ($uploadedLogo !== null) {
            $input['logo_url'] =
                $uploadedLogo;
        }

        $identity =
            (new AdminThemeService())
                ->saveSystemIdentity($input);

        if (!($identity['ok'] ?? false)) {
            throw new RuntimeException(
                'system_identity_invalid'
            );
        }

        $stmt = $this->db->prepare("
            INSERT INTO public_page_settings (
                setting_key, setting_value,
                created_by_user_id, updated_by_user_id
            )
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                setting_value = VALUES(setting_value),
                updated_by_user_id = VALUES(updated_by_user_id),
                updated_at = CURRENT_TIMESTAMP
        ");

        foreach (self::SETTING_KEYS as $key) {
            $value = $this->settingInput($key, $input);
            $stmt->execute([$key, $value, $userId, $userId]);
        }
    }

    public function saveItem(
        array $input,
        array $files,
        int $userId
    ): int {
        $this->authorize($userId);

        $id = max(0, (int) ($input['id'] ?? 0));
        $type = trim((string) ($input['item_type'] ?? ''));
        $code = strtolower(trim((string) ($input['code'] ?? '')));
        $title = trim((string) ($input['title'] ?? ''));

        if (!in_array($type, self::ITEM_TYPES, true)) {
            throw new RuntimeException('landing_item_type_invalid');
        }

        if (
            preg_match('/^[a-z][a-z0-9_-]{1,79}$/', $code) !== 1
            || $title === ''
            || mb_strlen($title) > 255
        ) {
            throw new RuntimeException('landing_item_invalid');
        }

        $current = $id > 0 ? $this->item($id) : null;

        $image = $this->uploader->store($files['image'] ?? null)
            ?? (string) ($current['image_url'] ?? '');

        $mobileImage =
            $this->uploader->store($files['mobile_image'] ?? null)
            ?? (string) ($current['mobile_image_url'] ?? '');

        $actionUrl = $this->cleanUrl(
            (string) ($input['action_url'] ?? '')
        );

        $target = (string) ($input['action_target'] ?? '_self');
        $target = in_array($target, ['_self', '_blank'], true)
            ? $target : '_self';

        $values = [
            $type,
            $code,
            trim((string) ($input['eyebrow'] ?? '')),
            $title,
            trim((string) ($input['body'] ?? '')),
            $image,
            $mobileImage,
            trim((string) ($input['action_text'] ?? '')),
            $actionUrl,
            $target,
            trim((string) ($input['icon'] ?? '')),
            max(0, min(9999, (int) ($input['sort_order'] ?? 100))),
            isset($input['is_active']) ? 1 : 0,
            $this->scheduleToUtc(
                $input['starts_date'] ?? null,
                $input['starts_time'] ?? null
            ),
            $this->scheduleToUtc(
                $input['ends_date'] ?? null,
                $input['ends_time'] ?? null
            ),
            $userId,
        ];

        if ($id > 0) {
            $stmt = $this->db->prepare("
                UPDATE public_page_items
                SET item_type=?, code=?, eyebrow=?, title=?, body=?,
                    image_url=?, mobile_image_url=?, action_text=?,
                    action_url=?, action_target=?, icon=?, sort_order=?,
                    is_active=?, starts_at=?, ends_at=?,
                    updated_by_user_id=?, updated_at=CURRENT_TIMESTAMP
                WHERE id=?
            ");
            $stmt->execute([...$values, $id]);
            return $id;
        }

        $stmt = $this->db->prepare("
            INSERT INTO public_page_items (
                item_type, code, eyebrow, title, body,
                image_url, mobile_image_url, action_text,
                action_url, action_target, icon, sort_order,
                is_active, starts_at, ends_at,
                created_by_user_id, updated_by_user_id
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([...$values, $userId]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteItem(int $id, int $userId): void
    {
        $this->authorize($userId);

        $stmt = $this->db->prepare(
            "DELETE FROM public_page_items WHERE id = ?"
        );
        $stmt->execute([$id]);
    }

    private function runtimeItems(): array
    {
        return $this->db->query("
            SELECT *
            FROM public_page_items
            WHERE is_active = 1
              AND (starts_at IS NULL OR starts_at <= UTC_TIMESTAMP())
              AND (ends_at IS NULL OR ends_at >= UTC_TIMESTAMP())
            ORDER BY item_type, sort_order, id
        ")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function settings(bool $activeOnly = true): array
    {
        $sql = "
            SELECT setting_key, setting_value
            FROM public_page_settings
        ";

        if ($activeOnly) {
            $sql .= " WHERE is_active = 1";
        }

        $rows = $this->db->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $map = [];

        foreach ($rows as $row) {
            $map[(string) $row['setting_key']] =
                (string) ($row['setting_value'] ?? '');
        }

        $defaults = [
            'runtime_status_position' =>
                'right',
            'runtime_online_position' =>
                'right',
            'runtime_datetime_position' =>
                'center',
            'runtime_version_position' =>
                'left',
            'runtime_deploy_position' =>
                'left',
        ];

        return $map + $defaults;
    }

    private function item(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM public_page_items WHERE id=? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function authorize(int $userId): void
    {
        if (
            $userId < 1
            || !(new AdminNavigationRbacService())->can(
                $userId,
                'admin.settings.manage'
            )
        ) {
            throw new RuntimeException('landing_management_forbidden');
        }
    }

    private function settingInput(string $key, array $input): string
    {
        if (
            str_starts_with(
                $key,
                'runtime_'
            )
            && str_ends_with(
                $key,
                '_position'
            )
        ) {
            $position =
                trim(
                    (string) (
                        $input[$key]
                        ?? ''
                    )
                );

            return in_array(
                $position,
                [
                    'right',
                    'center',
                    'left',
                    'hidden',
                ],
                true
            )
                ? $position
                : 'hidden';
        }


        if (str_starts_with($key, 'show_')) {
            return isset($input[$key]) ? '1' : '0';
        }

        $value = trim((string) ($input[$key] ?? ''));

        if ($key === 'register_url') {
            return $this->cleanUrl($value);
        }

        return mb_substr($value, 0, 2000);
    }

    private function cleanUrl(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        if (
            str_starts_with($value, '/')
            || str_starts_with($value, '#')
        ) {
            return $value;
        }

        if (
            filter_var($value, FILTER_VALIDATE_URL)
            && in_array(
                strtolower((string) parse_url($value, PHP_URL_SCHEME)),
                ['http', 'https'],
                true
            )
        ) {
            return $value;
        }

        throw new RuntimeException('landing_url_invalid');
    }

    private function scheduleToUtc(
        mixed $dateValue,
        mixed $timeValue
    ): ?string {
        $dateValue = trim((string) $dateValue);

        if ($dateValue === '') {
            return null;
        }

        $gregorian = PersianDate::toGregorianDate($dateValue);
        $time = trim(PersianDate::normalizeDigits(
            (string) $timeValue
        ));

        if ($time === '') {
            $time = '00:00';
        }

        if (preg_match('/^\d{2}:\d{2}$/', $time) !== 1) {
            throw new RuntimeException('landing_time_invalid');
        }

        $local = new DateTimeImmutable(
            $gregorian . ' ' . $time . ':00',
            Clock::displayTimezone()
        );

        return Clock::databaseTimestamp($local);
    }

    private function scheduleForForm(array $item): array
    {
        $result = [
            'starts_date_form' => '',
            'starts_time_form' => '',
            'ends_date_form' => '',
            'ends_time_form' => '',
        ];

        foreach (['starts', 'ends'] as $prefix) {
            $stored = $item[$prefix . '_at'] ?? null;
            $instant = Clock::parseStoredInstant($stored);

            if ($instant === null) {
                continue;
            }

            $local = Clock::convertToDisplayTimezone($instant);
            $result[$prefix . '_date_form'] =
                PersianDate::fromGregorianDate(
                    $local->format('Y-m-d')
                );
            $result[$prefix . '_time_form'] =
                $local->format('H:i');
        }

        return $result;
    }

    private function runtimeMetadata(): array
    {
        $nowUtc = Clock::nowUtc();
        $now = Clock::convertToDisplayTimezone($nowUtc);

        return [
            'version' =>
                $this->persianDigits(
                    Version::current()
                ),
            'persian_date' => PersianDate::fromGregorianDate(
                $now->format('Y-m-d')
            ),
            'time' =>
                $this->persianDigits(
                    $now->format('H:i:s')
                ),
            'utc_iso' => Clock::isoUtc($nowUtc),
            'timezone' => Clock::displayTimezoneName(),
            'deployment_at' => $this->deploymentTime(),
            'online_users' =>
                (
                    new OnlinePresenceService()
                )->onlineCount(),
        ];
    }

    private function runtimeSlots(
        array $settings,
        array $runtime
    ): array {
        $slots = [
            'right' => [],
            'center' => [],
            'left' => [],
        ];

        $push = static function (
            array &$slots,
            string $position,
            array $item
        ): void {
            if (
                isset($slots[$position])
            ) {
                $slots[$position][] =
                    $item;
            }
        };

        if (
            ($settings['show_status']
                ?? '1') === '1'
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_status_position'
                    ]
                    ?? 'right'
                ),
                [
                    'key' => 'status',
                    'kind' => 'status',
                    'text' =>
                        (string) (
                            $settings[
                                'status_text'
                            ]
                            ?? 'سامانه فعال است'
                        ),
                ]
            );
        }

        if (
            $runtime['online_users']
            !== null
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_online_position'
                    ]
                    ?? 'right'
                ),
                [
                    'key' => 'online',
                    'kind' => 'online',
                    'text' =>
                        'کاربران آنلاین: '
                        . $this->persianDigits(
                            (string) (
                                $runtime[
                                    'online_users'
                                ]
                                ?? 0
                            )
                        ),
                ]
            );
        }

        $push(
            $slots,
            (string) (
                $settings[
                    'runtime_datetime_position'
                ]
                ?? 'center'
            ),
            [
                'key' => 'datetime',
                'kind' => 'datetime',
                'text' =>
                    trim(
                        (string) (
                            $runtime[
                                'persian_date'
                            ]
                            ?? ''
                        )
                        . ' | '
                        . (string) (
                            $runtime[
                                'time'
                            ]
                            ?? ''
                        )
                    ),
            ]
        );

        if (
            ($settings['show_version']
                ?? '1') === '1'
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_version_position'
                    ]
                    ?? 'left'
                ),
                [
                    'key' => 'version',
                    'kind' => 'version',
                    'text' =>
                        'نسخه '
                        . (string) (
                            $runtime['version']
                            ?? ''
                        ),
                ]
            );
        }

        if (
            ($settings['show_deploy_date']
                ?? '1') === '1'
            && !empty(
                $runtime[
                    'deployment_at'
                ]
            )
        ) {
            $push(
                $slots,
                (string) (
                    $settings[
                        'runtime_deploy_position'
                    ]
                    ?? 'left'
                ),
                [
                    'key' => 'deploy',
                    'kind' => 'deploy',
                    'text' =>
                        'استقرار: '
                        . (string) (
                            $runtime[
                                'deployment_at'
                            ]
                            ?? ''
                        ),
                ]
            );
        }

        return $slots;
    }

    private function persianDigits(
        string $value
    ): string {
        return strtr(
            $value,
            [
                '0' => '۰',
                '1' => '۱',
                '2' => '۲',
                '3' => '۳',
                '4' => '۴',
                '5' => '۵',
                '6' => '۶',
                '7' => '۷',
                '8' => '۸',
                '9' => '۹',
            ]
        );
    }

    private function deploymentTime(): ?string
    {
        $file = BASE_PATH . '/storage/runtime-build.json';

        if (!is_readable($file)) {
            return null;
        }

        $data = json_decode(
            (string) file_get_contents($file),
            true
        );

        if (!is_array($data)) {
            return null;
        }

        foreach (
            ['deployed_at', 'generated_at', 'built_at', 'created_at']
            as $key
        ) {
            $instant = Clock::parseStoredInstant($data[$key] ?? null);

            if ($instant === null) {
                continue;
            }

            $local = Clock::convertToDisplayTimezone($instant);

            return
                PersianDate::fromGregorianDate(
                    $local->format('Y-m-d')
                )
                . ' '
                . $this->persianDigits(
                    $local->format('H:i')
                );
        }

        return null;
    }
}
