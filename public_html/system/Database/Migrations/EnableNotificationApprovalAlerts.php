<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;

class EnableNotificationApprovalAlerts extends Migration
{
    public function up(): void
    {
        if (!Database::tableExists(
            'admin_navigation_items'
        )) {
            return;
        }

        $statement = $this->db->prepare("
            UPDATE admin_navigation_items
            SET badge_source = ?,
                hide_when_badge_empty = 0,
                updated_at = CURRENT_TIMESTAMP
            WHERE shell_key = 'core'
              AND item_key =
                'notification-approval-queue'
        ");

        $statement->execute([
            'notification_approval_pending_count',
        ]);
    }

    public function down(): void
    {
    }
}
