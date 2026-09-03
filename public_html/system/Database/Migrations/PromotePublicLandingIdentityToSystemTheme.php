<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

class PromotePublicLandingIdentityToSystemTheme extends Migration
{
    public function up(): void
    {
        $legacy = [
            'page_title' => [
                'system_key' =>
                    'brand_name',

                'legacy_default' =>
                    'سامانه هوشمند تروکا',

                /*
                 * The historical landing default is
                 * also the desired global identity,
                 * so it must still be promotable.
                 */
                'promote_default' =>
                    true,

                /*
                 * Only these system values may be
                 * replaced automatically.
                 */
                'system_replaceable' => [
                    '',
                    'پنل مدیریت تروکا',
                    'سامانه هوشمند تروکا',
                ],
            ],

            'footer_text' => [
                'system_key' =>
                    'footer_text',

                'legacy_default' =>
                    'کلیه حقوق این وب‌سایت محفوظ است.',

                /*
                 * A generic historical footer default
                 * should not overwrite global identity.
                 */
                'promote_default' =>
                    false,

                'system_replaceable' => [
                    '',
                    'کلیه حقوق این وب‌سایت متعلق به سامانه هوشمندتروکا می‌باشد.',
                ],
            ],
        ];

        $landing =
            $this->db->prepare("
                SELECT setting_value
                FROM public_page_settings
                WHERE setting_key = ?
                LIMIT 1
            ");

        $system =
            $this->db->prepare("
                SELECT setting_value
                FROM app_settings
                WHERE user_id = 0
                  AND namespace = 'admin.theme'
                  AND setting_key = ?
                LIMIT 1
            ");

        $upsert =
            $this->db->prepare("
                INSERT INTO app_settings (
                    user_id,
                    namespace,
                    setting_key,
                    setting_value,
                    value_type,
                    is_public,
                    created_at,
                    updated_at
                )
                VALUES (
                    0,
                    'admin.theme',
                    ?,
                    ?,
                    'string',
                    1,
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                )
                ON DUPLICATE KEY UPDATE
                    setting_value =
                        VALUES(setting_value),
                    value_type =
                        'string',
                    is_public =
                        1,
                    updated_at =
                        CURRENT_TIMESTAMP
            ");

        foreach (
            $legacy
            as $landingKey => $config
        ) {
            $landing->execute([
                $landingKey,
            ]);

            $rawLandingValue =
                $landing->fetchColumn();

            /*
             * A missing legacy row has nothing
             * to promote.
             */
            if ($rawLandingValue === false) {
                continue;
            }

            $landingValue =
                trim(
                    (string) $rawLandingValue
                );

            if ($landingValue === '') {
                continue;
            }

            $isLegacyDefault =
                $landingValue ===
                    $config[
                        'legacy_default'
                    ];

            if (
                $isLegacyDefault
                && (
                    $config[
                        'promote_default'
                    ]
                    ?? false
                ) !== true
            ) {
                continue;
            }

            $system->execute([
                $config['system_key'],
            ]);

            $rawSystemValue =
                $system->fetchColumn();

            $systemValue =
                $rawSystemValue === false
                    ? ''
                    : trim(
                        (string) $rawSystemValue
                    );

            $replaceable =
                $config[
                    'system_replaceable'
                ]
                ?? [];

            if (
                !in_array(
                    $systemValue,
                    $replaceable,
                    true
                )
            ) {
                /*
                 * Preserve an explicitly customized
                 * global system identity.
                 */
                continue;
            }

            $upsert->execute([
                $config['system_key'],
                $landingValue,
            ]);
        }

        /*
         * Global identity is authoritative after
         * this migration. Remove legacy duplicates
         * regardless of whether they were promoted
         * or intentionally preserved.
         */
        $delete =
            $this->db->prepare("
                DELETE FROM public_page_settings
                WHERE setting_key IN (?, ?)
            ");

        $delete->execute([
            'page_title',
            'footer_text',
        ]);
    }

    public function down(): void
    {
        $values = [
            [
                'page_title',
                'سامانه هوشمند تروکا',
                10,
            ],
            [
                'footer_text',
                'کلیه حقوق این وب‌سایت محفوظ است.',
                40,
            ],
        ];

        $stmt =
            $this->db->prepare("
                INSERT INTO
                    public_page_settings (
                        setting_key,
                        setting_value,
                        sort_order
                    )
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    setting_value =
                        VALUES(setting_value),
                    sort_order =
                        VALUES(sort_order)
            ");

        foreach ($values as $row) {
            $stmt->execute($row);
        }
    }
}
