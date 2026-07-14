<?php

namespace App\Services\GeographyCanonicalization;

use RuntimeException;
use Throwable;

class MinistryCanonicalizationApplyException extends RuntimeException
{
    public function __construct(
        private readonly string $failureReference,
        private readonly string $failureStage,
        private readonly int $appliedItemCount,
        Throwable $previous
    ) {
        parent::__construct('Canonical geography apply failed safely and may be resumed.', 0, $previous);
    }

    public function safeResponse(): array
    {
        return [
            'success' => false,
            'message' => 'Canonical geography operation stopped safely.',
            'failure_reference' => $this->failureReference,
            'failure_stage' => $this->failureStage,
            'run_status' => 'failed',
            'resume_safe' => true,
            'applied_item_count' => $this->appliedItemCount,
            'canonical_write_performed' => $this->appliedItemCount > 0,
            'sci_write_performed' => false,
            'bot_write_performed' => false,
        ];
    }
}
