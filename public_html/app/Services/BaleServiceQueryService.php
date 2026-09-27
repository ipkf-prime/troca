<?php
declare(strict_types=1);

namespace App\Services;

use App\Repositories\NotificationMessengerEnrollmentRepository;
use App\Services\Ticketing\TicketService;
use IPKF\Database\Database;
use PDO;
use RuntimeException;
use Throwable;

final class BaleServiceQueryService
{
    public function run(string $body, string $timestamp, string $signature): array
    {
        if (strlen($body) > 4096 || strlen($body) < 2 ||
            preg_match('/^[0-9]{10}$/D', $timestamp) !== 1 ||
            abs(time() - (int) $timestamp) > 120 ||
            preg_match('/^[a-f0-9]{64}$/D', $signature) !== 1) {
            throw new RuntimeException('INVALID_SERVICE_REQUEST');
        }
        $repository = new NotificationMessengerEnrollmentRepository();
        $runtime = new NotificationProviderRuntimeService();
        $matched = null;
        $matchedToken = null;
        foreach ($repository->serviceAccessBaleProviders() as $provider) {
            $candidate = (string) ($runtime->secrets($provider)['bot_token'] ?? '');
            if ($candidate === '') { continue; }
            $expected = hash_hmac('sha256', $timestamp . "\n" . hash('sha256', $body), $candidate);
            if (!hash_equals($expected, $signature)) { continue; }
            if ($matched !== null) { throw new RuntimeException('AMBIGUOUS_BOT'); }
            $matched = $provider;
            $matchedToken = $candidate;
        }
        if ($matched === null || $matchedToken === null) {
            throw new RuntimeException('UNAUTHORIZED_SERVICE_REQUEST');
        }
        $dialog = BaleDialogContentService::forProvider($matched);
        $input = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($input) || ($input['chat_type'] ?? null) !== 'private') {
            throw new RuntimeException('INVALID_INPUT');
        }
        foreach (['chat_id', 'sender_id', 'update_id'] as $key) {
            if (preg_match('/^[0-9]{1,20}$/D', (string)($input[$key] ?? '')) !== 1) {
                throw new RuntimeException('INVALID_ID');
            }
        }
        $command = $input['command'] ?? null;
        if (!is_string($command) || strlen($command) > 1400 ||
            !(preg_match('~^/(?:services|tickets(?:\s+[1-9][0-9]{0,3})?|ticket-new|ticket(?:\s+[A-Za-z0-9_-]{3,100})?)$~D', $command) === 1 ||
              \App\Services\BaleTicketReplyService::parse($command) !== null ||
              preg_match('~^/resolve [A-Za-z0-9_-]{3,100}$~D',$command)===1)) {
            throw new RuntimeException('INVALID_COMMAND');
        }
        $chatId = (string) $input['chat_id'];
        $senderId = (string) $input['sender_id'];
        $db = Database::connect();
        $binding = $db->prepare(
            "SELECT user_id FROM notification_messenger_bindings " .
            "WHERE provider_instance_id = ? AND chat_id = ? AND external_user_id = ? " .
            "AND status_code = 'active' LIMIT 1"
        );
        $binding->execute([(int)$matched['id'], $chatId, $senderId]);
        $userId = (int)$binding->fetchColumn();
        $reply = '';
        $path = null;
        $choices = [];
        $ticketPage = 1;
        $hasMore = false;
        if ($userId < 1) {
            $reply = BaleDialogContentService::text($dialog, 'core.text_01');
            $path = '/admin/account/bale';
        } else {
            try {
                $ticketing = new TicketService();
        $supportAccess = new BaleSupportAccessService();
                if (str_starts_with($command, '/resolve ')) {
                    $submitted=(new BaleTicketReplyService())->resolve(
                        $db, (int)$matched['id'], $userId, $input,
                        substr($command,strlen('/resolve ')), $dialog
                    );
                    $reply=(string)$submitted['reply'];
                    $path=$submitted['link_path'];
                } elseif (str_starts_with($command, '/reply ')) {
                    $submitted = (new BaleTicketReplyService())->submit(
                        $db, (int)$matched['id'], $userId, $input, $command, $dialog
                    );
                    $reply = (string)$submitted['reply'];
                    $path = $submitted['link_path'];
                } elseif ($command === '/services') {
                    $form = $ticketing->form([], $userId);
                    $projects = $form['options']['projects'] ?? [];
                    $ticketList = $ticketing->myTickets($userId);
                    $hasTickets = !empty($ticketList['items']);
                    if ($projects !== [] || $hasTickets) {
                        $reply = BaleDialogContentService::text($dialog, 'core.text_02');
                        if ($projects !== []) { $reply .= BaleDialogContentService::text($dialog, 'core.text_03'); }
                        $reply .= BaleDialogContentService::text($dialog, 'core.text_04');
                    } else {
                        $reply = BaleDialogContentService::text($dialog, 'core.text_05');
                        $path = '/admin/support/ticketing/membership';
                    }
                } elseif ($command === '/ticket-new') {
                    $form = $ticketing->form([], $userId);
                    if (empty($form['options']['projects'])) {
                        $reply = BaleDialogContentService::text($dialog, 'core.text_06');
                        $path = '/admin/support/ticketing/membership';
                    } else {
                        $reply = BaleDialogContentService::text($dialog, 'core.text_07');
                        $path = '/admin/ticketing/tickets/create';
                    }
                } elseif (preg_match('~^/tickets(?:\s+([1-9][0-9]{0,3}))?$~D', $command, $pageMatch) === 1) {
                    // Open means the current Ticketing status has is_closed=0,
                    // not a guessed set of literal status codes or display labels.
                    $ticketPage = isset($pageMatch[1]) ? (int)$pageMatch[1] : 1;
                    $statuses = (new \App\Repositories\TicketRepository())->statuses();
                    $openCodes = [];
                    foreach ($statuses as $statusRow) {
                        if (is_array($statusRow) && (int)($statusRow['is_closed'] ?? 1) === 0) {
                            $openCodes[(string)$statusRow['code']] = true;
                        }
                    }
                    $result = $ticketing->myTickets($userId);
                    $items = is_array($result['items'] ?? null) ? $result['items'] : [];
                    $open = array_values(array_filter($items, static function ($item) use ($openCodes): bool {
                        return is_array($item) && isset($openCodes[(string)($item['status_code'] ?? '')]);
                    }));
                    $offset = ($ticketPage - 1) * 8;
                    $pageItems = array_slice($open, $offset, 8);
                    $hasMore = count($open) > $offset + count($pageItems);
                    if ($open === []) {
                        $reply = BaleDialogContentService::text($dialog, 'core.text_08');
                        $available = $ticketing->form([], $userId);
                        if (empty($available['options']['projects'])) {
                            $reply .= BaleDialogContentService::text($dialog, 'core.text_09');
                            $path = '/admin/support/ticketing/membership';
                        } else {
                            $reply .= BaleDialogContentService::text($dialog, 'core.text_10');
                            $path = '/admin/ticketing/tickets/create';
                        }
                    } elseif ($pageItems === []) {
                        $reply = BaleDialogContentService::text($dialog, 'core.text_11');
                    } else {
                        $reply = BaleDialogContentService::text($dialog,'core.tickets_page',['page'=>(string)$ticketPage]);
                        foreach ($pageItems as $item) {
                            $reference = (string)($item['public_reference'] ?? '');
                            if (preg_match('/^[A-Za-z0-9_-]{3,100}$/D', $reference) !== 1) { continue; }
                            $number = preg_replace('/[\r\n\x00-\x1f]+/u', ' ', (string)($item['ticket_number'] ?? ''));
                            $subject = preg_replace('/[\r\n\x00-\x1f]+/u', ' ', (string)($item['subject'] ?? ''));
                            $number = mb_substr(trim((string)$number), 0, 26, 'UTF-8');
                            $subject = mb_substr(trim((string)$subject), 0, 54, 'UTF-8');
                            $label = trim($number . ' — ' . $subject);
                            if ($label === '') { continue; }
                            $choices[] = ['reference' => $reference, 'label' => $label];
                        }
                        if ($choices === []) {
                            $reply = BaleDialogContentService::text($dialog, 'core.text_12');
                        }
                    }
                } else {
                    $ref = trim(substr($command, strlen('/ticket')));
                    $detail = $ref !== '' ? $supportAccess->ticketForUser($ref, $userId) : null;
                    if (!is_array($detail) || !is_array($detail['ticket'] ?? null)) {
                        $reply = BaleDialogContentService::text($dialog, 'core.text_13');
                    } else {
                        $ticket = $detail['ticket'];
                        $number = (string)($ticket['ticket_number'] ?? $ref);
                        $subject = preg_replace('/[\r\n\x00-\x1f]+/u', ' ', (string)($ticket['subject'] ?? ''));
                        $status = (string)($ticket['status_title'] ?? $ticket['status_code'] ?? '');
                        $reply = BaleDialogContentService::text($dialog,'core.ticket_detail', ['number'=>$number,'subject'=>mb_substr((string)$subject,0,180,'UTF-8'),'status'=>$status]);
                        $path = '/admin/ticketing/tickets/' . rawurlencode($ref);
                    }
                }
            } catch (Throwable) {
                $reply = BaleDialogContentService::text($dialog, 'core.text_14');
                $path = null;
            }
        }
        // Replace only an already-authorized requester ticket destination.
        // Link possession alone never grants access: session, active Bale binding,
        // and canonical requester visibility are all checked at click time.
        if ($userId > 0 && is_string($path) &&
            preg_match('~^/admin/ticketing/tickets/([A-Za-z0-9_-]{3,100})$~D', $path, $linkMatch)===1) {
            $visible = $supportAccess->ticketForUser($linkMatch[1], $userId);
            $path = is_array($visible) && is_array($visible['ticket']??null)
                ? '/admin/bale/ticket/open?t=' . BaleTicketDeepLinkService::issue(
                    $matchedToken, $userId, (int)$matched['id'], $linkMatch[1])
                : null;
        }
        $reply = mb_substr($reply, 0, 3600, 'UTF-8');
        $payload = [
            'ok' => true, 'connected' => $userId > 0,
            'chat_id' => $chatId, 'update_id' => (string)$input['update_id'],
            'reply' => $reply, 'link_path' => $path,
            'ticket_choices' => $choices, 'ticket_page' => $ticketPage, 'has_more' => $hasMore,
        ];
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        return [
            'json' => $json,
            'signature' => hash_hmac('sha256', hash('sha256', $body) . "\n" . $json, $matchedToken),
        ];
    }
}
