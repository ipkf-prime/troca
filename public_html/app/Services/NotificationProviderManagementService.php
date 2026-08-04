<?php

namespace App\Services;

use App\Repositories\NotificationProviderManagementRepository;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class NotificationProviderManagementService extends BaseService
{
    private const FIELD_LABELS = [
        'host' => 'میزبان',
        'port' => 'پورت',
        'encryption' => 'رمزنگاری ارتباط',
        'username' => 'نام کاربری',
        'from_address' => 'نشانی فرستنده',
        'from_name' => 'نام نمایشی فرستنده',
        'sender' => 'خط یا شناسه فرستنده',
        'balance_endpoint' => 'نشانی استعلام اعتبار',
        'endpoint' => 'نشانی API',
        'api_base' => 'نشانی پایه API',
        'bot_username' => 'نام کاربری بات',
        'parse_mode' => 'قالب پیام',
        'phone_number_id' => 'شناسه شماره واتساپ',
        'business_account_id' => 'شناسه حساب تجاری',
        'api_version' => 'نسخه API',
        'provider_name' => 'نام سرویس‌دهنده',
    ];

    private const SECRET_FIELDS = [
        'smtp' => [
            [
                'key' => 'password',
                'label' => 'رمز عبور ایمیل (SMTP)',
                'required' => false,
            ],
        ],
        'gmail_smtp' => [
            [
                'key' => 'password',
                'label' => 'رمز برنامه یا رمز عبور Gmail',
                'required' => false,
            ],
        ],
        'yahoo_smtp' => [
            [
                'key' => 'password',
                'label' => 'رمز برنامه یا رمز عبور Yahoo',
                'required' => false,
            ],
        ],
        'microsoft365_smtp' => [
            [
                'key' => 'password',
                'label' => 'رمز عبور Microsoft 365',
                'required' => false,
            ],
        ],
        'kavenegar' => [
            [
                'key' => 'api_key',
                'label' => 'کلید سرویس کاوه‌نگار',
                'required' => true,
            ],
        ],
        'melipayamak' => [
            [
                'key' => 'password',
                'label' => 'رمز عبور ملی پیامک',
                'required' => false,
            ],
            [
                'key' => 'api_key',
                'label' => 'کلید سرویس ملی پیامک',
                'required' => false,
            ],
        ],
        'ippanel' => [
            [
                'key' => 'api_key',
                'label' => 'کلید سرویس IPPanel',
                'required' => true,
            ],
        ],
        'generic_sms' => [
            [
                'key' => 'api_key',
                'label' => 'کلید سرویس',
                'required' => false,
            ],
            [
                'key' => 'password',
                'label' => 'رمز عبور درگاه',
                'required' => false,
            ],
            [
                'key' => 'auth_token',
                'label' => 'توکن احراز هویت',
                'required' => false,
            ],
        ],
        'bale_bot' => [
            [
                'key' => 'bot_token',
                'label' => 'توکن بات بله',
                'required' => true,
            ],
            [
                'key' => 'webhook_secret',
                'label' => 'کلید محرمانه وب‌هوک',
                'required' => false,
            ],
        ],
        'telegram_bot' => [
            [
                'key' => 'bot_token',
                'label' => 'توکن بات تلگرام',
                'required' => true,
            ],
            [
                'key' => 'webhook_secret',
                'label' => 'کلید محرمانه وب‌هوک',
                'required' => false,
            ],
        ],
        'eitaa_bot' => [
            [
                'key' => 'bot_token',
                'label' => 'توکن بات ایتا',
                'required' => true,
            ],
            [
                'key' => 'webhook_secret',
                'label' => 'کلید محرمانه وب‌هوک',
                'required' => false,
            ],
        ],
        'whatsapp_cloud' => [
            [
                'key' => 'access_token',
                'label' => 'توکن دسترسی واتساپ',
                'required' => true,
            ],
            [
                'key' => 'app_secret',
                'label' => 'کلید محرمانه برنامه',
                'required' => false,
            ],
            [
                'key' => 'verify_token',
                'label' => 'توکن تأیید وب‌هوک',
                'required' => false,
            ],
        ],
    ];

    public function __construct(
        private ?NotificationProviderManagementRepository $repository = null,
        private ?NotificationProviderSecretService $secrets = null,
        private ?AuthorizationService $authorization = null
    ) {
        $this->repository ??=
            new NotificationProviderManagementRepository();
        $this->secrets ??=
            new NotificationProviderSecretService();
        $this->authorization ??=
            new AuthorizationService();
    }

    public function page(
        int $userId,
        string $editReference = ''
    ): array {
        $this->authorize($userId);

        $definitions = $this->definitions();
        $form = $this->emptyForm($definitions);

        $editReference = trim($editReference);

        if ($editReference !== '') {
            $instance = $this->repository
                ->instanceByReference($editReference);

            if ($instance === null) {
                throw new RuntimeException(
                    'provider_instance_not_found'
                );
            }

            $configuration = json_decode(
                (string) ($instance['configuration_json'] ?? ''),
                true
            );

            if (!is_array($configuration)) {
                $configuration = [];
            }

            $secretKeys = [];
            $secretState = 'empty';
            $secretSet = $this->repository->secretSet(
                (int) $instance['id']
            );

            if ($secretSet !== null) {
                try {
                    $secretKeys = array_keys(
                        $this->secrets->decrypt(
                            (string) $secretSet[
                                'encrypted_payload'
                            ]
                        )
                    );
                    $secretState = 'stored';
                } catch (Throwable) {
                    $secretState = 'unavailable';
                }
            }

            $form = [
                'is_edit' => true,
                'public_reference' =>
                    (string) $instance['public_reference'],
                'provider_type_id' =>
                    (int) $instance['provider_type_id'],
                'provider_type_code' =>
                    (string) $instance['provider_type_code'],
                'channel_code' =>
                    (string) $instance['channel_code'],
                'code' => (string) $instance['code'],
                'title' => (string) $instance['title'],
                'description' =>
                    (string) ($instance['description'] ?? ''),
                'priority' => (int) $instance['priority'],
                'daily_limit' =>
                    $instance['daily_limit'] ?? '',
                'monthly_limit' =>
                    $instance['monthly_limit'] ?? '',
                'is_enabled' =>
                    !empty($instance['is_enabled']),
                'configuration' => $configuration,
                'stored_secret_keys' => $secretKeys,
                'secret_state' => $secretState,
            ];
        }

        return [
            'definitions' => $definitions,
            'form' => $form,
        ];
    }

    public function save(
        int $userId,
        array $input
    ): array {
        $this->authorize($userId);

        $reference = trim(
            (string) ($input['public_reference'] ?? '')
        );
        $existing = null;

        if ($reference !== '') {
            $existing = $this->repository
                ->instanceByReference($reference);

            if ($existing === null) {
                throw new RuntimeException(
                    'provider_instance_not_found'
                );
            }
        }

        $providerTypeId = (int) (
            $input['provider_type_id'] ?? 0
        );

        if ($existing !== null) {
            $providerTypeId =
                (int) $existing['provider_type_id'];
        }

        $type = $this->repository
            ->providerType($providerTypeId);

        if ($type === null) {
            throw new InvalidArgumentException(
                'provider_type_required'
            );
        }

        $channelCode = strtolower(trim(
            (string) ($input['channel_code'] ?? '')
        ));

        if ($existing !== null) {
            $channelCode =
                (string) $existing['channel_code'];
        }

        if (!in_array(
            $channelCode,
            ['email', 'sms', 'messenger'],
            true
        )) {
            throw new InvalidArgumentException(
                'provider_channel_required'
            );
        }

        if ((string) $type['channel_code'] !== $channelCode) {
            throw new InvalidArgumentException(
                'provider_channel_mismatch'
            );
        }

        $providerCode = (string) $type['code'];
        $definition = $this->definition($type);

        $title = trim((string) ($input['title'] ?? ''));

        if (
            mb_strlen($title, 'UTF-8') < 2
            || mb_strlen($title, 'UTF-8') > 190
        ) {
            throw new InvalidArgumentException(
                'provider_title_invalid'
            );
        }

        $code = strtolower(trim(
            (string) ($input['code'] ?? '')
        ));

        if ($code === '') {
            $code = $providerCode
                . '-'
                . substr(bin2hex(random_bytes(6)), 0, 10);
        }

        if (
            preg_match(
                '/^[a-z0-9][a-z0-9._-]{2,119}$/',
                $code
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'provider_code_invalid'
            );
        }

        if ($this->repository->codeExists(
            $code,
            $reference !== '' ? $reference : null
        )) {
            throw new InvalidArgumentException(
                'provider_code_exists'
            );
        }

        $description = trim(
            (string) ($input['description'] ?? '')
        );
        $description = $description === ''
            ? null
            : mb_substr(
                $description,
                0,
                1000,
                'UTF-8'
            );

        $configurationInput = is_array(
            $input['configuration'] ?? null
        ) ? $input['configuration'] : [];

        $configuration = $this->configuration(
            $definition['public_fields'],
            $configurationInput
        );

        $currentSecrets = [];

        if ($existing !== null) {
            $secretSet = $this->repository->secretSet(
                (int) $existing['id']
            );

            if ($secretSet !== null) {
                $currentSecrets = $this->secrets->decrypt(
                    (string) $secretSet['encrypted_payload']
                );
            }
        }

        $secretInput = is_array(
            $input['secrets'] ?? null
        ) ? $input['secrets'] : [];

        [$mergedSecrets, $secretKeysUpdated] =
            $this->mergeSecrets(
                $definition['secret_fields'],
                $currentSecrets,
                $secretInput
            );

        $encryptedSecrets = null;

        if (
            $secretKeysUpdated !== []
            || (
                $existing === null
                && $mergedSecrets !== []
            )
        ) {
            $encryptedSecrets =
                $this->secrets->encrypt($mergedSecrets);
        }

        $enabled = !empty($input['is_enabled']);
        $priority = max(
            -1000,
            min(1000, (int) ($input['priority'] ?? 0))
        );

        $dailyLimit = $this->nullableUnsigned(
            $input['daily_limit'] ?? null,
            1000000000
        );
        $monthlyLimit = $this->nullableUnsigned(
            $input['monthly_limit'] ?? null,
            1000000000
        );

        $created = $existing === null;

        if ($created) {
            $reference =
                'npi_' . bin2hex(random_bytes(12));
        }

        $savedReference = $this->repository->save(
            [
                'public_reference' => $reference,
                'provider_type_id' => $providerTypeId,
                'code' => $code,
                'title' => $title,
                'description' => $description,
                'instance_kind' =>
                    $this->instanceKind($providerCode),
                'status_code' =>
                    $enabled ? 'active' : 'inactive',
                'is_enabled' => $enabled ? 1 : 0,
                'priority' => $priority,
                'configuration_json' => json_encode(
                    $configuration,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                ),
                'daily_limit' => $dailyLimit,
                'monthly_limit' => $monthlyLimit,
            ],
            $encryptedSecrets,
            $userId,
            [
                'provider_type_code' => $providerCode,
                'channel_code' => $channelCode,
                'code' => $code,
                'title' => $title,
                'enabled' => $enabled,
                'configuration_keys' =>
                    array_keys($configuration),
                'secret_keys_updated' =>
                    $secretKeysUpdated,
            ]
        );

        return [
            'public_reference' => $savedReference,
            'created' => $created,
        ];
    }

    public function setEnabled(
        int $userId,
        string $reference,
        bool $enabled
    ): void {
        $this->authorize($userId);

        $reference = trim($reference);

        if (
            preg_match(
                '/^npi_[a-f0-9]{24}$/',
                $reference
            ) !== 1
        ) {
            throw new InvalidArgumentException(
                'provider_reference_invalid'
            );
        }

        $this->repository->setEnabled(
            $reference,
            $enabled,
            $userId
        );
    }

    private function definitions(): array
    {
        return array_map(
            fn (array $type): array =>
                $this->definition($type),
            $this->repository->providerTypes()
        );
    }

    private function definition(array $type): array
    {
        $schema = json_decode(
            (string) ($type['config_schema_json'] ?? ''),
            true
        );

        if (!is_array($schema)) {
            $schema = [];
        }

        $publicFields = [];

        foreach ($schema as $field) {
            if (!is_array($field)) {
                continue;
            }

            $key = strtolower(trim(
                (string) ($field['key'] ?? '')
            ));

            if (
                preg_match(
                    '/^[a-z][a-z0-9_]{0,79}$/',
                    $key
                ) !== 1
            ) {
                continue;
            }

            $typeCode = strtolower(trim(
                (string) ($field['type'] ?? 'text')
            ));

            if (!in_array(
                $typeCode,
                ['text', 'number', 'email', 'url', 'select'],
                true
            )) {
                $typeCode = 'text';
            }

            $options = is_array(
                $field['options'] ?? null
            ) ? array_values(array_filter(
                array_map('strval', $field['options']),
                static fn (string $value): bool =>
                    $value !== ''
            )) : [];

            $publicFields[] = [
                'key' => $key,
                'label' => self::FIELD_LABELS[$key]
                    ?? str_replace('_', ' ', $key),
                'type' => $typeCode,
                'required' =>
                    !empty($field['required']),
                'options' => $options,
            ];
        }

        $providerCode = (string) $type['code'];

        return [
            'id' => (int) $type['id'],
            'code' => $providerCode,
            'title' => (string) $type['title'],
            'channel_code' =>
                (string) $type['channel_code'],
            'driver_code' =>
                (string) $type['driver_code'],
            'supports_balance' =>
                !empty($type['supports_balance']),
            'instance_kind' =>
                $this->instanceKind($providerCode),
            'public_fields' => $publicFields,
            'secret_fields' =>
                self::SECRET_FIELDS[$providerCode] ?? [],
        ];
    }

    private function configuration(
        array $fields,
        array $input
    ): array {
        $configuration = [];

        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $value = trim((string) ($input[$key] ?? ''));

            if (
                $value === ''
                && !empty($field['required'])
            ) {
                throw new InvalidArgumentException(
                    'provider_config_required_' . $key
                );
            }

            if ($value === '') {
                continue;
            }

            $type = (string) $field['type'];

            if (
                $type === 'email'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new InvalidArgumentException(
                    'provider_config_invalid_' . $key
                );
            }

            if (
                $type === 'url'
                && filter_var(
                    $value,
                    FILTER_VALIDATE_URL
                ) === false
            ) {
                throw new InvalidArgumentException(
                    'provider_config_invalid_' . $key
                );
            }

            if ($type === 'number') {
                if (
                    filter_var(
                        $value,
                        FILTER_VALIDATE_INT
                    ) === false
                ) {
                    throw new InvalidArgumentException(
                        'provider_config_invalid_' . $key
                    );
                }

                $number = (int) $value;

                if (
                    $key === 'port'
                    && (
                        $number < 1
                        || $number > 65535
                    )
                ) {
                    throw new InvalidArgumentException(
                        'provider_config_invalid_' . $key
                    );
                }

                $configuration[$key] = $number;
                continue;
            }

            if (
                $type === 'select'
                && !in_array(
                    $value,
                    $field['options'],
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    'provider_config_invalid_' . $key
                );
            }

            $configuration[$key] = mb_substr(
                $value,
                0,
                1000,
                'UTF-8'
            );
        }

        return $configuration;
    }

    private function mergeSecrets(
        array $fields,
        array $current,
        array $input
    ): array {
        $merged = [];
        $updated = [];

        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $existing = trim(
                (string) ($current[$key] ?? '')
            );
            $replacement = trim(
                (string) ($input[$key] ?? '')
            );

            if ($replacement !== '') {
                $merged[$key] = mb_substr(
                    $replacement,
                    0,
                    4000,
                    'UTF-8'
                );
                $updated[] = $key;
                continue;
            }

            if ($existing !== '') {
                $merged[$key] = $existing;
                continue;
            }

            if (!empty($field['required'])) {
                throw new InvalidArgumentException(
                    'provider_secret_required_' . $key
                );
            }
        }

        return [
            $merged,
            array_values(array_unique($updated)),
        ];
    }

    private function nullableUnsigned(
        mixed $value,
        int $maximum
    ): ?int {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (
            filter_var(
                $value,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new InvalidArgumentException(
                'provider_limit_invalid'
            );
        }

        $number = (int) $value;

        if ($number < 0 || $number > $maximum) {
            throw new InvalidArgumentException(
                'provider_limit_invalid'
            );
        }

        return $number;
    }

    private function instanceKind(
        string $providerCode
    ): string {
        return str_ends_with($providerCode, '_bot')
            ? 'bot'
            : 'account';
    }

    private function emptyForm(array $definitions): array
    {
        return [
            'is_edit' => false,
            'public_reference' => '',
            'channel_code' => '',
            'provider_type_id' => 0,
            'provider_type_code' => '',
            'code' => '',
            'title' => '',
            'description' => '',
            'priority' => 0,
            'daily_limit' => '',
            'monthly_limit' => '',
            'is_enabled' => false,
            'configuration' => [],
            'stored_secret_keys' => [],
            'secret_state' => 'empty',
        ];
    }

    private function authorize(int $userId): void
    {
        if (!$this->authorization->hasPermission(
            $userId,
            'notifications.providers.manage'
        )) {
            throw new RuntimeException(
                'provider_management_forbidden'
            );
        }
    }
}
