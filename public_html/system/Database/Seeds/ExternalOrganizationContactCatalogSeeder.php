<?php

namespace IPKF\Database\Seeds;

class ExternalOrganizationContactCatalogSeeder extends Seeder
{
    private const TYPES = [
        [
            'code' => 'phone',
            'title' => 'تلفن',
            'channel' => 'phone',
            'sort_order' => 10,
        ],
        [
            'code' => 'extension',
            'title' => 'داخلی',
            'channel' => 'phone',
            'sort_order' => 20,
        ],
        [
            'code' => 'mobile',
            'title' => 'تلفن همراه',
            'channel' => 'mobile',
            'sort_order' => 30,
        ],
        [
            'code' => 'fax',
            'title' => 'فاکس',
            'channel' => 'fax',
            'sort_order' => 40,
        ],
        [
            'code' => 'email',
            'title' => 'ایمیل',
            'channel' => 'email',
            'sort_order' => 50,
        ],
        [
            'code' => 'website',
            'title' => 'وب‌سایت',
            'channel' => 'url',
            'sort_order' => 60,
        ],
        [
            'code' => 'system',
            'title' => 'سامانه مکاتبات',
            'channel' => 'system',
            'sort_order' => 70,
        ],
    ];

    public function run(): void
    {
        if (!$this->tableExists('contact_types')) {
            return;
        }

        $find = $this->db->prepare("
            SELECT id
            FROM contact_types
            WHERE code = ?
            LIMIT 1
        ");

        $insert = $this->db->prepare("
            INSERT INTO contact_types (
                code,
                title,
                channel,
                validation_pattern,
                sort_order,
                status,
                created_at,
                updated_at
            ) VALUES (
                ?,
                ?,
                ?,
                NULL,
                ?,
                'active',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");

        $update = $this->db->prepare("
            UPDATE contact_types
            SET
                title = ?,
                channel = ?,
                sort_order = ?,
                status = 'active',
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        foreach (self::TYPES as $type) {
            $find->execute([
                $type['code'],
            ]);

            $id = $find->fetchColumn();

            if ($id === false) {
                $insert->execute([
                    $type['code'],
                    $type['title'],
                    $type['channel'],
                    $type['sort_order'],
                ]);

                continue;
            }

            $update->execute([
                $type['title'],
                $type['channel'],
                $type['sort_order'],
                (int) $id,
            ]);
        }

        /*
         * structured-phone-retire-standalone-extension-v1
         *
         * Legacy row is preserved for historical references.
         * New extensions belong to their phone contact row.
         */
        $retireStandaloneExtension =
            $this->db->prepare("
                UPDATE contact_types
                SET status = 'inactive',
                    updated_at = CURRENT_TIMESTAMP
                WHERE code = 'extension'
            ");

        $retireStandaloneExtension->execute();

    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");

        $statement->execute([
            $table,
        ]);

        return
            (int) $statement->fetchColumn()
            > 0;
    }
}
