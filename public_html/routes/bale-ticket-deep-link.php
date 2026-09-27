<?php
declare(strict_types=1);

/* Read-only, session-bound redirect; link bearer alone grants no ticket visibility. */
$router->get('/admin/bale/ticket/open', function ($request,$response) {
    $response->header('Cache-Control','no-store, private')
        ->header('Referrer-Policy','no-referrer')
        ->header('X-Robots-Tag','noindex, nofollow');
    $userId=(new \App\Services\AuthService())->currentUserId();
    if ($userId===null || (int)$userId<1) {
        // Existing login does not preserve arbitrary return URLs. Reopen the Bale button after login.
        return $response->redirect('/admin/login');
    }
    $token=$request->input('t','');
    if (!is_string($token)) return $response->redirect('/admin/support/ticketing/membership');
    try {
        $reference=\App\Services\BaleTicketDeepLinkService::referenceForAuthenticatedUser($token,(int)$userId);
    } catch (\Throwable) {
        $reference=null;
    }
    if ($reference===null) {
        return $response->redirect('/admin/support/ticketing/membership?status=bale_link_unavailable');
    }
    return $response->redirect('/admin/ticketing/tickets/'.rawurlencode($reference));
});
