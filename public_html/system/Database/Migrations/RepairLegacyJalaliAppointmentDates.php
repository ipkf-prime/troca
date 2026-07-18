<?php

namespace IPKF\Database\Migrations;

use IPKF\Database\Database;
use IPKF\Support\PersianDate;
use PDO;
use Throwable;

class RepairLegacyJalaliAppointmentDates extends Migration
{
    public function up(): void
    {
        if (!Database::tableExists('organization_appointments')) {
            return;
        }

        $statement = $this->db->query("
            SELECT id, valid_from, valid_to
            FROM organization_appointments
            WHERE (valid_from IS NOT NULL AND YEAR(valid_from) BETWEEN 1200 AND 1700)
               OR (valid_to IS NOT NULL AND YEAR(valid_to) BETWEEN 1200 AND 1700)
            ORDER BY id ASC
        ");
        $rows = $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($rows === []) {
            return;
        }

        $update = $this->db->prepare("
            UPDATE organization_appointments
            SET valid_from = ?, valid_to = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $this->db->beginTransaction();
        try {
            foreach ($rows as $row) {
                $update->execute([
                    $this->repairDate($row['valid_from'] ?? null),
                    $this->repairDate($row['valid_to'] ?? null),
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
    }

    private function repairDate(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = substr($value, 0, 10);
        if (!preg_match('/^(1[2-7]\d{2})-(\d{2})-(\d{2})$/', $date, $matches)) {
            return $date;
        }

        return PersianDate::toGregorianDate($matches[1] . '/' . $matches[2] . '/' . $matches[3]);
    }
}
