<?php

namespace App\Services\Signature;

use IPKF\Database\Database;
use PDO;

class SignatureAuthorizationResolver
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    public function resolve(string $appointmentReference, string $documentLanguage, string $purposeCode): ?array
    {
        $language = $this->normalizeLanguage($documentLanguage);
        $statement = $this->db->prepare("
            SELECT
                sa.public_reference AS signature_reference,
                sa.language_code,
                sa.asset_kind,
                sa.storage_key,
                sa.mime_type,
                sa.sha256_hash,
                auth.public_reference AS authorization_reference,
                auth.allow_shared_fallback
            FROM signature_authorizations auth
            INNER JOIN signature_assets sa ON sa.id = auth.signature_asset_id
            INNER JOIN organization_appointments a ON a.id = auth.appointment_id
            WHERE a.public_reference = ?
              AND auth.purpose_code = ?
              AND auth.status = 'active'
              AND auth.revoked_at IS NULL
              AND sa.status = 'active'
              AND sa.revoked_at IS NULL
              AND (auth.valid_from IS NULL OR auth.valid_from <= CURRENT_TIMESTAMP)
              AND (auth.valid_until IS NULL OR auth.valid_until >= CURRENT_TIMESTAMP)
              AND (sa.valid_from IS NULL OR sa.valid_from <= CURRENT_TIMESTAMP)
              AND (sa.valid_until IS NULL OR sa.valid_until >= CURRENT_TIMESTAMP)
              AND (
                    (sa.language_code = ? AND auth.allowed_language_code IN (?, 'shared'))
                    OR (sa.language_code = 'shared' AND auth.allow_shared_fallback = 1 AND auth.allowed_language_code IN (?, 'shared'))
              )
            ORDER BY (sa.language_code = ?) DESC, auth.id DESC
            LIMIT 1
        ");
        $statement->execute([$appointmentReference, $purposeCode, $language, $language, $language, $language]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    public function buildSnapshot(array $identity, array $authorization, string $language, string $signedAtUtc): array
    {
        $lang = $this->normalizeLanguage($language);
        $display = $identity['display'][$lang] ?? [];

        return [
            'signer_person_reference' => $identity['person_reference'] ?? null,
            'appointment_reference' => $identity['appointment_reference'] ?? null,
            'organization_reference' => $identity['organization_reference'] ?? null,
            'org_unit_reference' => $identity['org_unit_reference'] ?? null,
            'position_reference' => $identity['position_reference'] ?? null,
            'displayed_person_name' => $display['person'] ?? null,
            'displayed_position_title' => $display['position'] ?? null,
            'displayed_unit_title' => $display['unit'] ?? null,
            'displayed_organization_title' => $display['organization'] ?? null,
            'language' => $lang,
            'signature_asset_reference' => $authorization['signature_reference'] ?? null,
            'signature_file_hash' => $authorization['sha256_hash'] ?? null,
            'authorization_reference' => $authorization['authorization_reference'] ?? null,
            'signed_at_utc' => $signedAtUtc,
        ];
    }

    private function normalizeLanguage(string $language): string
    {
        $language = strtolower(trim($language));
        if (!in_array($language, ['fa', 'en'], true)) {
            throw new \InvalidArgumentException('Unsupported document language.');
        }
        return $language;
    }
}
