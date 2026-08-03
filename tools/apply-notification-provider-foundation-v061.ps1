param(
    [string]$RepositoryRoot = "D:\Documents\GitHub\troca"
)

$ErrorActionPreference = "Stop"
Set-StrictMode -Version Latest

$ExpectedBranch = "v0.6.1-notification-provider-management-dev"
$Utf8NoBom = New-Object System.Text.UTF8Encoding($false)

function Run-Git {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]]$Arguments)

    & git @Arguments

    if ($LASTEXITCODE -ne 0) {
        throw "Git command failed: git $($Arguments -join ' ')"
    }
}

function Read-RepoFile {
    param([Parameter(Mandatory = $true)][string]$RelativePath)

    $Path = Join-Path $RepositoryRoot $RelativePath

    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "Required file not found: $RelativePath"
    }

    return [System.IO.File]::ReadAllText($Path)
}

function Write-RepoFile {
    param(
        [Parameter(Mandatory = $true)][string]$RelativePath,
        [Parameter(Mandatory = $true)][string]$Content
    )

    $Path = Join-Path $RepositoryRoot $RelativePath
    $Directory = Split-Path -Parent $Path

    if (-not (Test-Path -LiteralPath $Directory)) {
        [System.IO.Directory]::CreateDirectory($Directory) | Out-Null
    }

    $Normalized = $Content -replace "`r`n", "`n"

    if (-not $Normalized.EndsWith("`n")) {
        $Normalized += "`n"
    }

    [System.IO.File]::WriteAllText(
        $Path,
        $Normalized,
        $Utf8NoBom
    )
}

function Insert-AfterAnchor {
    param(
        [Parameter(Mandatory = $true)][string]$Content,
        [Parameter(Mandatory = $true)][string[]]$Anchors,
        [Parameter(Mandatory = $true)][string]$Insertion,
        [Parameter(Mandatory = $true)][string]$AlreadyPresent,
        [Parameter(Mandatory = $true)][string]$Label
    )

    if ($Content.Contains($AlreadyPresent)) {
        Write-Host "ALREADY OK: $Label" -ForegroundColor DarkYellow
        return $Content
    }

    foreach ($Anchor in $Anchors) {
        $Position = $Content.IndexOf(
            $Anchor,
            [System.StringComparison]::Ordinal
        )

        if ($Position -ge 0) {
            $InsertAt = $Position + $Anchor.Length

            Write-Host "UPDATED: $Label" -ForegroundColor Green

            return $Content.Substring(0, $InsertAt) +
                $Insertion +
                $Content.Substring($InsertAt)
        }
    }

    throw "Patch anchor missing: $Label"
}

Set-Location -LiteralPath $RepositoryRoot

$CurrentBranch = (& git branch --show-current).Trim()

if ($LASTEXITCODE -ne 0) {
    throw "Could not determine the current Git branch."
}

if ($CurrentBranch -ne $ExpectedBranch) {
    throw "Expected branch '$ExpectedBranch'; current branch is '$CurrentBranch'."
}

& git diff --quiet
$WorktreeClean = $LASTEXITCODE -eq 0

& git diff --cached --quiet
$IndexClean = $LASTEXITCODE -eq 0

if (-not $WorktreeClean -or -not $IndexClean) {
    throw "Tracked files are not clean. Commit or restore tracked changes first."
}

Write-Host ""
Write-Host "=== Notification Provider Foundation v0.6.1 ===" -ForegroundColor Cyan
Write-Host "branch=$CurrentBranch"
Write-Host "repository=$RepositoryRoot"

$MigrationPath = "public_html/system/Database/Migrations/ExtendNotificationProviderManagement.php"
$SecretServicePath = "public_html/app/Services/NotificationProviderSecretService.php"
$TestPath = "tests/NotificationProviderManagementFoundationTest.php"

$MigrationContent = @'
<?php

namespace IPKF\Database\Migrations;

class ExtendNotificationProviderManagement extends Migration
{
    public function up(): void
    {
        $this->extendProviderInstances();
        $this->extendProviderDefaults();
        $this->createSecretSets();
        $this->createProviderAuditEvents();
        $this->createWebhookEndpoints();
        $this->createWebhookEvents();
        $this->backfillProviderInstances();
        $this->backfillProviderDefaults();
        $this->addIndexes();
        $this->addForeignKeys();
    }

    public function down(): void
    {
    }

    private function extendProviderInstances(): void
    {
        if (!$this->tableExists('notification_provider_instances')) {
            return;
        }

        $columns = [
            'public_reference' => "
                ADD COLUMN public_reference
                VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL
                AFTER id
            ",
            'description' => "
                ADD COLUMN description VARCHAR(1000) NULL
                AFTER title
            ",
            'instance_kind' => "
                ADD COLUMN instance_kind
                VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'account'
                AFTER description
            ",
            'is_enabled' => "
                ADD COLUMN is_enabled TINYINT(1) NOT NULL DEFAULT 0
                AFTER status_code
            ",
            'configuration_version' => "
                ADD COLUMN configuration_version
                INT UNSIGNED NOT NULL DEFAULT 1
                AFTER configuration_json
            ",
            'health_status_code' => "
                ADD COLUMN health_status_code
                VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'unknown'
                AFTER balance_checked_at
            ",
            'last_tested_at' => "
                ADD COLUMN last_tested_at DATETIME NULL
                AFTER health_status_code
            ",
            'last_test_status_code' => "
                ADD COLUMN last_test_status_code
                VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin NULL
                AFTER last_tested_at
            ",
            'last_test_message' => "
                ADD COLUMN last_test_message VARCHAR(1000) NULL
                AFTER last_test_status_code
            ",
            'created_by_user_id' => "
                ADD COLUMN created_by_user_id BIGINT UNSIGNED NULL
                AFTER monthly_limit
            ",
            'updated_by_user_id' => "
                ADD COLUMN updated_by_user_id BIGINT UNSIGNED NULL
                AFTER created_by_user_id
            ",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'notification_provider_instances',
                $column
            )) {
                $this->db->exec(
                    "ALTER TABLE notification_provider_instances "
                    . $definition
                );
            }
        }
    }

    private function extendProviderDefaults(): void
    {
        if (!$this->tableExists('notification_provider_defaults')) {
            return;
        }

        $columns = [
            'purpose_code' => "
                ADD COLUMN purpose_code
                VARCHAR(60) CHARACTER SET ascii COLLATE ascii_bin
                NOT NULL DEFAULT 'general'
                AFTER channel_code
            ",
            'is_default' => "
                ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0
                AFTER priority
            ",
            'fallback_order' => "
                ADD COLUMN fallback_order INT UNSIGNED NOT NULL DEFAULT 0
                AFTER is_default
            ",
        ];

        foreach ($columns as $column => $definition) {
            if (!$this->columnExists(
                'notification_provider_defaults',
                $column
            )) {
                $this->db->exec(
                    "ALTER TABLE notification_provider_defaults "
                    . $definition
                );
            }
        }

        if ($this->indexExists(
            'notification_provider_defaults',
            'notification_provider_defaults_unique'
        )) {
            $this->db->exec("
                ALTER TABLE notification_provider_defaults
                DROP INDEX notification_provider_defaults_unique
            ");
        }

        if (!$this->indexExists(
            'notification_provider_defaults',
            'notification_provider_defaults_unique_v2'
        )) {
            $this->db->exec("
                ALTER TABLE notification_provider_defaults
                ADD UNIQUE KEY notification_provider_defaults_unique_v2 (
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code,
                    priority
                )
            ");
        }
    }

    private function createSecretSets(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_provider_secret_sets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider_instance_id BIGINT UNSIGNED NOT NULL,
                cipher_code
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                key_version INT UNSIGNED NOT NULL DEFAULT 1,
                encrypted_payload LONGTEXT NOT NULL,
                payload_checksum
                    CHAR(64) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                rotated_at DATETIME NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_provider_secret_instance_unique (
                    provider_instance_id
                ),
                INDEX notification_provider_secret_rotation_index (
                    rotated_at,
                    updated_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createProviderAuditEvents(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_provider_audit_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                provider_instance_id BIGINT UNSIGNED NULL,
                actor_user_id BIGINT UNSIGNED NULL,
                action_code
                    VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                change_summary_json LONGTEXT NULL,
                request_reference
                    VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NULL,
                occurred_at DATETIME NOT NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX notification_provider_audit_instance_time_index (
                    provider_instance_id,
                    occurred_at,
                    id
                ),
                INDEX notification_provider_audit_actor_time_index (
                    actor_user_id,
                    occurred_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createWebhookEndpoints(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_webhook_endpoints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                provider_instance_id BIGINT UNSIGNED NOT NULL,
                endpoint_token_hash
                    CHAR(64) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                status_code
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'inactive',
                delivery_mode
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'webhook',
                subscribed_events_json LONGTEXT NULL,
                last_registered_at DATETIME NULL,
                last_verified_at DATETIME NULL,
                last_received_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                updated_by_user_id BIGINT UNSIGNED NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_webhook_reference_unique (
                    public_reference
                ),
                UNIQUE KEY notification_webhook_instance_unique (
                    provider_instance_id
                ),
                INDEX notification_webhook_status_index (
                    status_code,
                    updated_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function createWebhookEvents(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS notification_webhook_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                public_reference
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                webhook_endpoint_id BIGINT UNSIGNED NOT NULL,
                external_event_id
                    VARCHAR(190) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                event_type
                    VARCHAR(100) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL,
                signature_status_code
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'unchecked',
                processing_status_code
                    VARCHAR(30) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL DEFAULT 'pending',
                headers_json LONGTEXT NULL,
                payload_json LONGTEXT NOT NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                received_at DATETIME NOT NULL,
                processed_at DATETIME NULL,
                last_error VARCHAR(2000) NULL,
                created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY notification_webhook_event_reference_unique (
                    public_reference
                ),
                UNIQUE KEY notification_webhook_external_event_unique (
                    webhook_endpoint_id,
                    external_event_id
                ),
                INDEX notification_webhook_processing_index (
                    processing_status_code,
                    received_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function backfillProviderInstances(): void
    {
        if (!$this->tableExists('notification_provider_instances')) {
            return;
        }

        $this->db->exec("
            UPDATE notification_provider_instances
            SET public_reference = CONCAT(
                    'npi_',
                    LOWER(LPAD(HEX(id), 24, '0'))
                )
            WHERE public_reference IS NULL
               OR public_reference = ''
        ");

        $this->db->exec("
            UPDATE notification_provider_instances
            SET is_enabled = CASE
                    WHEN status_code = 'active' THEN 1
                    ELSE 0
                END
        ");

        if ($this->columnExists(
            'notification_provider_instances',
            'public_reference'
        )) {
            $this->db->exec("
                ALTER TABLE notification_provider_instances
                MODIFY public_reference
                    VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin
                    NOT NULL
            ");
        }
    }

    private function backfillProviderDefaults(): void
    {
        if (
            !$this->tableExists('notification_provider_defaults')
            || !$this->columnExists(
                'notification_provider_defaults',
                'is_default'
            )
        ) {
            return;
        }

        $this->db->exec("
            UPDATE notification_provider_defaults
            SET is_default = 0
        ");

        $this->db->exec("
            UPDATE notification_provider_defaults AS defaults
            INNER JOIN (
                SELECT
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code,
                    MIN(priority) AS selected_priority
                FROM notification_provider_defaults
                WHERE is_active = 1
                GROUP BY
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code
            ) AS selected
              ON selected.scope_type = defaults.scope_type
             AND selected.scope_reference = defaults.scope_reference
             AND selected.channel_code = defaults.channel_code
             AND selected.purpose_code = defaults.purpose_code
             AND selected.selected_priority = defaults.priority
            SET defaults.is_default = 1
            WHERE defaults.is_active = 1
        ");
    }

    private function addIndexes(): void
    {
        if (
            $this->tableExists('notification_provider_instances')
            && !$this->indexExists(
                'notification_provider_instances',
                'notification_provider_instances_reference_unique'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_provider_instances
                ADD UNIQUE KEY
                    notification_provider_instances_reference_unique (
                        public_reference
                    )
            ");
        }

        if (
            $this->tableExists('notification_provider_instances')
            && !$this->indexExists(
                'notification_provider_instances',
                'notification_provider_instances_enabled_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_provider_instances
                ADD INDEX notification_provider_instances_enabled_index (
                    provider_type_id,
                    is_enabled,
                    priority,
                    id
                )
            ");
        }

        if (
            $this->tableExists('notification_provider_defaults')
            && !$this->indexExists(
                'notification_provider_defaults',
                'notification_provider_defaults_purpose_index'
            )
        ) {
            $this->db->exec("
                ALTER TABLE notification_provider_defaults
                ADD INDEX notification_provider_defaults_purpose_index (
                    scope_type,
                    scope_reference,
                    channel_code,
                    purpose_code,
                    is_active,
                    is_default,
                    priority,
                    id
                )
            ");
        }
    }

    private function addForeignKeys(): void
    {
        foreach ([
            [
                'notification_provider_secret_sets',
                'notification_provider_secret_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'CASCADE',
            ],
            [
                'notification_provider_audit_events',
                'notification_provider_audit_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'SET NULL',
            ],
            [
                'notification_webhook_endpoints',
                'notification_webhook_instance_fk',
                'provider_instance_id',
                'notification_provider_instances',
                'id',
                'CASCADE',
            ],
            [
                'notification_webhook_events',
                'notification_webhook_event_endpoint_fk',
                'webhook_endpoint_id',
                'notification_webhook_endpoints',
                'id',
                'CASCADE',
            ],
        ] as $foreignKey) {
            $this->addForeignKeyIfPossible(...$foreignKey);
        }
    }

    private function addForeignKeyIfPossible(
        string $table,
        string $constraint,
        string $column,
        string $referenceTable,
        string $referenceColumn,
        string $onDelete
    ): void {
        if (
            !$this->tableExists($table)
            || !$this->tableExists($referenceTable)
            || !$this->columnExists($table, $column)
            || !$this->columnExists($referenceTable, $referenceColumn)
            || $this->foreignKeyExists($table, $constraint)
            || !$this->supportsForeignKeys($table)
            || !$this->supportsForeignKeys($referenceTable)
            || $this->columnType($table, $column)
                !== $this->columnType(
                    $referenceTable,
                    $referenceColumn
                )
        ) {
            return;
        }

        $onDelete = in_array(
            $onDelete,
            ['CASCADE', 'SET NULL', 'RESTRICT'],
            true
        ) ? $onDelete : 'RESTRICT';

        $this->db->exec("
            ALTER TABLE {$table}
            ADD CONSTRAINT {$constraint}
            FOREIGN KEY ({$column})
            REFERENCES {$referenceTable} ({$referenceColumn})
            ON UPDATE CASCADE
            ON DELETE {$onDelete}
        ");
    }

    private function tableExists(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
        ");
        $statement->execute([$table]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
        ");
        $statement->execute([$table, $column]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.statistics
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND index_name = ?
        ");
        $statement->execute([$table, $index]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function foreignKeyExists(
        string $table,
        string $constraint
    ): bool {
        $statement = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.table_constraints
            WHERE constraint_schema = DATABASE()
              AND table_name = ?
              AND constraint_name = ?
              AND constraint_type = 'FOREIGN KEY'
        ");
        $statement->execute([$table, $constraint]);

        return (int) $statement->fetchColumn() > 0;
    }

    private function columnType(
        string $table,
        string $column
    ): string {
        $statement = $this->db->prepare("
            SELECT COLUMN_TYPE
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = ?
              AND column_name = ?
            LIMIT 1
        ");
        $statement->execute([$table, $column]);

        return strtolower(trim(
            (string) $statement->fetchColumn()
        ));
    }

    private function supportsForeignKeys(string $table): bool
    {
        $statement = $this->db->prepare("
            SELECT ENGINE
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name = ?
            LIMIT 1
        ");
        $statement->execute([$table]);

        return strtolower(trim(
            (string) $statement->fetchColumn()
        )) === 'innodb';
    }
}
'@

$SecretServiceContent = @'
<?php

namespace App\Services;

use IPKF\Support\Env;
use RuntimeException;

class NotificationProviderSecretService extends BaseService
{
    private const KEY_VERSION = 1;

    public function encrypt(array $secrets): array
    {
        $normalized = $this->normalize($secrets);
        $plain = json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
        $key = $this->key();

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(
                SODIUM_CRYPTO_SECRETBOX_NONCEBYTES
            );
            $ciphertext = sodium_crypto_secretbox(
                $plain,
                $nonce,
                $key
            );

            $envelope = [
                'version' => 1,
                'cipher' => 'sodium_secretbox',
                'nonce' => base64_encode($nonce),
                'ciphertext' => base64_encode($ciphertext),
            ];
            $cipherCode = 'sodium_secretbox';
        } elseif (function_exists('openssl_encrypt')) {
            $nonce = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt(
                $plain,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                '',
                16
            );

            if ($ciphertext === false) {
                throw new RuntimeException(
                    'notification_secret_encryption_failed'
                );
            }

            $envelope = [
                'version' => 1,
                'cipher' => 'aes_256_gcm',
                'nonce' => base64_encode($nonce),
                'tag' => base64_encode($tag),
                'ciphertext' => base64_encode($ciphertext),
            ];
            $cipherCode = 'aes_256_gcm';
        } else {
            throw new RuntimeException(
                'notification_secret_cipher_unavailable'
            );
        }

        return [
            'cipher_code' => $cipherCode,
            'key_version' => self::KEY_VERSION,
            'encrypted_payload' => base64_encode(
                json_encode(
                    $envelope,
                    JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                )
            ),
            'payload_checksum' => hash('sha256', $plain),
        ];
    }

    public function decrypt(string $encryptedPayload): array
    {
        $encodedEnvelope = base64_decode(
            trim($encryptedPayload),
            true
        );

        if ($encodedEnvelope === false) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        $envelope = json_decode(
            $encodedEnvelope,
            true,
            32,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($envelope)) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        $cipher = (string) ($envelope['cipher'] ?? '');
        $nonce = base64_decode(
            (string) ($envelope['nonce'] ?? ''),
            true
        );
        $ciphertext = base64_decode(
            (string) ($envelope['ciphertext'] ?? ''),
            true
        );

        if ($nonce === false || $ciphertext === false) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        $key = $this->key();

        if (
            $cipher === 'sodium_secretbox'
            && function_exists('sodium_crypto_secretbox_open')
        ) {
            $plain = sodium_crypto_secretbox_open(
                $ciphertext,
                $nonce,
                $key
            );
        } elseif (
            $cipher === 'aes_256_gcm'
            && function_exists('openssl_decrypt')
        ) {
            $tag = base64_decode(
                (string) ($envelope['tag'] ?? ''),
                true
            );

            if ($tag === false) {
                throw new RuntimeException(
                    'notification_secret_payload_invalid'
                );
            }

            $plain = openssl_decrypt(
                $ciphertext,
                'aes-256-gcm',
                $key,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag
            );
        } else {
            throw new RuntimeException(
                'notification_secret_cipher_unsupported'
            );
        }

        if (!is_string($plain)) {
            throw new RuntimeException(
                'notification_secret_decryption_failed'
            );
        }

        $secrets = json_decode(
            $plain,
            true,
            64,
            JSON_THROW_ON_ERROR
        );

        if (!is_array($secrets)) {
            throw new RuntimeException(
                'notification_secret_payload_invalid'
            );
        }

        return $secrets;
    }

    public function mask(array $secrets): array
    {
        $masked = [];

        foreach ($secrets as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $text = trim((string) $value);

            if ($text === '') {
                $masked[$key] = '';
                continue;
            }

            $length = mb_strlen($text, 'UTF-8');

            if ($length <= 4) {
                $masked[$key] = str_repeat('•', $length);
                continue;
            }

            $masked[$key] =
                mb_substr($text, 0, 2, 'UTF-8')
                . str_repeat('•', max(4, $length - 4))
                . mb_substr($text, -2, null, 'UTF-8');
        }

        return $masked;
    }

    private function normalize(array $secrets): array
    {
        $normalized = [];

        foreach ($secrets as $key => $value) {
            $key = strtolower(trim((string) $key));

            if (
                $key === ''
                || preg_match(
                    '/^[a-z][a-z0-9_]{0,79}$/',
                    $key
                ) !== 1
                || is_array($value)
                || is_object($value)
                || is_resource($value)
            ) {
                continue;
            }

            $normalized[$key] = trim(
                (string) ($value ?? '')
            );
        }

        ksort($normalized);

        return $normalized;
    }

    private function key(): string
    {
        $masterKey = trim(
            (string) Env::get(
                'NOTIFICATION_SECRET_KEY',
                ''
            )
        );

        if ($masterKey === '') {
            $masterKey = trim(
                (string) Env::get('APP_KEY', '')
            );
        }

        if ($masterKey === '') {
            throw new RuntimeException(
                'notification_secret_key_missing'
            );
        }

        if (str_starts_with($masterKey, 'base64:')) {
            $decoded = base64_decode(
                substr($masterKey, 7),
                true
            );

            if ($decoded === false || $decoded === '') {
                throw new RuntimeException(
                    'notification_secret_key_invalid'
                );
            }

            $masterKey = $decoded;
        }

        return hash(
            'sha256',
            "ipkf:notification-provider:v1\0"
            . $masterKey,
            true
        );
    }
}
'@

$TestContent = @'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (string $path) use ($root): string {
    $content = file_get_contents($root . '/' . $path);

    if (!is_string($content)) {
        throw new RuntimeException(
            "Cannot read required file: {$path}"
        );
    }

    return $content;
};

$expect = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
};

$migration = $read(
    'public_html/system/Database/Migrations/'
    . 'ExtendNotificationProviderManagement.php'
);
$registry = $read(
    'public_html/system/Database/Application/'
    . 'ApplicationMigrationRegistry.php'
);
$migrateEndpoint = $read(
    'public_html/public/migrate.php'
);
$secretService = $read(
    'public_html/app/Services/'
    . 'NotificationProviderSecretService.php'
);
$seeder = $read(
    'public_html/system/Database/Seeds/'
    . 'CommunicationCenterSeeder.php'
);
$settingsService = $read(
    'public_html/app/Services/'
    . 'CommunicationSettingsService.php'
);

foreach ([
    'notification_provider_secret_sets',
    'notification_provider_audit_events',
    'notification_webhook_endpoints',
    'notification_webhook_events',
] as $table) {
    $expect(
        str_contains($migration, $table),
        "Missing provider-management table: {$table}"
    );
}

foreach ([
    'public_reference',
    'instance_kind',
    'is_enabled',
    'health_status_code',
    'purpose_code',
    'is_default',
    'fallback_order',
] as $column) {
    $expect(
        str_contains($migration, $column),
        "Missing provider-management column: {$column}"
    );
}

$expect(
    str_contains(
        $registry,
        'ExtendNotificationProviderManagement::class'
    ),
    'Provider-management migration is not registered.'
);

$expect(
    str_contains(
        $migrateEndpoint,
        'new \IPKF\Database\Migrations\\'
        . 'ExtendNotificationProviderManagement()'
    ),
    'Provider-management migration is missing from migrate.php.'
);

$expect(
    str_contains($secretService, 'sodium_crypto_secretbox')
    && str_contains($secretService, 'aes-256-gcm')
    && str_contains(
        $secretService,
        "Env::get("
        . "'NOTIFICATION_SECRET_KEY'"
    )
    && str_contains($secretService, "Env::get('APP_KEY'")
    && !preg_match(
        '/\b(?:password|api_key|bot_token|access_token)\s*=>\s*[\'"][^\'"]+/i',
        $secretService
    ),
    'Provider secrets must use authenticated encryption.'
);

foreach ([
    "'smtp'",
    "'kavenegar'",
    "'bale_bot'",
    "'eitaa_bot'",
    "'telegram_bot'",
    "'whatsapp_cloud'",
] as $provider) {
    $expect(
        str_contains($seeder, $provider),
        "Missing provider catalog entry: {$provider}"
    );
}

$expect(
    str_contains($settingsService, "'providers'")
    && str_contains($settingsService, "'defaults'"),
    'Provider registration and default selection must remain separate sections.'
);

$expect(
    !preg_match(
        '/\b(?:DROP\s+TABLE|TRUNCATE\s+TABLE)\b/i',
        $migration
    ),
    'Destructive SQL is present.'
);

echo "Notification provider management foundation checks passed.\n";
'@

Write-RepoFile $MigrationPath $MigrationContent
Write-RepoFile $SecretServicePath $SecretServiceContent
Write-RepoFile $TestPath $TestContent

$RegistryPath = "public_html/system/Database/Application/ApplicationMigrationRegistry.php"
$Registry = Read-RepoFile $RegistryPath
$Registry = Insert-AfterAnchor `
    -Content $Registry `
    -Anchors @(
        "                    \IPKF\Database\Migrations\CreateSecureMessageExtensionTables::class,",
        "                    \IPKF\Database\Migrations\CreateCommunicationCenterFoundationTables::class,"
    ) `
    -Insertion "`n                    \IPKF\Database\Migrations\ExtendNotificationProviderManagement::class," `
    -AlreadyPresent "\IPKF\Database\Migrations\ExtendNotificationProviderManagement::class," `
    -Label "application migration registry"
Write-RepoFile $RegistryPath $Registry

$MigratePath = "public_html/public/migrate.php"
$Migrate = Read-RepoFile $MigratePath
$Migrate = Insert-AfterAnchor `
    -Content $Migrate `
    -Anchors @(
        "        new \IPKF\Database\Migrations\CreateSecureMessageExtensionTables(),",
        "        new \IPKF\Database\Migrations\CreateCommunicationCenterFoundationTables(),"
    ) `
    -Insertion "`n        new \IPKF\Database\Migrations\ExtendNotificationProviderManagement()," `
    -AlreadyPresent "new \IPKF\Database\Migrations\ExtendNotificationProviderManagement()," `
    -Label "main migration endpoint"
Write-RepoFile $MigratePath $Migrate

$Files = @(
    $MigrationPath,
    $SecretServicePath,
    $RegistryPath,
    $MigratePath,
    $TestPath
)

Write-Host ""
Write-Host "=== Stage Foundation Files ===" -ForegroundColor Cyan

foreach ($File in $Files) {
    Run-Git add -- $File
}

Write-Host ""
Write-Host "=== Cached Validation ===" -ForegroundColor Cyan

Run-Git diff --cached --check

Write-Host ""
Write-Host "=== Cached Summary ===" -ForegroundColor Cyan

Run-Git diff --cached --stat

Write-Host ""
Write-Host "=== Final Status ===" -ForegroundColor Cyan

Run-Git status --short --branch

Write-Host ""
Write-Host "FOUNDATION FILES CREATED AND STAGED" -ForegroundColor Green
Write-Host "No commit was created."
