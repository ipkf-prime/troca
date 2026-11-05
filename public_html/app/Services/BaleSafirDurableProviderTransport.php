<?php
declare(strict_types=1);
namespace App\Services;

use RuntimeException;

/**
 * SAFIR v3 transport preserving the existing successful provider contract.
 * Sending is never initiated by constructing this class; caller must supply
 * explicit approved campaign guard and atomic idempotency reservation.
 */
final class BaleSafirDurableProviderTransport
{
    public function __construct(private readonly string $apiAccessKey)
    {
        if (trim($apiAccessKey)==='') {
            throw new RuntimeException('safir_provider_credential_missing');
        }
    }

    /**
     * @param array<string,mixed> $payload Provider-approved template_id + text_fields.
     * @return array{accepted:bool,message_id:?string}
     */
    public function send(string $phone,array $payload,string $requestId): array
    {
        if (!extension_loaded('curl') || !preg_match('/^989[0-9]{9}$/D',$phone)
            || ($payload['request_id'] ?? null)!==$requestId
            || ($payload['phone_number'] ?? null)!==$phone
            || !is_array($payload['message_data']['template_message'] ?? null)
            || !is_string($payload['message_data']['template_message']['template_id'] ?? null)
            || !is_array($payload['message_data']['template_message']['text_fields'] ?? null)
                && !is_object($payload['message_data']['template_message']['text_fields'] ?? null)) {
            throw new RuntimeException('safir_provider_payload_invalid');
        }
        $body=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $curl=curl_init('https://safir.bale.ai/api/v3/send_message');
        if ($curl===false) throw new RuntimeException('safir_provider_curl_unavailable');
        curl_setopt_array($curl,[
            CURLOPT_POST=>true,
            CURLOPT_POSTFIELDS=>$body,
            CURLOPT_HTTPHEADER=>[
                'api-access-key: '.$this->apiAccessKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_CONNECTTIMEOUT=>5,
            CURLOPT_TIMEOUT=>12,
            CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYPEER=>true,
            CURLOPT_SSL_VERIFYHOST=>2,
        ]);
        if (defined('CURLOPT_PROTOCOLS')) {
            curl_setopt($curl,CURLOPT_PROTOCOLS,CURLPROTO_HTTPS);
        }
        try {
            $response=curl_exec($curl);
            $http=(int)curl_getinfo($curl,CURLINFO_RESPONSE_CODE);
        } finally {
            curl_close($curl);
        }
        if (!is_string($response)) return ['accepted'=>false,'message_id'=>null];
        $parsed=json_decode($response,true);
        if (!is_array($parsed)) return ['accepted'=>false,'message_id'=>null];
        $errors=$parsed['error_data'] ?? null;
        $message=trim((string)($parsed['message_id'] ?? ($parsed['data']['message_id'] ?? '')));
        $accepted=$http>=200 && $http<300 && $message!==''
            && ($errors===null || $errors===[] || $errors==='');
        return [
            'accepted'=>$accepted,
            'message_id'=>$accepted ? $message : null,
        ];
    }
}
