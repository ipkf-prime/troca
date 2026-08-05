#!/usr/bin/env bash
set -euo pipefail

repo_root="${1:-/d/Documents/GitHub/troca}"
expected_branch="v0.6.1-notification-provider-management-dev"
expected_head="133478e"

cd "$repo_root"

current_branch="$(git branch --show-current)"
current_head="$(git rev-parse --short HEAD)"

if [[ "$current_branch" != "$expected_branch" ]]; then
    printf 'Expected branch %s; current branch is %s.\n' \
        "$expected_branch" "$current_branch" >&2
    exit 1
fi

if [[ "$current_head" != "$expected_head" ]]; then
    printf 'Expected HEAD %s; current HEAD is %s.\n' \
        "$expected_head" "$current_head" >&2
    exit 1
fi

if ! git diff --quiet || ! git diff --cached --quiet; then
    echo "Working tree or index is not clean. Patch stopped." >&2
    git status --short --branch >&2
    exit 1
fi

media_repository_file="public_html/app/Repositories/NotificationMediaRepository.php"
media_upload_file="public_html/app/Services/NotificationMediaUploadService.php"
send_service_file="public_html/app/Services/NotificationSendCenterService.php"
gateway_service_file="public_html/app/Services/NotificationGatewayService.php"
gateway_repository_file="public_html/app/Repositories/NotificationGatewayRepository.php"
smtp_adapter_file="public_html/app/Services/NotificationSmtpGatewayAdapter.php"
smtp_transport_file="public_html/app/Services/NotificationSmtpTransport.php"
bale_adapter_file="public_html/app/Services/NotificationBaleGatewayAdapter.php"
http_transport_file="public_html/app/Services/NotificationProviderHttpTransport.php"
routes_file="public_html/routes/communication-center.php"
view_file="public_html/resources/views/admin/communication-settings.php"
style_file="public_html/resources/views/admin/partials/communication-style.php"
test_file="tests/NotificationMultimediaDeliveryCoreTest.php"
tool_file="tools/apply-notification-multimedia-delivery-core-v061-r2.sh"

required_files=(
  "$send_service_file"
  "$gateway_service_file"
  "$gateway_repository_file"
  "$smtp_adapter_file"
  "$smtp_transport_file"
  "$bale_adapter_file"
  "$http_transport_file"
  "$routes_file"
  "$view_file"
  "$style_file"
)

for file in "${required_files[@]}"; do
    if [[ ! -f "$file" ]]; then
        printf 'Required file not found: %s\n' "$file" >&2
        exit 1
    fi
done

cleanup_on_error() {
    status=$?

    if [[ "$status" -ne 0 ]]; then
        echo
        echo "PATCH FAILED; RESTORING CLEAN TREE" >&2

        git restore --staged --worktree -- \
          "$send_service_file" \
          "$gateway_service_file" \
          "$gateway_repository_file" \
          "$smtp_adapter_file" \
          "$smtp_transport_file" \
          "$bale_adapter_file" \
          "$http_transport_file" \
          "$routes_file" \
          "$view_file" \
          "$style_file" \
          >/dev/null 2>&1 || true

        rm -f -- \
          "$media_repository_file" \
          "$media_upload_file" \
          "$test_file" \
          "$tool_file"
    fi

    exit "$status"
}

trap cleanup_on_error EXIT

echo
echo "=== Add Secure Notification Media Storage ==="

cat > "$media_repository_file" <<'PHP'
<?php

namespace App\Repositories;

use RuntimeException;

class NotificationMediaRepository extends BaseRepository
{
    public function tableReady(): bool
    {
        $statement = $this->connection()->prepare("
            SELECT COUNT(*)
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
              AND table_name IN (
                'notification_media_assets',
                'notification_media_links'
              )
        ");
        $statement->execute();

        return (int) $statement->fetchColumn() === 2;
    }

    public function create(
        int $actorUserId,
        array $asset
    ): array {
        if (!$this->tableReady()) {
            throw new RuntimeException(
                'notification_send_media_storage_unavailable'
            );
        }

        $reference =
            'nma_' . bin2hex(random_bytes(12));
        $statement = $this->connection()->prepare("
            INSERT INTO notification_media_assets (
                public_reference,
                actor_user_id,
                original_name,
                stored_name,
                storage_path,
                mime_type,
                extension,
                media_kind,
                size_bytes,
                checksum_sha256,
                status_code,
                created_at,
                updated_at
            )
            VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
                'active',
                CURRENT_TIMESTAMP,
                CURRENT_TIMESTAMP
            )
        ");
        $statement->execute([
            $reference,
            $actorUserId,
            (string) $asset['original_name'],
            (string) $asset['stored_name'],
            (string) $asset['storage_path'],
            (string) $asset['mime_type'],
            (string) $asset['extension'],
            (string) $asset['media_kind'],
            (int) $asset['size_bytes'],
            (string) $asset['checksum_sha256'],
        ]);

        return $asset + [
            'id' => (int) $this->connection()
                ->lastInsertId(),
            'public_reference' => $reference,
        ];
    }

    public function remove(array $assetIds): void
    {
        $assetIds = array_values(array_unique(
            array_filter(
                array_map('intval', $assetIds),
                static fn (int $id): bool => $id > 0
            )
        ));

        if ($assetIds === []) {
            return;
        }

        $placeholders = implode(
            ',',
            array_fill(0, count($assetIds), '?')
        );
        $statement = $this->connection()->prepare("
            DELETE FROM notification_media_assets
            WHERE id IN ({$placeholders})
              AND NOT EXISTS (
                SELECT 1
                FROM notification_media_links
                WHERE notification_media_links.asset_id =
                    notification_media_assets.id
              )
        ");
        $statement->execute($assetIds);
    }
}
PHP

cat > "$media_upload_file" <<'PHP'
<?php

namespace App\Services;

use App\Repositories\NotificationMediaRepository;
use finfo;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

class NotificationMediaUploadService extends BaseService
{
    public const MAX_FILES = 5;
    public const MAX_FILE_BYTES = 10485760;
    public const MAX_TOTAL_BYTES = 31457280;

    private const TYPES = [
        'jpg' => ['image', ['image/jpeg']],
        'jpeg' => ['image', ['image/jpeg']],
        'png' => ['image', ['image/png']],
        'webp' => ['image', ['image/webp']],
        'mp4' => ['video', ['video/mp4']],
        'mp3' => ['audio', ['audio/mpeg', 'audio/mp3']],
        'm4a' => [
            'audio',
            ['audio/mp4', 'audio/x-m4a', 'video/mp4'],
        ],
        'ogg' => [
            'audio',
            ['audio/ogg', 'application/ogg'],
        ],
        'pdf' => ['document', ['application/pdf']],
        'docx' => [
            'document',
            [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        'xlsx' => [
            'document',
            [
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'application/zip',
                'application/octet-stream',
            ],
        ],
        'txt' => ['document', ['text/plain']],
    ];

    public function __construct(
        private ?NotificationMediaRepository $repository = null
    ) {
        $this->repository ??=
            new NotificationMediaRepository();
    }

    public function store(
        int $actorUserId,
        array $uploadSet
    ): array {
        $uploads = $this->normalize($uploadSet);

        if ($uploads === []) {
            throw new InvalidArgumentException(
                'notification_send_media_required'
            );
        }

        if (count($uploads) > self::MAX_FILES) {
            throw new InvalidArgumentException(
                'notification_send_media_count_exceeded'
            );
        }

        $total = 0;

        foreach ($uploads as $upload) {
            $error = (int) $upload['error'];

            if ($error !== UPLOAD_ERR_OK) {
                throw new InvalidArgumentException(
                    in_array(
                        $error,
                        [
                            UPLOAD_ERR_INI_SIZE,
                            UPLOAD_ERR_FORM_SIZE,
                        ],
                        true
                    )
                        ? 'notification_send_media_file_size_exceeded'
                        : 'notification_send_media_upload_failed'
                );
            }

            $size = (int) $upload['size'];

            if (
                $size < 1
                || $size > self::MAX_FILE_BYTES
            ) {
                throw new InvalidArgumentException(
                    'notification_send_media_file_size_exceeded'
                );
            }

            $total += $size;
        }

        if ($total > self::MAX_TOTAL_BYTES) {
            throw new InvalidArgumentException(
                'notification_send_media_total_size_exceeded'
            );
        }

        $stored = [];

        try {
            foreach ($uploads as $upload) {
                $stored[] = $this->storeOne(
                    $actorUserId,
                    $upload
                );
            }

            return $stored;
        } catch (Throwable $exception) {
            $this->cleanup($stored);
            throw $exception;
        }
    }

    public function cleanup(array $assets): void
    {
        foreach ($assets as $asset) {
            $path = (string) (
                $asset['storage_path'] ?? ''
            );

            if ($path !== '' && is_file($path)) {
                @unlink($path);
            }
        }

        $this->repository->remove(array_map(
            static fn (array $asset): int =>
                (int) ($asset['id'] ?? 0),
            $assets
        ));
    }

    private function storeOne(
        int $actorUserId,
        array $upload
    ): array {
        $temporaryPath = (string) $upload['tmp_name'];

        if (
            $temporaryPath === ''
            || !is_uploaded_file($temporaryPath)
        ) {
            throw new InvalidArgumentException(
                'notification_send_media_upload_invalid'
            );
        }

        $originalName = $this->safeName(
            (string) $upload['name']
        );
        $extension = strtolower(pathinfo(
            $originalName,
            PATHINFO_EXTENSION
        ));
        $definition = self::TYPES[$extension] ?? null;

        if (!is_array($definition)) {
            throw new InvalidArgumentException(
                'notification_send_media_type_invalid'
            );
        }

        $mime = $this->mime($temporaryPath);

        if (!in_array(
            $mime,
            $definition[1],
            true
        )) {
            throw new InvalidArgumentException(
                'notification_send_media_type_invalid'
            );
        }

        if (
            in_array($extension, ['docx', 'xlsx'], true)
            && !$this->validOfficeArchive(
                $temporaryPath,
                $extension,
                $mime
            )
        ) {
            throw new InvalidArgumentException(
                'notification_send_media_type_invalid'
            );
        }

        $directory = $this->directory();
        $storedName = bin2hex(random_bytes(20))
            . '.' . $extension;
        $path = $directory
            . DIRECTORY_SEPARATOR
            . $storedName;

        if (!move_uploaded_file(
            $temporaryPath,
            $path
        )) {
            throw new RuntimeException(
                'notification_send_media_storage_failed'
            );
        }

        @chmod($path, 0600);

        try {
            $checksum = hash_file('sha256', $path);

            if (!is_string($checksum) || $checksum === '') {
                throw new RuntimeException(
                    'notification_send_media_storage_failed'
                );
            }

            return $this->repository->create(
                $actorUserId,
                [
                    'original_name' => $originalName,
                    'stored_name' => $storedName,
                    'storage_path' => $path,
                    'mime_type' => $mime,
                    'extension' => $extension,
                    'media_kind' =>
                        (string) $definition[0],
                    'size_bytes' => (int) filesize($path),
                    'checksum_sha256' => $checksum,
                ]
            );
        } catch (Throwable $exception) {
            @unlink($path);
            throw $exception;
        }
    }

    private function normalize(array $files): array
    {
        $names = $files['name'] ?? [];

        if (!is_array($names)) {
            $names = [$names];
        }

        $result = [];

        foreach ($names as $index => $name) {
            $error = $this->at(
                $files['error'] ?? [],
                $index,
                UPLOAD_ERR_NO_FILE
            );

            if ((int) $error === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $result[] = [
                'name' => (string) $name,
                'tmp_name' => (string) $this->at(
                    $files['tmp_name'] ?? [],
                    $index,
                    ''
                ),
                'error' => (int) $error,
                'size' => (int) $this->at(
                    $files['size'] ?? [],
                    $index,
                    0
                ),
            ];
        }

        return $result;
    }

    private function at(
        mixed $value,
        int|string $index,
        mixed $default
    ): mixed {
        if (is_array($value)) {
            return $value[$index] ?? $default;
        }

        return (int) $index === 0
            ? $value
            : $default;
    }

    private function mime(string $path): string
    {
        $mime = '';

        if (class_exists(finfo::class)) {
            $detector = new finfo(FILEINFO_MIME_TYPE);
            $value = $detector->file($path);
            $mime = is_string($value)
                ? strtolower(trim($value))
                : '';
        }

        if (
            $mime === ''
            && function_exists('mime_content_type')
        ) {
            $value = mime_content_type($path);
            $mime = is_string($value)
                ? strtolower(trim($value))
                : '';
        }

        if ($mime === '') {
            throw new RuntimeException(
                'notification_send_media_type_detection_failed'
            );
        }

        return $mime;
    }

    private function validOfficeArchive(
        string $path,
        string $extension,
        string $mime
    ): bool {
        $official = [
            'docx' =>
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ];

        if (
            $mime === ($official[$extension] ?? '')
            && !class_exists(ZipArchive::class)
        ) {
            return true;
        }

        if (!class_exists(ZipArchive::class)) {
            return false;
        }

        $archive = new ZipArchive();

        if ($archive->open($path) !== true) {
            return false;
        }

        try {
            $required = $extension === 'docx'
                ? 'word/document.xml'
                : 'xl/workbook.xml';

            return $archive->locateName(
                '[Content_Types].xml'
            ) !== false
                && $archive->locateName($required)
                    !== false;
        } finally {
            $archive->close();
        }
    }

    private function directory(): string
    {
        $root = trim((string) getenv(
            'NOTIFICATION_MEDIA_STORAGE_PATH'
        ));

        if ($root === '') {
            $applicationRoot = dirname(__DIR__, 2);
            $root = dirname($applicationRoot)
                . DIRECTORY_SEPARATOR . 'storage'
                . DIRECTORY_SEPARATOR . 'ipkf'
                . DIRECTORY_SEPARATOR
                . 'notification-media';
        }

        $directory = rtrim(
            $root,
            DIRECTORY_SEPARATOR
        )
            . DIRECTORY_SEPARATOR . date('Y')
            . DIRECTORY_SEPARATOR . date('m');

        if (
            !is_dir($directory)
            && !mkdir($directory, 0700, true)
            && !is_dir($directory)
        ) {
            throw new RuntimeException(
                'notification_send_media_storage_failed'
            );
        }

        @chmod($directory, 0700);

        if (!is_writable($directory)) {
            throw new RuntimeException(
                'notification_send_media_storage_failed'
            );
        }

        return $directory;
    }

    private function safeName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim(preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            '',
            $name
        ) ?? '');

        return mb_substr(
            $name !== '' ? $name : 'file',
            0,
            255,
            'UTF-8'
        );
    }
}
PHP

echo "ADDED: secure multimedia storage services"

SEND_SERVICE_FILE="$send_service_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{SEND_SERVICE_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;
    my $count = () = $$ref =~ /\Q$old\E/g;
    die "Expected one anchor for $label; found $count.\n" if $count != 1;
    my $position = index($$ref, $old);
    substr($$ref, $position, length($old), $new);
    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    "        private ?AuthorizationService \$authorization = null\n",
    "        private ?AuthorizationService \$authorization = null,\n"
        . "        private ?NotificationMediaUploadService \$media = null\n",
    'send center media dependency'
);

replace_once(
    \$text,
    "        \$this->authorization ??=\n"
        . "            new AuthorizationService();\n",
    "        \$this->authorization ??=\n"
        . "            new AuthorizationService();\n"
        . "        \$this->media ??=\n"
        . "            new NotificationMediaUploadService();\n",
    'send center media initialization'
);

replace_once(
    \$text,
    "    public function send(\n"
        . "        int \$actorUserId,\n"
        . "        array \$input\n"
        . "    ): array {\n",
    "    public function send(\n"
        . "        int \$actorUserId,\n"
        . "        array \$input,\n"
        . "        array \$mediaFiles = []\n"
        . "    ): array {\n",
    'send center upload signature'
);

replace_once(
    \$text,
    "        if (\$messageType === 'multimedia') {\n"
        . "            throw new InvalidArgumentException(\n"
        . "                'notification_send_multimedia_delivery_pending'\n"
        . "            );\n"
        . "        }\n\n",
    '',
    'remove multimedia blocker'
);

replace_once(
    \$text,
    "        if (\$channels === []) {\n"
        . "            throw new InvalidArgumentException(\n"
        . "                'notification_send_channel_required'\n"
        . "            );\n"
        . "        }\n",
    "        if (\$channels === []) {\n"
        . "            throw new InvalidArgumentException(\n"
        . "                'notification_send_channel_required'\n"
        . "            );\n"
        . "        }\n\n"
        . "        if (\n"
        . "            \$messageType === 'multimedia'\n"
        . "            && in_array('sms', \$channels, true)\n"
        . "        ) {\n"
        . "            throw new InvalidArgumentException(\n"
        . "                'notification_send_multimedia_sms_not_supported'\n"
        . "            );\n"
        . "        }\n",
    'multimedia channel validation'
);

replace_once(
    \$text,
    "        \$reference =\n"
        . "            'nsc_' . bin2hex(random_bytes(12));\n",
    "        \$mediaAssets = \$messageType === 'multimedia'\n"
        . "            ? \$this->media->store(\n"
        . "                \$actorUserId,\n"
        . "                \$mediaFiles\n"
        . "            )\n"
        . "            : [];\n\n"
        . "        \$reference =\n"
        . "            'nsc_' . bin2hex(random_bytes(12));\n",
    'secure multimedia upload'
);

replace_once(
    \$text,
    "                        'subject' => \$subject,\n"
        . "                        'body' => \$body,\n",
    "                        'subject' => \$subject,\n"
        . "                        'body' => \$body,\n"
        . "                        'message_type_code' =>\n"
        . "                            \$messageType,\n"
        . "                        'media_assets' =>\n"
        . "                            \$mediaAssets,\n",
    'gateway media payload'
);

replace_once(
    \$text,
    "            'public_reference' => \$reference,\n"
        . "            'created_at' => date(\n",
    "            'public_reference' => \$reference,\n"
        . "            'message_type_code' => \$messageType,\n"
        . "            'media_count' => count(\$mediaAssets),\n"
        . "            'created_at' => date(\n",
    'send result media summary'
);

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: notification send center"

GATEWAY_SERVICE_FILE="$gateway_service_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{GATEWAY_SERVICE_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;
    my $count = () = $$ref =~ /\Q$old\E/g;
    die "Expected one anchor for $label; found $count.\n" if $count != 1;
    my $position = index($$ref, $old);
    substr($$ref, $position, length($old), $new);
    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    "        \$body = trim(\n"
        . "            (string) (\$input['body'] ?? '')\n"
        . "        );\n",
    "        \$body = trim(\n"
        . "            (string) (\$input['body'] ?? '')\n"
        . "        );\n"
        . "        \$messageType = strtolower(trim(\n"
        . "            (string) (\n"
        . "                \$input['message_type_code']\n"
        . "                ?? 'text'\n"
        . "            )\n"
        . "        ));\n"
        . "        \$mediaAssets = array_values(array_filter(\n"
        . "            is_array(\$input['media_assets'] ?? null)\n"
        . "                ? \$input['media_assets']\n"
        . "                : [],\n"
        . "            static fn (mixed \$asset): bool =>\n"
        . "                is_array(\$asset)\n"
        . "                && (int) (\$asset['id'] ?? 0) > 0\n"
        . "                && is_readable((string) (\n"
        . "                    \$asset['storage_path'] ?? ''\n"
        . "                ))\n"
        . "        ));\n",
    'gateway media input'
);

replace_once(
    \$text,
    "                \$recipientUserReference\n"
        . "            );\n",
    "                \$recipientUserReference,\n"
        . "                \$messageType,\n"
        . "                \$mediaAssets\n"
        . "            );\n",
    'gateway tracking media args'
);

replace_once(
    \$text,
    "                        'request_reference' =>\n"
        . "                            \$tracking['request_reference'],\n"
        . "                    ]\n",
    "                        'request_reference' =>\n"
        . "                            \$tracking['request_reference'],\n"
        . "                        'message_type_code' =>\n"
        . "                            \$messageType,\n"
        . "                        'media_assets' =>\n"
        . "                            \$mediaAssets,\n"
        . "                    ]\n",
    'gateway adapter media args'
);

replace_once(
    \$text,
    "                    'fallback_used' => \$index > 0,\n"
        . "                ];\n",
    "                    'fallback_used' => \$index > 0,\n"
        . "                    'media_count' => count(\n"
        . "                        \$mediaAssets\n"
        . "                    ),\n"
        . "                ];\n",
    'gateway media count'
);

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

GATEWAY_REPOSITORY_FILE="$gateway_repository_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{GATEWAY_REPOSITORY_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;
    my $count = () = $$ref =~ /\Q$old\E/g;
    die "Expected one anchor for $label; found $count.\n" if $count != 1;
    my $position = index($$ref, $old);
    substr($$ref, $position, length($old), $new);
    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    "        ?int \$recipientUserId = null,\n"
        . "        ?string \$recipientUserReference = null\n"
        . "    ): array {\n",
    "        ?int \$recipientUserId = null,\n"
        . "        ?string \$recipientUserReference = null,\n"
        . "        string \$messageTypeCode = 'text',\n"
        . "        array \$mediaAssets = []\n"
        . "    ): array {\n",
    'repository media signature'
);

replace_once(
    \$text,
    "                    template_code,\n"
        . "                    title,\n",
    "                    template_code,\n"
        . "                    message_type_code,\n"
        . "                    title,\n",
    'notification message type column'
);

replace_once(
    \$text,
    "                    NULL,\n"
        . "                    ?,\n"
        . "                    ?,\n"
        . "                    NULL,\n"
        . "                    'normal',\n",
    "                    NULL,\n"
        . "                    ?,\n"
        . "                    ?,\n"
        . "                    ?,\n"
        . "                    NULL,\n"
        . "                    'normal',\n",
    'notification message type value'
);

replace_once(
    \$text,
    "                \$notificationReference,\n"
        . "                \$eventId,\n"
        . "                \$subject !== ''\n",
    "                \$notificationReference,\n"
        . "                \$eventId,\n"
        . "                \$messageTypeCode,\n"
        . "                \$subject !== ''\n",
    'notification message type parameter'
);

my $start = index(
    $text,
    "    public function createDirectDelivery(\n"
);
my $end = index(
    $text,
    "    public function beginAttempt(\n",
    $start
);
die "createDirectDelivery block not found.\n"
    if $start < 0 || $end < 0;

my $block = substr($text, $start, $end - $start);
my $anchor =
    "            \$deliveryId =\n"
    . "                (int) \$db->lastInsertId();\n\n"
    . "            \$db->commit();\n";
my $replacement =
    "            \$deliveryId =\n"
    . "                (int) \$db->lastInsertId();\n\n"
    . "            if (\$mediaAssets !== []) {\n"
    . "                \$link = \$db->prepare(\"\n"
    . "                    INSERT IGNORE INTO notification_media_links (\n"
    . "                        notification_id,\n"
    . "                        asset_id,\n"
    . "                        sort_order,\n"
    . "                        is_primary,\n"
    . "                        alt_text,\n"
    . "                        created_at\n"
    . "                    )\n"
    . "                    VALUES (?, ?, ?, ?, NULL, CURRENT_TIMESTAMP)\n"
    . "                \");\n\n"
    . "                foreach (array_values(\$mediaAssets) as \$index => \$asset) {\n"
    . "                    \$assetId = (int) (\$asset['id'] ?? 0);\n\n"
    . "                    if (\$assetId > 0) {\n"
    . "                        \$link->execute([\n"
    . "                            \$notificationId,\n"
    . "                            \$assetId,\n"
    . "                            \$index,\n"
    . "                            \$index === 0 ? 1 : 0,\n"
    . "                        ]);\n"
    . "                    }\n"
    . "                }\n"
    . "            }\n\n"
    . "            \$db->commit();\n";
my $count = () = $block =~ /\Q$anchor\E/g;
die "Expected media link anchor once; found $count.\n"
    if $count != 1;
my $position = index($block, $anchor);
substr($block, $position, length($anchor), $replacement);
substr($text, $start, $end - $start, $block);
print "UPDATED: notification media links\n";

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: notification gateway tracking"

SMTP_ADAPTER_FILE="$smtp_adapter_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{SMTP_ADAPTER_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;
    my $count = () = $$ref =~ /\Q$old\E/g;
    die "Expected one anchor for $label; found $count.\n" if $count != 1;
    my $position = index($$ref, $old);
    substr($$ref, $position, length($old), $new);
    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    "        \$body = trim(\n"
        . "            (string) (\$message['body'] ?? '')\n"
        . "        );\n",
    "        \$body = trim(\n"
        . "            (string) (\$message['body'] ?? '')\n"
        . "        );\n"
        . "        \$mediaAssets = array_values(array_filter(\n"
        . "            is_array(\$message['media_assets'] ?? null)\n"
        . "                ? \$message['media_assets']\n"
        . "                : [],\n"
        . "            static fn (mixed \$asset): bool =>\n"
        . "                is_array(\$asset)\n"
        . "                && is_readable((string) (\n"
        . "                    \$asset['storage_path'] ?? ''\n"
        . "                ))\n"
        . "        ));\n",
    'SMTP media assets'
);

replace_once(
    \$text,
    "                'body' => \$body,\n"
        . "                'timeout' => 15,\n",
    "                'body' => \$body,\n"
        . "                'attachments' => array_map(\n"
        . "                    static fn (array \$asset): array => [\n"
        . "                        'path' => (string) \$asset['storage_path'],\n"
        . "                        'mime_type' => (string) \$asset['mime_type'],\n"
        . "                        'original_name' => (string) \$asset['original_name'],\n"
        . "                    ],\n"
        . "                    \$mediaAssets\n"
        . "                ),\n"
        . "                'timeout' => 15,\n",
    'SMTP attachments'
);

replace_once(
    \$text,
    "                    'target_domain' =>\n"
        . "                        \$this->emailDomain(\$destination),\n"
        . "                ],\n",
    "                    'target_domain' =>\n"
        . "                        \$this->emailDomain(\$destination),\n"
        . "                    'media_count' => count(\$mediaAssets),\n"
        . "                ],\n",
    'SMTP media metadata'
);

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

SMTP_TRANSPORT_FILE="$smtp_transport_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{SMTP_TRANSPORT_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;
    my $count = () = $$ref =~ /\Q$old\E/g;
    die "Expected one anchor for $label; found $count.\n" if $count != 1;
    my $position = index($$ref, $old);
    substr($$ref, $position, length($old), $new);
    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    "        \$body = trim((string) (\$message['body'] ?? ''));\n"
        . "        \$timeout = max(\n",
    "        \$body = trim((string) (\$message['body'] ?? ''));\n"
        . "        \$attachments = array_values(array_filter(\n"
        . "            is_array(\$message['attachments'] ?? null)\n"
        . "                ? \$message['attachments']\n"
        . "                : [],\n"
        . "            static fn (mixed \$attachment): bool =>\n"
        . "                is_array(\$attachment)\n"
        . "                && is_readable((string) (\n"
        . "                    \$attachment['path'] ?? ''\n"
        . "                ))\n"
        . "        ));\n"
        . "        \$timeout = max(\n",
    'SMTP transport attachment input'
);

replace_once(
    \$text,
    "                \$messageId,\n"
        . "                \$isTest\n"
        . "            );\n",
    "                \$messageId,\n"
        . "                \$isTest,\n"
        . "                \$attachments\n"
        . "            );\n",
    'SMTP attachment payload call'
);

my $start = -1;
my $end = -1;

if ($text =~ /^[ \t]*private function payload\s*\(/m) {
    $start = $-[0];
}

if ($start >= 0) {
    my $tail = substr($text, $start);

    if (
        $tail =~
            /^[ \t]*private function headerText\s*\(/m
    ) {
        $end = $start + $-[0];
    }
}

if ($start < 0 || $end <= $start) {
    my @signatures = (
        $text =~
            /^.*private function [A-Za-z0-9_]+\s*\(.*$/mg
    );

    die "SMTP payload method not found. Signatures: "
        . join(' | ', @signatures)
        . "\n";
}

my $method = <<'METHOD';
    private function payload(
        string $fromAddress,
        string $fromName,
        string $recipient,
        string $subject,
        string $body,
        string $messageId,
        bool $isTest,
        array $attachments = []
    ): string {
        $safeFromName = $this->headerText(
            $fromName !== '' ? $fromName : $fromAddress
        );
        $safeSubject = $this->headerText($subject);

        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . $safeFromName
                . ' <' . $fromAddress . '>',
            'To: <' . $recipient . '>',
            'Subject: ' . $safeSubject,
            'Message-ID: <' . $messageId . '>',
            'MIME-Version: 1.0',
        ];

        $headers[] = $isTest
            ? 'X-IPKF-Notification-Test: 1'
            : 'X-IPKF-Notification-Gateway: 1';

        if ($attachments === []) {
            $headers[] =
                'Content-Type: text/plain; charset=UTF-8';
            $headers[] =
                'Content-Transfer-Encoding: base64';

            return $this->dotStuff(
                implode("\r\n", $headers)
                . "\r\n\r\n"
                . $this->encodePart($body)
            );
        }

        $boundary = '=_IPKF_'
            . bin2hex(random_bytes(18));
        $headers[] =
            'Content-Type: multipart/mixed; boundary="'
            . $boundary . '"';

        $parts = [
            '--' . $boundary,
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            '',
            $this->encodePart($body),
        ];

        foreach ($attachments as $attachment) {
            $path = (string) (
                $attachment['path'] ?? ''
            );

            if ($path === '' || !is_readable($path)) {
                throw new RuntimeException(
                    'provider_test_send_failed'
                );
            }

            $content = file_get_contents($path);

            if (!is_string($content)) {
                throw new RuntimeException(
                    'provider_test_send_failed'
                );
            }

            $mime = trim((string) (
                $attachment['mime_type']
                ?? 'application/octet-stream'
            ));
            $name = basename(str_replace(
                '\\',
                '/',
                (string) (
                    $attachment['original_name']
                    ?? basename($path)
                )
            ));
            $name = trim(preg_replace(
                '/[\r\n]+/',
                ' ',
                $name
            ) ?? 'attachment');
            $ascii = preg_replace(
                '/[^A-Za-z0-9._-]+/',
                '_',
                $name
            ) ?: 'attachment';

            array_push(
                $parts,
                '--' . $boundary,
                'Content-Type: ' . $mime
                    . '; name="' . $ascii . '"',
                'Content-Transfer-Encoding: base64',
                'Content-Disposition: attachment; filename="'
                    . $ascii
                    . '"; filename*=UTF-8\'\''
                    . rawurlencode($name),
                '',
                $this->encodePart($content)
            );
        }

        $parts[] = '--' . $boundary . '--';
        $parts[] = '';

        return $this->dotStuff(
            implode("\r\n", $headers)
            . "\r\n\r\n"
            . implode("\r\n", $parts)
        );
    }

    private function encodePart(string $content): string
    {
        return rtrim(chunk_split(
            base64_encode($content),
            76,
            "\r\n"
        ));
    }

    private function dotStuff(string $payload): string
    {
        return preg_replace(
            '/(?m)^\./',
            '..',
            $payload
        ) ?? $payload;
    }

METHOD

substr($text, $start, $end - $start, $method);
print "UPDATED: SMTP multipart MIME payload\n";

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: SMTP attachment delivery"

HTTP_TRANSPORT_FILE="$http_transport_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{HTTP_TRANSPORT_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

my $method = <<'METHOD';
    public function postMultipart(
        string $url,
        array $fields,
        array $file,
        int $timeout = 45,
        string $userAgent = 'IPKF-Notification-Gateway/1.0'
    ): array {
        if (
            !function_exists('curl_init')
            || !class_exists(\CURLFile::class)
        ) {
            throw new RuntimeException(
                'provider_test_api_connection_failed'
            );
        }

        $path = (string) ($file['path'] ?? '');
        $fieldName = trim((string) (
            $file['field_name'] ?? ''
        ));

        if (
            $path === ''
            || $fieldName === ''
            || !is_readable($path)
        ) {
            throw new RuntimeException(
                'provider_test_api_connection_failed'
            );
        }

        $payload = [];

        foreach ($fields as $key => $value) {
            $payload[(string) $key] = (string) $value;
        }

        $payload[$fieldName] = new \CURLFile(
            $path,
            (string) (
                $file['mime_type']
                ?? 'application/octet-stream'
            ),
            (string) (
                $file['original_name']
                ?? basename($path)
            )
        );

        $timeout = max(3, min(60, $timeout));
        $startedAt = microtime(true);
        $curl = curl_init($url);

        if ($curl === false) {
            throw new RuntimeException(
                'provider_test_api_connection_failed'
            );
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . $userAgent,
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];

        if (defined('CURLOPT_PROTOCOLS')) {
            $options[CURLOPT_PROTOCOLS] =
                CURLPROTO_HTTPS;
        }

        curl_setopt_array($curl, $options);

        $responseBody = curl_exec($curl);
        $errorNumber = curl_errno($curl);
        $statusCode = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );

        curl_close($curl);

        if ($responseBody === false) {
            throw new RuntimeException(
                $errorNumber === 28
                    ? 'provider_test_api_timeout'
                    : 'provider_test_api_connection_failed'
            );
        }

        return $this->result(
            $statusCode,
            (string) $responseBody,
            $startedAt
        );
    }

METHOD

my $anchor = "    private function request(\n";
my $count = () = $text =~ /\Q$anchor\E/g;
die "Expected multipart insertion anchor once; found $count.\n"
    if $count != 1;
my $position = index($text, $anchor);
substr($text, $position, 0, $method);
print "UPDATED: Bale multipart HTTP transport\n";

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

BALE_ADAPTER_FILE="$bale_adapter_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{BALE_ADAPTER_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

my $start = index($text, "    public function send(\n");
my $end = index(
    $text,
    "    private function gatewayException(\n",
    $start
);
die "Bale send block not found.\n"
    if $start < 0 || $end < 0;

my $methods = <<'METHODS';
    public function send(
        array $instance,
        array $message
    ): array {
        $configuration =
            $this->runtime->configuration($instance);
        $secrets = $this->runtime->secrets($instance);

        $botToken = trim(
            (string) ($secrets['bot_token'] ?? '')
        );
        $chatId = trim(
            (string) ($message['destination'] ?? '')
        );
        $body = trim(
            (string) ($message['body'] ?? '')
        );
        $mediaAssets = array_values(array_filter(
            is_array($message['media_assets'] ?? null)
                ? $message['media_assets']
                : [],
            static fn (mixed $asset): bool =>
                is_array($asset)
                && is_readable((string) (
                    $asset['storage_path'] ?? ''
                ))
        ));

        if ($botToken === '') {
            throw new InvalidArgumentException(
                'notification_gateway_secret_unavailable'
            );
        }

        if (
            preg_match(
                '/^-?[0-9]{1,20}$/',
                $chatId
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_destination_invalid'
            );
        }

        if (
            mb_strlen($body, 'UTF-8') < 1
            || mb_strlen($body, 'UTF-8') > 4096
        ) {
            throw new InvalidArgumentException(
                'notification_gateway_message_invalid'
            );
        }

        $apiBase = rtrim(
            trim((string) (
                $configuration['api_base']
                ?? 'https://tapi.bale.ai'
            )),
            '/'
        );

        if ($apiBase === '') {
            $apiBase = 'https://tapi.bale.ai';
        }

        $this->runtime->assertHttpsEndpoint(
            $apiBase,
            ['tapi.bale.ai']
        );

        $parseMode = trim((string) (
            $configuration['parse_mode'] ?? ''
        ));

        if (
            $parseMode !== ''
            && preg_match(
                '/^[A-Za-z0-9_]{1,30}$/',
                $parseMode
            ) !== 1
        ) {
            $parseMode = '';
        }

        try {
            if ($mediaAssets === []) {
                return $this->sendText(
                    $apiBase,
                    $botToken,
                    $chatId,
                    $body,
                    $parseMode
                );
            }

            return $this->sendMedia(
                $apiBase,
                $botToken,
                $chatId,
                $body,
                $parseMode,
                $mediaAssets
            );
        } catch (Throwable $exception) {
            throw $this->gatewayException($exception);
        }
    }

    private function sendText(
        string $apiBase,
        string $botToken,
        string $chatId,
        string $body,
        string $parseMode
    ): array {
        $payload = [
            'chat_id' => (int) $chatId,
            'text' => $body,
        ];

        if ($parseMode !== '') {
            $payload['parse_mode'] = $parseMode;
        }

        $response = $this->http->postJson(
            $apiBase . '/bot'
                . rawurlencode($botToken)
                . '/sendMessage',
            $payload,
            15,
            'IPKF-Notification-Gateway/1.0'
        );
        $json = $this->accepted($response);

        return [
            'provider_message_reference' =>
                (string) (
                    $json['result']['message_id'] ?? ''
                ),
            'response_code' => (string) (
                $response['status_code'] ?? ''
            ),
            'response_message' => 'bale_accepted',
            'duration_ms' => (int) (
                $response['duration_ms'] ?? 0
            ),
            'metadata' => [
                'transport' => 'bale',
                'media_count' => 0,
                'target_fingerprint' => substr(
                    hash('sha256', $chatId),
                    0,
                    16
                ),
            ],
        ];
    }

    private function sendMedia(
        string $apiBase,
        string $botToken,
        string $chatId,
        string $body,
        string $parseMode,
        array $assets
    ): array {
        $duration = 0;
        $primaryReference = '';
        $responseCode = '';
        $caption = mb_strlen($body, 'UTF-8') <= 900
            ? $body
            : '';

        if ($caption === '') {
            $text = $this->sendText(
                $apiBase,
                $botToken,
                $chatId,
                $body,
                $parseMode
            );
            $duration += (int) (
                $text['duration_ms'] ?? 0
            );
            $primaryReference = (string) (
                $text['provider_message_reference'] ?? ''
            );
            $responseCode = (string) (
                $text['response_code'] ?? ''
            );
        }

        foreach ($assets as $index => $asset) {
            [$method, $field] = match (
                (string) (
                    $asset['media_kind'] ?? 'document'
                )
            ) {
                'image' => ['sendPhoto', 'photo'],
                'video' => ['sendVideo', 'video'],
                'audio' => ['sendAudio', 'audio'],
                default => ['sendDocument', 'document'],
            };

            $fields = ['chat_id' => (int) $chatId];

            if ($index === 0 && $caption !== '') {
                $fields['caption'] = $caption;

                if ($parseMode !== '') {
                    $fields['parse_mode'] = $parseMode;
                }
            }

            $response = $this->http->postMultipart(
                $apiBase . '/bot'
                    . rawurlencode($botToken)
                    . '/' . $method,
                $fields,
                [
                    'field_name' => $field,
                    'path' => (string) (
                        $asset['storage_path']
                    ),
                    'mime_type' => (string) (
                        $asset['mime_type']
                    ),
                    'original_name' => (string) (
                        $asset['original_name']
                    ),
                ],
                45,
                'IPKF-Notification-Gateway/1.0'
            );
            $json = $this->accepted($response);
            $reference = (string) (
                $json['result']['message_id'] ?? ''
            );

            if ($primaryReference === '') {
                $primaryReference = $reference;
            }

            $duration += (int) (
                $response['duration_ms'] ?? 0
            );
            $responseCode = (string) (
                $response['status_code'] ?? ''
            );
        }

        return [
            'provider_message_reference' =>
                $primaryReference,
            'response_code' => $responseCode,
            'response_message' =>
                'bale_media_accepted',
            'duration_ms' => $duration,
            'metadata' => [
                'transport' => 'bale',
                'media_count' => count($assets),
                'target_fingerprint' => substr(
                    hash('sha256', $chatId),
                    0,
                    16
                ),
            ],
        ];
    }

    private function accepted(array $response): array
    {
        $json = $response['json'] ?? null;

        if (
            (int) ($response['status_code'] ?? 0) < 200
            || (int) ($response['status_code'] ?? 0) >= 300
            || !is_array($json)
            || empty($json['ok'])
            || !is_array($json['result'] ?? null)
        ) {
            throw new RuntimeException(
                'notification_gateway_provider_rejected'
            );
        }

        return $json;
    }

METHODS

substr($text, $start, $end - $start, $methods);
print "UPDATED: Bale multimedia delivery\n";

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

echo "UPDATED: Bale multimedia adapter"

ROUTES_FILE="$routes_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{ROUTES_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

my $start = index(
    $text,
    "\$router->post(\n"
        . "    '/admin/communications/settings/send',"
);
my $end = index(
    $text,
    "\$router->post(\n"
        . "    '/admin/communications/settings/send/bale-invitations',",
    $start
);
die "Notification send route not found.\n"
    if $start < 0 || $end < 0;

my $block = substr($text, $start, $end - $start);
my $anchor =
    "                ]\n"
    . "            );\n";
my $replacement =
    "                ],\n"
    . "                is_array(\n"
    . "                    \$_FILES['media_files'] ?? null\n"
    . "                ) ? \$_FILES['media_files'] : []\n"
    . "            );\n";
my $count = () = $block =~ /\Q$anchor\E/g;
die "Expected send service call once; found $count.\n"
    if $count != 1;
my $position = index($block, $anchor);
substr($block, $position, length($anchor), $replacement);
substr($text, $start, $end - $start, $block);
print "UPDATED: route media uploads\n";

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

VIEW_FILE="$view_file" perl <<'PERL'
use strict;
use warnings;
use utf8;
use open qw(:std :encoding(UTF-8));

my $path = $ENV{VIEW_FILE};
open my $fh, '<:encoding(UTF-8)', $path or die "$path: $!\n";
local $/;
my $text = <$fh>;
close $fh;
$text =~ s/\r\n?/\n/g;

sub replace_once {
    my ($ref, $old, $new, $label) = @_;
    my $count = () = $$ref =~ /\Q$old\E/g;
    die "Expected one anchor for $label; found $count.\n" if $count != 1;
    my $position = index($$ref, $old);
    substr($$ref, $position, length($old), $new);
    print "UPDATED: $label\n";
}

replace_once(
    \$text,
    "    'notification_send_multimedia_delivery_pending' => ['error', 'ساختار محتوای چندرسانه‌ای ثبت شد؛ اتصال نهایی Adapter ایمیل و بله در مرحله بعد فعال می‌شود.'],\n",
    "    'notification_send_multimedia_sms_not_supported' => ['error', 'پیام چندرسانه‌ای فقط از طریق ایمیل و بله قابل ارسال است.'],\n"
        . "    'notification_send_media_required' => ['error', 'برای پیام چندرسانه‌ای حداقل یک فایل انتخاب کنید.'],\n"
        . "    'notification_send_media_count_exceeded' => ['error', 'حداکثر پنج فایل در هر ارسال مجاز است.'],\n"
        . "    'notification_send_media_file_size_exceeded' => ['error', 'حجم هر فایل باید حداکثر ده مگابایت باشد.'],\n"
        . "    'notification_send_media_total_size_exceeded' => ['error', 'مجموع حجم فایل‌ها باید حداکثر سی مگابایت باشد.'],\n"
        . "    'notification_send_media_type_invalid' => ['error', 'نوع یا محتوای یکی از فایل‌ها مجاز نیست.'],\n"
        . "    'notification_send_media_type_detection_failed' => ['error', 'تشخیص نوع واقعی یکی از فایل‌ها انجام نشد.'],\n"
        . "    'notification_send_media_upload_invalid' => ['error', 'فایل بارگذاری‌شده معتبر نیست.'],\n"
        . "    'notification_send_media_upload_failed' => ['error', 'بارگذاری فایل‌های چندرسانه‌ای انجام نشد.'],\n"
        . "    'notification_send_media_storage_unavailable' => ['error', 'ساختار ذخیره‌سازی فایل‌های اعلان آماده نیست.'],\n"
        . "    'notification_send_media_storage_failed' => ['error', 'ذخیره امن فایل‌های اعلان انجام نشد.'],\n",
    'multimedia status messages'
);

replace_once(
    \$text,
    "                    mediaBlock.innerHTML = `\n"
        . "                        <label class=\"notification-send-dropzone\">\n"
        . "                            <strong>فایل‌های چندرسانه‌ای</strong>\n"
        . "                            <span>تصویر، ویدئو، صوت یا سند</span>\n"
        . "                            <input type=\"file\"\n"
        . "                                name=\"media_files[]\" multiple\n"
        . "                                data-send-media-files\n"
        . "                                accept=\".jpg,.jpeg,.png,.webp,.mp4,.mp3,.m4a,.ogg,.pdf,.doc,.docx,.xls,.xlsx,.txt\">\n"
        . "                        </label>\n"
        . "                        <div class=\"notification-send-media-preview\"\n"
        . "                            data-send-media-preview></div>\n"
        . "                    `;\n",
    "                    mediaBlock.innerHTML = `\n"
        . "                        <label class=\"notification-send-dropzone\">\n"
        . "                            <span class=\"notification-send-dropzone__icon\">＋</span>\n"
        . "                            <strong>انتخاب فایل‌های چندرسانه‌ای</strong>\n"
        . "                            <span>تصویر، ویدئو، صوت یا سند</span>\n"
        . "                            <em data-send-media-file-count>فایلی انتخاب نشده است</em>\n"
        . "                            <input type=\"file\"\n"
        . "                                name=\"media_files[]\" multiple\n"
        . "                                data-send-media-files\n"
        . "                                accept=\".jpg,.jpeg,.png,.webp,.mp4,.mp3,.m4a,.ogg,.pdf,.docx,.xlsx,.txt\">\n"
        . "                        </label>\n"
        . "                        <p class=\"notification-send-media-limits\">\n"
        . "                            حداکثر ۵ فایل، هر فایل ۱۰ مگابایت و مجموع ۳۰ مگابایت\n"
        . "                        </p>\n"
        . "                        <div class=\"notification-send-media-preview\"\n"
        . "                            data-send-media-preview></div>\n"
        . "                    `;\n",
    'Persian multimedia picker'
);

$text =~ s/\n*\z/\n/;
open my $out, '>:encoding(UTF-8)', $path or die "$path: $!\n";
print {$out} $text;
close $out;
PERL

cat >> "$style_file" <<'CSS'

<style>
/* notification-multimedia-delivery-core-v061 */
.notification-send-actions [hidden] {
    display: none !important;
}

.notification-send-dropzone {
    align-items: center;
    cursor: pointer;
    display: grid;
    gap: .25rem;
    justify-items: center;
    position: relative;
    text-align: center;
}

.notification-send-dropzone input[type="file"] {
    block-size: 1px;
    clip: rect(0 0 0 0);
    clip-path: inset(50%);
    inline-size: 1px;
    overflow: hidden;
    position: absolute;
    white-space: nowrap;
}

.notification-send-dropzone__icon {
    align-items: center;
    background: var(--admin-primary-soft);
    border-radius: 999px;
    color: var(--admin-primary);
    display: inline-flex;
    font-size: 1.1rem;
    height: 32px;
    justify-content: center;
    width: 32px;
}

.notification-send-dropzone em {
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: 999px;
    color: var(--admin-text-muted);
    font-size: .72rem;
    font-style: normal;
    padding: .25rem .55rem;
}

.notification-send-media-limits {
    color: var(--admin-text-muted);
    font-size: .7rem;
    margin: 0;
    text-align: center;
}
</style>
CSS

echo "UPDATED: multimedia form"

cat > "$test_file" <<'PHP'
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

$read = static function (
    string $path
) use ($root): string {
    $content = file_get_contents(
        $root . '/' . $path
    );

    if (!is_string($content)) {
        fwrite(STDERR, "FAIL: cannot read {$path}\n");
        exit(1);
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

$send = $read(
    'public_html/app/Services/'
    . 'NotificationSendCenterService.php'
);
$gateway = $read(
    'public_html/app/Services/'
    . 'NotificationGatewayService.php'
);
$smtp = $read(
    'public_html/app/Services/'
    . 'NotificationSmtpTransport.php'
);
$bale = $read(
    'public_html/app/Services/'
    . 'NotificationBaleGatewayAdapter.php'
);
$http = $read(
    'public_html/app/Services/'
    . 'NotificationProviderHttpTransport.php'
);
$route = $read(
    'public_html/routes/communication-center.php'
);
$view = $read(
    'public_html/resources/views/admin/'
    . 'communication-settings.php'
);
$style = $read(
    'public_html/resources/views/admin/partials/'
    . 'communication-style.php'
);

$expect(
    !str_contains(
        $send,
        'notification_send_multimedia_delivery_pending'
    )
    && str_contains(
        $send,
        'NotificationMediaUploadService'
    )
    && str_contains($gateway, "'media_assets'"),
    'Multimedia send path is incomplete.'
);

$expect(
    str_contains($smtp, 'multipart/mixed')
    && str_contains($bale, 'sendPhoto')
    && str_contains($bale, 'sendDocument')
    && str_contains($http, 'postMultipart'),
    'Multimedia adapters are incomplete.'
);

$expect(
    str_contains(
        $route,
        "\$_FILES['media_files']"
    )
    && str_contains(
        $view,
        'notification_send_media_required'
    )
    && str_contains(
        $style,
        'notification-multimedia-delivery-core-v061'
    ),
    'Multimedia route or form is incomplete.'
);

echo "Notification multimedia delivery core checks passed.\n";
PHP

mkdir -p tools
cp -- "$0" "$tool_file"

git add -- \
  "$media_repository_file" \
  "$media_upload_file" \
  "$send_service_file" \
  "$gateway_service_file" \
  "$gateway_repository_file" \
  "$smtp_adapter_file" \
  "$smtp_transport_file" \
  "$bale_adapter_file" \
  "$http_transport_file" \
  "$routes_file" \
  "$view_file" \
  "$style_file" \
  "$test_file" \
  "$tool_file"

echo
echo "=== Cached Validation ==="

git diff --cached --check

if command -v php >/dev/null 2>&1; then
    echo
    echo "=== PHP Lint ==="

    for file in \
      "$media_repository_file" \
      "$media_upload_file" \
      "$send_service_file" \
      "$gateway_service_file" \
      "$gateway_repository_file" \
      "$smtp_adapter_file" \
      "$smtp_transport_file" \
      "$bale_adapter_file" \
      "$http_transport_file" \
      "$routes_file" \
      "$view_file" \
      "$style_file" \
      "$test_file"
    do
      php -l "$file"
    done

    php "$test_file"
else
    echo
    echo "PHP_NOT_AVAILABLE_ON_WINDOWS=SKIPPED"
fi

echo
echo "=== Multimedia Markers ==="

git grep -n -E \
  "NotificationMediaUploadService|notification_send_media_required|postMultipart|multipart/mixed|sendPhoto|notification-multimedia-delivery-core-v061" \
  -- \
  "$media_upload_file" \
  "$send_service_file" \
  "$gateway_service_file" \
  "$smtp_transport_file" \
  "$bale_adapter_file" \
  "$http_transport_file" \
  "$view_file" \
  "$style_file" \
  "$test_file"

echo
echo "=== Pending Blocker Check ==="

if git grep -n \
  "notification_send_multimedia_delivery_pending" \
  -- "$send_service_file" "$view_file"
then
    echo "MULTIMEDIA_PENDING_BLOCKER=PRESENT" >&2
    exit 1
else
    echo "MULTIMEDIA_PENDING_BLOCKER=REMOVED"
fi

echo
echo "=== Unstaged Changes Check ==="

if git diff --quiet; then
    echo "UNSTAGED_CHANGES=0"
else
    echo "UNSTAGED_CHANGES=1"
    git status --short
    exit 1
fi

echo
echo "=== Cached Summary ==="

git diff --cached --stat

echo
echo "=== Final Status ==="

git status --short --branch

echo
echo "NOTIFICATION MULTIMEDIA DELIVERY CORE ADDED AND STAGED"
echo "No commit was created."

trap - EXIT
