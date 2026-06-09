<?php

namespace App\Services\Multiposting;

class JobBoardAutomationResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $errorMessage = null,
        public readonly ?string $screenshotPath = null,
        public readonly ?string $externalUrl = null,
        public readonly ?string $failureCode = null,
        public readonly array $raw = []
    ) {
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function success(?string $externalUrl = null, array $raw = []): self
    {
        return new self(true, null, null, $externalUrl, null, $raw);
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function failure(
        string $message,
        ?string $screenshotPath = null,
        array $raw = [],
        ?string $failureCode = null
    ): self {
        return new self(false, $message, $screenshotPath, null, $failureCode, $raw);
    }
}
