<?php

namespace App\Services\Automation\Correspondence;

use App\Services\Automation\CoreReference;
use App\Services\Automation\CoreReferenceType;
use App\Services\Automation\CoreReferenceValidator;
use IPKF\Database\Database;
use IPKF\Support\Env;
use PDO;

class CoreReferenceOptions
{
    public function options(): array
    {
        return [
            'users' => $this->users(),
            'persons' => $this->persons(),
            'organizations' => $this->organizations(),
            'org_units' => $this->orgUnits(),
        ];
    }

    public function tokenFor(string $kind, int $id): ?string
    {
        if ($id < 1 || !in_array($kind, [
            CoreReferenceType::PERSON,
            CoreReferenceType::USER,
            CoreReferenceType::ORGANIZATION,
            CoreReferenceType::ORG_UNIT,
        ], true)) {
            return null;
        }

        return $this->token($kind, $id);
    }

    public function decode(string $token): ?array
    {
        $encoded = strtr(trim($token), '-_', '+/');
        $encoded .= str_repeat('=', (4 - strlen($encoded) % 4) % 4);
        $payload = json_decode(base64_decode($encoded, true) ?: '', true);

        if (!is_array($payload) || !isset($payload['kind'], $payload['id'], $payload['sig'])) {
            return null;
        }

        $kind = (string) $payload['kind'];
        $id = (int) $payload['id'];
        $signature = (string) $payload['sig'];

        if ($id < 1 || !hash_equals($this->signature($kind, $id), $signature)) {
            return null;
        }

        if (!(new CoreReferenceValidator())->validate(new CoreReference($kind, (string) $id))) {
            return null;
        }

        return ['kind' => $kind, 'id' => $id];
    }

    public function organizationIdForContext(array $context): ?int
    {
        $active = $context['active_assignment'] ?? [];

        if (($active['scope_type'] ?? '') === 'organization' && (int) ($active['scope_id'] ?? 0) > 0) {
            return (int) $active['scope_id'];
        }

        $statement = $this->core()->query('SELECT id FROM organizations WHERE COALESCE(is_active, 1) = 1 ORDER BY id ASC LIMIT 1');
        $id = $statement->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    public function userPersonId(int $userId): ?int
    {
        $statement = $this->core()->prepare('SELECT person_id FROM users WHERE id = ? LIMIT 1');
        $statement->execute([$userId]);
        $id = $statement->fetchColumn();

        return $id === false || $id === null ? null : (int) $id;
    }

    private function users(): array
    {
        $statement = $this->core()->query("SELECT users.id, users.username, persons.full_name FROM users LEFT JOIN persons ON persons.id = users.person_id WHERE COALESCE(users.status, 'active') = 'active' ORDER BY users.id ASC LIMIT 80");

        return array_map(fn (array $row): array => [
            'token' => $this->token(CoreReferenceType::USER, (int) $row['id']),
            'label' => trim((string) ($row['full_name'] ?? '')) !== '' ? (string) $row['full_name'] : (string) ($row['username'] ?? 'کاربر'),
        ], $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function persons(): array
    {
        $statement = $this->core()->query("SELECT id, full_name FROM persons WHERE COALESCE(status, 'active') = 'active' ORDER BY id ASC LIMIT 80");

        return array_map(fn (array $row): array => [
            'token' => $this->token(CoreReferenceType::PERSON, (int) $row['id']),
            'label' => (string) ($row['full_name'] ?? 'شخص'),
        ], $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function organizations(): array
    {
        $statement = $this->core()->query('SELECT id, title FROM organizations WHERE COALESCE(is_active, 1) = 1 ORDER BY sort_order ASC, id ASC LIMIT 80');

        return array_map(fn (array $row): array => [
            'token' => $this->token(CoreReferenceType::ORGANIZATION, (int) $row['id']),
            'label' => (string) ($row['title'] ?? 'سازمان'),
        ], $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function orgUnits(): array
    {
        $statement = $this->core()->query("SELECT id, title FROM org_units WHERE COALESCE(status, 'active') = 'active' AND deleted_at IS NULL ORDER BY sort_order ASC, id ASC LIMIT 80");

        return array_map(fn (array $row): array => [
            'token' => $this->token(CoreReferenceType::ORG_UNIT, (int) $row['id']),
            'label' => (string) ($row['title'] ?? 'واحد سازمانی'),
        ], $statement->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function token(string $kind, int $id): string
    {
        $payload = json_encode(['kind' => $kind, 'id' => $id, 'sig' => $this->signature($kind, $id)], JSON_UNESCAPED_UNICODE) ?: '{}';

        return rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    }

    private function signature(string $kind, int $id): string
    {
        $secret = (string) Env::get('APP_KEY', 'ipkf-local-key');

        return hash_hmac('sha256', $kind . ':' . $id, $secret !== '' ? $secret : 'ipkf-local-key');
    }

    private function core(): PDO
    {
        return Database::connect();
    }
}
