<?php
declare(strict_types=1);
namespace App\Services;

use App\Repositories\NotificationMessengerEnrollmentRepository;
use IPKF\Database\Database;
use PDO;
use Throwable;

/** A ticket link is a locator only. No authentication or authorization bypass. */
final class BaleTicketDeepLinkService
{
    public static function issue(string $botSecret, int $userId, int $providerId, string $reference): string
    {
        if ($botSecret==='' || $userId<1 || $providerId<1 ||
            preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$reference)!==1) {
            throw new \RuntimeException('INVALID_TICKET_LINK_REQUEST');
        }
        $payload=json_encode([
            'v'=>1,'u'=>$userId,'p'=>$providerId,'r'=>$reference,
            'e'=>time()+1200,'n'=>bin2hex(random_bytes(12))
        ], JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
        $body=rtrim(strtr(base64_encode($payload),'+/','-_'),'=');
        $mac=hash_hmac('sha256',"bale-ticket-deeplink-v1\n".$body,$botSecret);
        return $body.'.'.$mac;
    }

    public static function referenceForAuthenticatedUser(string $token, int $currentUserId): ?string
    {
        if ($currentUserId<1 || strlen($token)>650 ||
            preg_match('/^([A-Za-z0-9_-]{40,500})\.([a-f0-9]{64})$/D',$token,$m)!==1) return null;
        $json=base64_decode(strtr($m[1],'-_','+/').str_repeat('=',(4-strlen($m[1])%4)%4),true);
        if (!is_string($json) || strlen($json)>512) return null;
        try {$payload=json_decode($json,true,8,JSON_THROW_ON_ERROR);} catch (Throwable) {return null;}
        if (!is_array($payload) || ($payload['v']??null)!==1 ||
            !is_int($payload['u']??null) || $payload['u']!==$currentUserId ||
            !is_int($payload['p']??null) || $payload['p']<1 ||
            !is_int($payload['e']??null) || $payload['e']<=time() || $payload['e']>time()+1200 ||
            !is_string($payload['n']??null) || preg_match('/^[a-f0-9]{24}$/D',$payload['n'])!==1 ||
            !is_string($payload['r']??null) || preg_match('/^[A-Za-z0-9_-]{3,100}$/D',$payload['r'])!==1) return null;
        $repository=new NotificationMessengerEnrollmentRepository();
        $runtime=new NotificationProviderRuntimeService();
        $providerMatched=false;
        foreach ($repository->serviceAccessBaleProviders() as $provider) {
            if ((int)($provider['id']??0)!==$payload['p']) continue;
            $secret=(string)($runtime->secrets($provider)['bot_token']??'');
            if ($secret==='') return null;
            $expected=hash_hmac('sha256',"bale-ticket-deeplink-v1\n".$m[1],$secret);
            if (!hash_equals($expected,$m[2])) return null;
            $providerMatched=true;
            break;
        }
        if (!$providerMatched) return null;
        $stmt=Database::connect()->prepare(
            "SELECT 1 FROM notification_messenger_bindings WHERE provider_instance_id=? AND user_id=? AND status_code='active' LIMIT 1"
        );
        $stmt->execute([$payload['p'],$currentUserId]);
        if ($stmt->fetchColumn()===false) return null;
        $detail=(new \App\Services\Ticketing\TicketService())
            ->detailForUser($payload['r'],$currentUserId);
        return is_array($detail) && is_array($detail['ticket']??null) ? $payload['r'] : null;
    }
}
