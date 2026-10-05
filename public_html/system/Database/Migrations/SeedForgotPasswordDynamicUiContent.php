<?php

declare(strict_types=1);

namespace IPKF\Database\Migrations;

use PDO;
use RuntimeException;
use Throwable;

final class SeedForgotPasswordDynamicUiContent extends Migration
{
    private const SEED =
        'c4-a5-r3-m2e-c3-b2-r1';

    private const MODULE =
        'core';

    private const SURFACE =
        'forgot-password';

    private const SOURCE_FILE =
        'public_html/resources/views/admin/forgot-password.php';

    private const ITEMS_JSON ='[{"key":"core.forgot-password.ui.page-title","role":"page_title","body":"بازیابی کلمه عبور"},{"key":"core.forgot-password.error.password-confirmation","role":"error","body":"تکرار رمز عبور با رمز جدید یکسان نیست."},{"key":"core.forgot-password.error.password-policy","role":"error","body":"رمز عبور باید بین ۸ تا ۱۲۸ کاراکتر و شامل حداقل یک حرف و یک عدد باشد."},{"key":"core.forgot-password.error.password-identity","role":"error","body":"رمز عبور نباید شامل نام کاربری یا بخش اصلی ایمیل باشد."},{"key":"core.forgot-password.error.same-password","role":"error","body":"رمز عبور جدید نباید با رمز عبور فعلی یکسان باشد."},{"key":"core.forgot-password.error.invalid-or-expired-code","role":"error","body":"کد تأیید معتبر نیست یا اعتبار آن پایان یافته است."},{"key":"core.forgot-password.error.reset-failed","role":"error","body":"بازیابی رمز عبور انجام نشد. کد جدید درخواست کنید."},{"key":"core.forgot-password.ui.kicker","role":"kicker","body":"بازیابی دسترسی"},{"key":"core.forgot-password.ui.heading","role":"heading","body":"بازیابی کلمه عبور"},{"key":"core.forgot-password.ui.identifier-label","role":"field_label","body":"ایمیل، موبایل یا نام کاربری"},{"key":"core.forgot-password.ui.request-code-action","role":"action_label","body":"ارسال کد بازیابی"},{"key":"core.forgot-password.ui.code-label","role":"field_label","body":"کد تأیید"},{"key":"core.forgot-password.ui.new-password-label","role":"field_label","body":"رمز عبور جدید"},{"key":"core.forgot-password.ui.password-confirmation-label","role":"field_label","body":"تکرار رمز عبور جدید"},{"key":"core.forgot-password.ui.submit-password-action","role":"action_label","body":"ثبت رمز عبور جدید"},{"key":"core.forgot-password.ui.request-new-code-action","role":"action_label","body":"درخواست کد جدید"},{"key":"core.forgot-password.ui.login-link","role":"link_label","body":"بازگشت به ورود"},{"key":"core.forgot-password.ui.home-link","role":"link_label","body":"صفحه اصلی"}]';

    public function up(): void
    {
        $items =
            json_decode(
                self::ITEMS_JSON,
                true,
                512,
                JSON_THROW_ON_ERROR
            );

        if (
            !is_array($items)
            || count($items) !== 18
        ) {
            throw new RuntimeException(
                'forgot_password_dynamic_ui_payload_invalid'
            );
        }

        $this->db->beginTransaction();

        try {
            foreach ($items as $item) {
                $definitionId =
                    $this->ensureDefinition(
                        $item
                    );

                $this->ensureOverride(
                    $definitionId,
                    $item
                );
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
         * Intentionally non-destructive.
         * Managed UI content may be edited after deployment.
         */
    }

    private function ensureDefinition(
        array $item
    ): int {
        $key =
            (string) (
                $item['key']
                ?? ''
            );

        if ($key === '') {
            throw new RuntimeException(
                'forgot_password_dynamic_ui_key_empty'
            );
        }

        $query =
            $this->db->prepare(
                'SELECT
                    id,
                    content_type,
                    metadata_json
                 FROM ui_content_definitions
                 WHERE content_key = ?
                 LIMIT 1
                 FOR UPDATE'
            );

        $query->execute([
            $key,
        ]);

        $existing =
            $query->fetch(
                PDO::FETCH_ASSOC
            );

        if (is_array($existing)) {
            $metadata =
                $this->metadata(
                    $existing['metadata_json']
                    ?? null
                );

            if (
                (string) (
                    $existing['content_type']
                    ?? ''
                ) !== 'guide'
            ) {
                throw new RuntimeException(
                    'forgot_password_definition_type_collision:'
                    . $key
                );
            }

            if (
                ($metadata['seed'] ?? null)
                !== self::SEED
            ) {
                throw new RuntimeException(
                    'forgot_password_definition_ownership_collision:'
                    . $key
                );
            }

            return
                (int) $existing['id'];
        }

        $reference =
            'UICD-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'definition|'
                        . $key
                    ),
                    0,
                    24
                )
            );

        $metadata =
            $this->metadataFor(
                $item
            );

        $insert =
            $this->db->prepare(
                "INSERT INTO ui_content_definitions
                 (
                    public_reference,
                    content_key,
                    content_type,
                    default_locale,
                    http_status,
                    description,
                    metadata_json,
                    is_active
                 )
                 VALUES (
                    ?,
                    ?,
                    'guide',
                    'fa',
                    NULL,
                    ?,
                    ?,
                    1
                 )"
            );

        $insert->execute([
            $reference,
            $key,
            'Password recovery UI: '
                . (string) (
                    $item['role']
                    ?? 'text'
                ),
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);

        $id =
            (int) $this->db
                ->lastInsertId();

        if ($id < 1) {
            $query->execute([
                $key,
            ]);

            $id =
                (int) $query
                    ->fetchColumn();
        }

        if ($id < 1) {
            throw new RuntimeException(
                'forgot_password_definition_create_failed:'
                . $key
            );
        }

        return $id;
    }

    private function ensureOverride(
        int $definitionId,
        array $item
    ): void {
        $key =
            (string) $item['key'];

        $body =
            (string) (
                $item['body']
                ?? ''
            );

        if ($body === '') {
            throw new RuntimeException(
                'forgot_password_dynamic_ui_body_empty:'
                . $key
            );
        }

        $scopeKey =
            'scope:'
            . self::MODULE
            . ':surface:'
            . self::SURFACE;

        $query =
            $this->db->prepare(
                "SELECT
                    id,
                    metadata_json
                 FROM ui_content_overrides
                 WHERE definition_id = ?
                   AND scope_key = ?
                   AND locale = 'fa'
                 LIMIT 1
                 FOR UPDATE"
            );

        $query->execute([
            $definitionId,
            $scopeKey,
        ]);

        $existing =
            $query->fetch(
                PDO::FETCH_ASSOC
            );

        if (is_array($existing)) {
            $metadata =
                $this->metadata(
                    $existing['metadata_json']
                    ?? null
                );

            if (
                ($metadata['seed'] ?? null)
                !== self::SEED
            ) {
                throw new RuntimeException(
                    'forgot_password_override_ownership_collision:'
                    . $key
                );
            }

            return;
        }

        $reference =
            'UICO-'
            . strtoupper(
                substr(
                    hash(
                        'sha256',
                        'override|'
                        . $key
                        . '|'
                        . $scopeKey
                        . '|fa'
                    ),
                    0,
                    24
                )
            );

        $scopePath = [
            [
                'type' =>
                    'surface',
                'reference' =>
                    self::SURFACE,
            ],
        ];

        $metadata =
            $this->metadataFor(
                $item
            );

        $insert =
            $this->db->prepare(
                "INSERT INTO ui_content_overrides
                 (
                    public_reference,
                    definition_id,
                    scope_type,
                    scope_key,
                    module_key,
                    scope_reference,
                    scope_path_json,
                    locale,
                    title,
                    body,
                    icon_code,
                    severity_code,
                    layout_variant,
                    visibility_mode,
                    primary_action_code,
                    primary_action_label,
                    secondary_action_code,
                    secondary_action_label,
                    metadata_json,
                    is_active
                 )
                 VALUES (
                    ?,
                    ?,
                    'fine',
                    ?,
                    ?,
                    ?,
                    ?,
                    'fa',
                    ?,
                    ?,
                    NULL,
                    NULL,
                    NULL,
                    'show',
                    NULL,
                    NULL,
                    NULL,
                    NULL,
                    ?,
                    1
                 )"
            );

        $insert->execute([
            $reference,
            $definitionId,
            $scopeKey,
            self::MODULE,
            self::SURFACE,
            json_encode(
                $scopePath,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
            $body,
            $body,
            json_encode(
                $metadata,
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_THROW_ON_ERROR
            ),
        ]);
    }

    private function metadataFor(
        array $item
    ): array {
        return [
            'seed' =>
                self::SEED,
            'module' =>
                self::MODULE,
            'surface' =>
                self::SURFACE,
            'source_file' =>
                self::SOURCE_FILE,
            'consumer_bound' =>
                true,
            'render_mode' =>
                'body',
            'ui_role' =>
                (string) (
                    $item['role']
                    ?? 'text'
                ),
        ];
    }

    private function metadata(
        mixed $value
    ): array {
        $decoded =
            json_decode(
                (string) $value,
                true
            );

        return
            is_array($decoded)
                ? $decoded
                : [];
    }
}
