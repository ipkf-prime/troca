<?php

namespace App\Services;

use App\Repositories\CommunicationSettingsRepository;

class CommunicationSettingsService extends BaseService
{
    private const SECTIONS = [
        'providers' => [
            'title' => 'سرویس‌دهنده‌ها',
            'permission' => 'notifications.providers.manage',
        ],
        'defaults' => [
            'title' => 'پیش‌فرض سرویس‌دهنده‌ها',
            'permission' => 'notifications.providers.manage',
        ],
        'routing' => [
            'title' => 'قواعد ارسال',
            'permission' => 'notifications.routing.manage',
        ],
        'preferences' => [
            'title' => 'روش‌های دریافت اعلان',
            'permission' => 'notifications.preferences.self',
        ],
        'bale_connections' => [
            'title' => 'اتصال کاربران به بله',
            'permission' => 'notifications.send.manage',
        ],
        'approvals' => [
            'title' => 'تأیید اعلان‌ها',
            'permission' => 'notifications.approvals.view',
        ],
        'send' => [
            'title' => 'ارسال اعلان',
            'permission' => 'notifications.send.view',
        ],
        'reports' => [
            'title' => 'گزارش ارسال و تحویل',
            'permission' => 'notifications.reports.view',
        ],
        'internal' => [
            'title' => 'پیام‌رسان داخلی',
            'permission' => 'messages.admin.manage',
        ],
    ];

    public function __construct(
        private ?CommunicationSettingsRepository $repository = null,
        private ?AuthorizationService $authorization = null,
        private ?NotificationProviderManagementService $providers = null,
        private ?NotificationProviderDefaultService $providerDefaults = null,
        private ?NotificationDeliveryReportService $deliveryReports = null,
        private ?NotificationSendCenterService $sendCenter = null,
        private ?NotificationBaleConnectionManagementService $baleConnections = null,
        private ?NotificationApprovalManagementService $approvalManagement = null
    ) {
        $this->repository ??= new CommunicationSettingsRepository();
        $this->authorization ??= new AuthorizationService();
        $this->providers ??= new NotificationProviderManagementService();
        $this->providerDefaults ??= new NotificationProviderDefaultService();
        $this->deliveryReports ??= new NotificationDeliveryReportService();
        $this->sendCenter ??= new NotificationSendCenterService();
        $this->baleConnections ??=
            new NotificationBaleConnectionManagementService();
        $this->approvalManagement ??=
            new NotificationApprovalManagementService();
    }

    public function allowedSections(int $userId): array
    {
        $sections = [];

        foreach (self::SECTIONS as $code => $definition) {
            if ($this->authorization->hasPermission(
                $userId,
                $definition['permission']
            )) {
                $sections[$code] = $definition['title'];
            }
        }

        return $sections;
    }

    public function page(
        int $userId,
        string $section,
        string $editProviderReference = '',
        array $reportFilters = []
    ): array {
        $sections = $this->allowedSections($userId);

        if ($sections === []) {
            return [
                'allowed' => false,
                'section' => '',
                'sections' => [],
            ];
        }

        if (!array_key_exists($section, $sections)) {
            $section = (string) array_key_first($sections);
        }

        $providersAllowed = isset($sections['providers'])
            || isset($sections['defaults']);
        $routingAllowed = isset($sections['routing']);
        $preferencesAllowed = isset($sections['preferences']);
        $reportsAllowed = isset($sections['reports']);

        $providerManagement = [];
        $providerDefaultManagement = [];
        $deliveryReport = [];
        $notificationSendCenter = [];
        $baleConnectionManagement = [];
        $notificationApprovalManagement = [];

        if (
            $section === 'providers'
            && isset($sections['providers'])
        ) {
            $providerManagement = $this->providers->page(
                $userId,
                $editProviderReference
            );
        }

        if (
            $section === 'defaults'
            && isset($sections['defaults'])
        ) {
            $providerDefaultManagement =
                $this->providerDefaults->page($userId);
        }

        if (
            $section === 'bale_connections'
            && isset($sections['bale_connections'])
        ) {
            $baleConnectionManagement =
                $this->baleConnections->page($userId);
        }

        if (
            $section === 'approvals'
            && isset($sections['approvals'])
        ) {
            $notificationApprovalManagement = [
                'items' =>
                    $this->approvalManagement
                        ->queue($userId),
                'history' =>
                    $this->approvalManagement
                        ->history(
                            $userId,
                            $reportFilters
                        ),
                'can_decide' =>
                    $this->authorization
                        ->hasPermission(
                            $userId,
                            'notifications.approvals.decide'
                        ),

                'can_manage' =>
                    $this->authorization
                        ->hasPermission(
                            $userId,
                            'notifications.approvals.manage'
                        ),
            ];
        }

        if (
            $section === 'send'
            && isset($sections['send'])
        ) {
            $notificationSendCenter =
                $this->sendCenter->page($userId);
        }

        if (
            $section === 'reports'
            && $reportsAllowed
        ) {
            $deliveryReport = $this->deliveryReports->page(
                $userId,
                $reportFilters
            );
        }

        return [
            'allowed' => true,
            'section' => $section,
            'sections' => $sections,
            'provider_types' => $providersAllowed
                ? $this->repository->providerTypes()
                : [],
            'provider_instances' => $providersAllowed
                ? $this->repository->providerInstances()
                : [],
            'provider_management' => $providerManagement,
            'provider_default_management' =>
                $providerDefaultManagement,
            'provider_defaults' => $providersAllowed
                ? $this->repository->providerDefaults()
                : [],
            'routing_rules' => $routingAllowed
                ? $this->repository->routingRules()
                : [],

            'sms_policy' => $routingAllowed
                ? (new SmsDeliveryPolicyService())
                    ->settings()
                : [],
            'events' => $routingAllowed
                ? $this->repository->events()
                : [],
            'channels' => $preferencesAllowed
                ? $this->repository->channels()
                : [],
            'preferences' => $preferencesAllowed
                ? $this->repository->preferences($userId)
                : [],
            'notification_send_center' =>
                $notificationSendCenter,
            'notification_approval_management' =>
                $notificationApprovalManagement,
            'bale_connection_management' =>
                $baleConnectionManagement,
            'delivery_report' => $deliveryReport,
            'deliveries' => is_array(
                $deliveryReport['items'] ?? null
            ) ? $deliveryReport['items'] : [],
            'message_settings' => $section === 'internal'
                ? (new InternalMessageAdministrationService())
                    ->settings($userId)
                : [],
        ];
    }

    public function saveProviderDefaults(
        int $userId,
        mixed $defaults
    ): void {
        if (!isset(
            $this->allowedSections($userId)['defaults']
        )) {
            throw new \RuntimeException(
                'provider_management_forbidden'
            );
        }

        $this->providerDefaults->save(
            $userId,
            $defaults
        );
    }

    public function saveSmsPolicy(
        int $userId,
        array $input
    ): array {
        if (!isset(
            $this->allowedSections(
                $userId
            )['routing']
        )) {
            throw new \RuntimeException(
                'sms_policy_forbidden'
            );
        }

        return (
            new SmsDeliveryPolicyService()
        )->save($input);
    }

    public function savePreferences(
        int $userId,
        mixed $channels
    ): void {
        if (!isset(
            $this->allowedSections($userId)['preferences']
        )) {
            return;
        }

        $allowed = array_map(
            static fn (array $channel): string =>
                (string) $channel['code'],
            $this->repository->channels()
        );

        $enabled = is_array($channels)
            ? array_values(array_unique(array_filter(
                array_map('strval', $channels),
                static fn (string $channel): bool =>
                    in_array($channel, $allowed, true)
            )))
            : [];

        $this->repository->savePreferences(
            $userId,
            $enabled
        );
    }
}
