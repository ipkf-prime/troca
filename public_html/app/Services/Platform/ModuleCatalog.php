<?php

namespace App\Services\Platform;

class ModuleCatalog
{
    public function coreModuleCodes(): array
    {
        return [
            'core.identity',
            'core.access',
            'core.organization',
            'core.geography',
            'core.platform_registry',
            'core.licensing',
        ];
    }

    public function automationModuleCodes(): array
    {
        return [
            'automation.core',
            'automation.correspondence',
            'automation.secretariat',
            'automation.cartable',
            'automation.workflow',
            'automation.forms',
            'automation.leave',
            'automation.mission',
            'automation.procurement_requests',
            'automation.payment_requests',
            'automation.check_requests',
            'automation.document_generation',
            'automation.archive',
            'automation.qr_verification',
            'automation.digital_signature',
            'automation.notifications',
        ];
    }
}
