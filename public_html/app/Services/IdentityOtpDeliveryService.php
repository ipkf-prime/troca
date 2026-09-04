<?php

declare(strict_types=1);

namespace App\Services;

use IPKF\Support\Env;
use Throwable;

class IdentityOtpDeliveryService extends BaseService
{
    public function __construct(
        private ?DynamicMessageTemplateService $templates = null,
        private ?NotificationGatewayService $gateway = null
    ) {
        $this->templates ??=
            new DynamicMessageTemplateService();
        $this->gateway ??=
            new NotificationGatewayService();
    }

    public function deliver(
        string $field,
        string $destination,
        string $code,
        ?string $templateCode = null,
        array $variables = [],
        int $actorUserId = 0
    ): array {
        $field =
            strtolower(
                trim($field)
            );

        $destination =
            trim($destination);

        if (
            !in_array(
                $field,
                [
                    'email',
                    'mobile',
                ],
                true
            )
            || $destination === ''
            || preg_match(
                '/^\d{6}$/D',
                $code
            ) !== 1
        ) {
            return [
                'ok' => false,
                'status' =>
                    'invalid_destination',
            ];
        }

        $channel =
            $field === 'email'
                ? 'email'
                : 'sms';

        /*
         * SMS_POLICY_IDENTITY_OTP_GATE_V1
         *
         * The policy is checked before template rendering
         * and before the transport is touched.
         */
        if ($channel === 'sms') {
            $policy =
                (new SmsDeliveryPolicyService())
                    ->decision();

            if (!($policy['allowed'] ?? false)) {
                return [
                    'ok' => false,
                    'status' =>
                        (string) (
                            $policy['status']
                            ?? 'sms_window_closed'
                        ),
                    'next_allowed_at' =>
                        $policy[
                            'next_allowed_at'
                        ] ?? null,
                ];
            }
        }

        $templateCode =
            trim(
                (string) (
                    $templateCode
                    ?? ''
                )
            );

        if ($templateCode === '') {
            $templateCode =
                $field === 'email'
                    ? 'auth.identity.email_verification'
                    : 'auth.identity.mobile_verification';
        }

        try {
            $message =
                $this->templates->render(
                    $templateCode,
                    $channel,
                    array_merge(
                        $variables,
                        [
                            'code' =>
                                $code,
                            'expires_minutes' =>
                                '5',
                        ]
                    )
                );
        } catch (Throwable) {
            return [
                'ok' => false,
                'status' =>
                    'template_unavailable',
            ];
        }

        $configured =
            $field === 'email'
                ? $actorUserId > 0
                : $this->smsConfigured();

        if (!$configured) {
            if (
                $this->devExposeToken()
            ) {
                return [
                    'ok' => true,
                    'status' =>
                        'dev_token_exposed',
                    'dev_token' =>
                        $code,
                ];
            }

            return [
                'ok' => false,
                'status' =>
                    'not_configured',
            ];
        }

        $sent =
            $field === 'email'
                ? $this->sendEmail(
                    $actorUserId,
                    $destination,
                    (string) (
                        $message['title']
                        ?? ''
                    ),
                    (string) (
                        $message['body']
                        ?? ''
                    )
                )
                : $this->sendSms(
                    $destination,
                    (string) (
                        $message['body']
                        ?? ''
                    )
                );

        if ($sent) {
            return [
                'ok' => true,
                'status' => 'sent',
                'dev_token' =>
                    $this->devExposeToken()
                        ? $code
                        : null,
            ];
        }

        if (
            $this->devExposeToken()
        ) {
            return [
                'ok' => true,
                'status' =>
                    'dev_token_exposed',
                'dev_token' =>
                    $code,
            ];
        }

        return [
            'ok' => false,
            'status' =>
                'delivery_failed',
        ];
    }

    private function smsConfigured(): bool
    {
        return filter_var(
            Env::get(
                'MFA_SMS_ENABLED',
                false
            ),
            FILTER_VALIDATE_BOOLEAN
        )
            && trim(
                (string) Env::get(
                    'KAVENEGAR_API_KEY',
                    ''
                )
            ) !== ''
            && trim(
                (string) Env::get(
                    'KAVENEGAR_SENDER',
                    ''
                )
            ) !== ''
            && function_exists(
                'curl_init'
            );
    }

    private function sendEmail(
        int $actorUserId,
        string $destination,
        string $subject,
        string $body
    ): bool {
        if (
            $actorUserId < 1
            || $subject === ''
            || $body === ''
        ) {
            return false;
        }

        try {
            $result =
                $this->gateway->sendDirect(
                    $actorUserId,
                    [
                        'channel_code' =>
                            'email',
                        'purpose_code' =>
                            'identity_email_verification',
                        'scope_type' =>
                            'global',
                        'scope_reference' =>
                            '*',
                        'destination' =>
                            $destination,
                        'subject' =>
                            $subject,
                        'body' =>
                            $body,
                        'message_type_code' =>
                            'text',
                        'recipient_user_id' =>
                            $actorUserId,
                    ]
                );

            return
                (string) (
                    $result['status_code']
                    ?? ''
                ) ===
                'notification_gateway_sent';

        } catch (Throwable) {
            return false;
        }
    }

    private function sendSms(
        string $destination,
        string $message
    ): bool {
        if ($message === '') {
            return false;
        }

        $apiKey =
            rawurlencode(
                (string) Env::get(
                    'KAVENEGAR_API_KEY',
                    ''
                )
            );

        $sender =
            rawurlencode(
                (string) Env::get(
                    'KAVENEGAR_SENDER',
                    ''
                )
            );

        $receptor =
            rawurlencode(
                $destination
            );

        $message =
            rawurlencode(
                $message
            );

        $url =
            "https://api.kavenegar.com/v1/"
            . "{$apiKey}/sms/send.json"
            . "?sender={$sender}"
            . "&receptor={$receptor}"
            . "&message={$message}";

        $curl =
            curl_init($url);

        if ($curl === false) {
            return false;
        }

        curl_setopt(
            $curl,
            CURLOPT_RETURNTRANSFER,
            true
        );

        curl_setopt(
            $curl,
            CURLOPT_CONNECTTIMEOUT,
            5
        );

        curl_setopt(
            $curl,
            CURLOPT_TIMEOUT,
            10
        );

        curl_exec($curl);

        $status =
            (int) curl_getinfo(
                $curl,
                CURLINFO_RESPONSE_CODE
            );

        curl_close($curl);

        return $status >= 200
            && $status < 300;
    }

    private function devExposeToken(): bool
    {
        return Env::get(
            'APP_ENV',
            'production'
        ) === 'development'
            && Env::isDebug()
            && filter_var(
                Env::get(
                    'IDENTITY_DEV_EXPOSE_TOKEN',
                    false
                ),
                FILTER_VALIDATE_BOOLEAN
            );
    }
}
