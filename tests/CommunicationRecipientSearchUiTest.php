<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CommunicationRecipientSearchUiTest extends TestCase
{
    public function testComposeSupportsSearchAndMultipleRecipients(): void
    {
        $view = file_get_contents(__DIR__ . '/../public_html/resources/views/admin/messages-compose.php');
        $service = file_get_contents(__DIR__ . '/../public_html/app/Services/InternalMessageService.php');
        $route = file_get_contents(__DIR__ . '/../public_html/routes/communication-center.php');

        self::assertStringContainsString('data-recipient-search', $view);
        self::assertStringContainsString('recipient_user_ids[]', $view);
        self::assertStringContainsString("query !== '' && !item.dataset.search.includes(query)", $view);
        self::assertStringNotContainsString('query.length < 2', $view);
        self::assertStringContainsString("input('recipient_user_ids', [])", $route);
        self::assertStringContainsString('count($recipientUserIds) > 100', $service);
    }

    public function testCommunicationDatesUseJalaliFormatter(): void
    {
        $notifications = file_get_contents(__DIR__ . '/../public_html/resources/views/admin/notifications.php');
        $settings = file_get_contents(__DIR__ . '/../public_html/resources/views/admin/communication-settings.php');

        self::assertStringContainsString('AdminFormat::jalaliDateTime', $notifications);
        self::assertStringContainsString('AdminFormat::jalaliDateTime', $settings);
        self::assertStringContainsString("'in_app' => 'پیام‌رسان داخلی'", $settings);
        self::assertStringContainsString("'delivered' => 'تحویل‌شده'", $settings);
        self::assertStringContainsString('$displayDeliveryDate', $settings);
    }

    public function testSettingsRouteAuthorizesSectionsThroughSettingsService(): void
    {
        $route = file_get_contents(__DIR__ . '/../public_html/routes/communication-center.php');

        self::assertStringContainsString('$page = $settings->page(', $route);
        self::assertStringContainsString("(\$page['allowed'] ?? false) !== true", $route);
        self::assertStringNotContainsString("\$itemQuery['section']", $route);
    }
}
