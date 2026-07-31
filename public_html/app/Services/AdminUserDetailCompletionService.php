<?php

namespace App\Services;

use App\Repositories\AdminUserManagementRepository;

class AdminUserDetailCompletionService extends BaseService
{
    public function __construct(
        private ?AdminUserService $users = null,
        private ?AdminUserManagementRepository $management = null
    ) {
        $this->users ??= new AdminUserService();
        $this->management ??= new AdminUserManagementRepository();
    }

    public function workspace(
        int $userId,
        string $tab,
        ?int $viewerId = null
    ): ?array {
        $workspace = $this->users->detailWorkspace(
            $userId,
            $tab,
            $viewerId
        );

        if ($workspace === null || $tab !== 'contacts') {
            return $workspace;
        }

        $fallback = $this->management->detailFallback($userId);
        $content = $workspace['content'] ?? [];
        $contacts = is_array($content['contacts'] ?? null)
            ? $content['contacts']
            : [];
        $addresses = is_array($content['addresses'] ?? null)
            ? $content['addresses']
            : [];

        foreach ($fallback['contacts'] ?? [] as $candidate) {
            $exists = false;
            foreach ($contacts as $contact) {
                if (
                    trim((string) ($contact['value'] ?? '')) !== ''
                    && trim((string) ($contact['value'] ?? ''))
                        === trim((string) ($candidate['value'] ?? ''))
                ) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $contacts[] = $candidate;
            }
        }

        if ($addresses === [] && ($fallback['addresses'] ?? []) !== []) {
            $addresses = $fallback['addresses'];
        }

        $workspace['content']['contacts'] = $contacts;
        $workspace['content']['addresses'] = $addresses;

        return $workspace;
    }
}
