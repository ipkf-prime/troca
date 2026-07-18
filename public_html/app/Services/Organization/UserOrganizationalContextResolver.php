<?php

namespace App\Services\Organization;

use IPKF\Database\Database;
use PDO;

class UserOrganizationalContextResolver
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function activeAppointmentsForUser(int $userId): array
    {
        $statement = $this->db->prepare("
            SELECT
                a.public_reference AS appointment_reference,
                a.is_primary,
                a.appointment_kind,
                a.valid_from,
                a.valid_to,
                p.public_reference AS person_reference,
                COALESCE(NULLIF(p.display_name_fa, ''), p.full_name) AS person_name_fa,
                NULLIF(p.display_name_en, '') AS person_name_en,
                o.public_reference AS organization_reference,
                COALESCE(NULLIF(o.title_fa, ''), o.title) AS organization_title_fa,
                NULLIF(o.title_en, '') AS organization_title_en,
                ou.public_reference AS org_unit_reference,
                COALESCE(NULLIF(ou.title_fa, ''), ou.title) AS org_unit_title_fa,
                NULLIF(ou.title_en, '') AS org_unit_title_en,
                op.public_reference AS position_reference,
                COALESCE(NULLIF(op.title_fa, ''), NULLIF(op.title_override, ''), pos.title) AS position_title_fa,
                COALESCE(NULLIF(op.title_en, ''), NULLIF(pos.title_en, '')) AS position_title_en
            FROM users u
            INNER JOIN persons p ON p.id = u.person_id
            INNER JOIN organization_appointments a ON a.person_id = p.id
            INNER JOIN organization_positions op ON op.id = a.organization_position_id
            INNER JOIN organizations o ON o.id = a.organization_id
            LEFT JOIN org_units ou ON ou.id = op.org_unit_id
            INNER JOIN positions pos ON pos.id = op.position_id
            WHERE u.id = ?
              AND u.status = 'active'
              AND p.status = 'active'
              AND a.status = 'active'
              AND a.revoked_at IS NULL
              AND (a.valid_from IS NULL OR a.valid_from <= CURRENT_DATE)
              AND (a.valid_to IS NULL OR a.valid_to >= CURRENT_DATE)
              AND op.status = 'active'
              AND o.is_active = 1
              AND (ou.id IS NULL OR ou.status = 'active')
            ORDER BY a.is_primary DESC, a.valid_from DESC, a.id DESC
        ");
        $statement->execute([$userId]);
        return $statement->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function resolve(int $userId, ?string $selectedAppointmentReference = null): ?array
    {
        $appointments = $this->activeAppointmentsForUser($userId);
        if ($appointments === []) {
            return null;
        }

        if ($selectedAppointmentReference !== null && $selectedAppointmentReference !== '') {
            foreach ($appointments as $appointment) {
                if (hash_equals((string) $appointment['appointment_reference'], $selectedAppointmentReference)) {
                    return $appointment;
                }
            }
            throw new OrganizationalContextException('Selected appointment is not available for this user.');
        }

        foreach ($appointments as $appointment) {
            if ((int) $appointment['is_primary'] === 1) {
                return $appointment;
            }
        }

        return count($appointments) === 1 ? $appointments[0] : null;
    }

    public function switchContext(int $userId, string $appointmentReference): array
    {
        $context = $this->resolve($userId, $appointmentReference);
        if ($context === null) {
            throw new OrganizationalContextException('No active organizational context is available.');
        }

        $_SESSION['active_organizational_appointment'] = $appointmentReference;
        $this->recordEvent($userId, $appointmentReference, 'context_switched');
        return $context;
    }

    public function current(int $userId): ?array
    {
        $selected = isset($_SESSION['active_organizational_appointment'])
            ? (string) $_SESSION['active_organizational_appointment']
            : null;

        try {
            return $this->resolve($userId, $selected);
        } catch (OrganizationalContextException) {
            unset($_SESSION['active_organizational_appointment']);
            return $this->resolve($userId, null);
        }
    }

    private function recordEvent(int $userId, string $appointmentReference, string $eventType): void
    {
        $statement = $this->db->prepare("
            INSERT INTO organizational_context_events
                (public_reference, user_id, appointment_id, event_type, occurred_at)
            SELECT UUID(), ?, id, ?, CURRENT_TIMESTAMP
            FROM organization_appointments
            WHERE public_reference = ?
            LIMIT 1
        ");
        $statement->execute([$userId, $eventType, $appointmentReference]);
    }
}
