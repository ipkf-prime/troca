<?php

namespace App\Services;

use IPKF\Support\Env;

class IdentityOtpDeliveryService extends BaseService
{
    public function deliver(
        string $field,
        string $destination,
        string $code
    ): array {
        $field = strtolower(trim($field));
        $destination = trim($destination);

        if (
            !in_array($field, ['email', 'mobile'], true)
            || $destination === ''
            || preg_match('/^\d{6}$/', $code) !== 1
        ) {
            return [
                'ok' => false,
                'status' => 'invalid_destination',
            ];
        }

        $configured = $field === 'email'
            ? $this->emailConfigured()
            : $this->smsConfigured();

        if (!$configured) {
            if ($this->devExposeToken()) {
                return [
                    'ok' => true,
                    'status' => 'dev_token_exposed',
                    'dev_token' => $code,
                ];
            }

            return [
                'ok' => false,
                'status' => 'not_configured',
            ];
        }

        $sent = $field === 'email'
            ? $this->sendEmail($destination, $code)
            : $this->sendSms($destination, $code);

        if ($sent) {
            return [
                'ok' => true,
                'status' => 'sent',
                'dev_token' => $this->devExposeToken()
                    ? $code
                    : null,
            ];
        }

        if ($this->devExposeToken()) {
            return [
                'ok' => true,
                'status' => 'dev_token_exposed',
                'dev_token' => $code,
            ];
        }

        return [
            'ok' => false,
            'status' => 'delivery_failed',
        ];
    }

    private function emailConfigured(): bool
    {
        return trim((string) Env::get(
            'MAIL_FROM_ADDRESS',
            ''
        )) !== ''
            && function_exists('mail');
    }

    private function smsConfigured(): bool
    {
        return filter_var(
            Env::get('MFA_SMS_ENABLED', false),
            FILTER_VALIDATE_BOOLEAN
        )
            && trim((string) Env::get(
                'KAVENEGAR_API_KEY',
                ''
            )) !== ''
            && trim((string) Env::get(
                'KAVENEGAR_SENDER',
                ''
            )) !== ''
            && function_exists('curl_init');
    }

    private function sendEmail(
        string $destination,
        string $code
    ): bool {
        $from = trim((string) Env::get(
            'MAIL_FROM_ADDRESS',
            ''
        ));
        $subject = 'کد تأیید حساب IPKF';
        $body = "کد تأیید شما: {$code}\n"
            . "اعتبار کد ۵ دقیقه است.";

        $encodedSubject = '=?UTF-8?B?'
            . base64_encode($subject)
            . '?=';
        $headers = implode("\r\n", [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ]);

        return @mail(
            $destination,
            $encodedSubject,
            $body,
            $headers
        );
    }

    private function sendSms(
        string $destination,
        string $code
    ): bool {
        $apiKey = rawurlencode((string) Env::get(
            'KAVENEGAR_API_KEY',
            ''
        ));
        $sender = rawurlencode((string) Env::get(
            'KAVENEGAR_SENDER',
            ''
        ));
        $receptor = rawurlencode($destination);
        $message = rawurlencode(
            "کد تأیید IPKF: {$code}"
        );
        $url = "https://api.kavenegar.com/v1/"
            . "{$apiKey}/sms/send.json"
            . "?sender={$sender}"
            . "&receptor={$receptor}"
            . "&message={$message}";

        $curl = curl_init($url);

        if ($curl === false) {
            return false;
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_exec($curl);
        $status = (int) curl_getinfo(
            $curl,
            CURLINFO_RESPONSE_CODE
        );
        curl_close($curl);

        return $status >= 200 && $status < 300;
    }

    private function devExposeToken(): bool
    {
        return Env::get('APP_ENV', 'production')
                === 'development'
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
