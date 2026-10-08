<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class RefineAdminUserImpersonationOperateEntitlementModel
    extends Migration
{
    private const OWNER_SEED =
        'c4-1-1-operate-person-grant-ui-v1';

    private const ITEMS = [
        'core.users.impersonation.operate.access.description' =>
            'این مجوز به‌صورت فردی اعطا می‌شود. استفاده از آن فقط زمانی ممکن است که شخص دسترسی پایه ورود مدیریتی و محدوده معتبر داشته باشد.',

        'core.users.impersonation.operate.access.status.allowed' =>
            'مجوز عملیاتی فعال و قابل استفاده است',

        'core.users.impersonation.operate.access.status.ineligible' =>
            'مجوز عملیاتی اعطا شده است؛ دسترسی پایه ورود مدیریتی این شخص هنوز فعال نیست',
    ];


    public function up(): void
    {
        $this->db->beginTransaction();

        try {
            foreach (
                self::ITEMS
                as $key => $body
            ) {
                $statement =
                    $this->db->prepare(
                        "SELECT
                            overrides.id,
                            overrides.metadata_json

                         FROM ui_content_definitions
                            AS definitions

                         INNER JOIN ui_content_overrides
                            AS overrides
                           ON overrides.definition_id =
                                definitions.id

                         WHERE definitions.content_key = ?
                           AND overrides.scope_key =
                                'scope:core:surface:impersonation'
                           AND overrides.locale = 'fa'
                           AND overrides.is_active = 1

                         LIMIT 1
                         FOR UPDATE"
                    );

                $statement->execute([
                    $key,
                ]);

                $row =
                    $statement->fetch(
                        PDO::FETCH_ASSOC
                    );

                if (!is_array($row)) {
                    throw new RuntimeException(
                        'a2r2_ui_override_missing:'
                        . $key
                    );
                }

                $metadata =
                    json_decode(
                        (string) (
                            $row['metadata_json']
                            ?? ''
                        ),
                        true
                    );

                if (
                    !is_array($metadata)
                    || (
                        $metadata['seed']
                        ?? null
                    ) !== self::OWNER_SEED
                ) {
                    throw new RuntimeException(
                        'a2r2_ui_override_owner_invalid:'
                        . $key
                    );
                }

                $update =
                    $this->db->prepare(
                        'UPDATE ui_content_overrides
                         SET title = ?,
                             body = ?
                         WHERE id = ?'
                    );

                $update->execute([
                    $body,
                    $body,
                    (int) $row['id'],
                ]);
            }

            $this->db->commit();

        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }


    public function down(): void
    {
        /*
         * Exact deployment preimage owns controlled rollback.
         */
    }
}
