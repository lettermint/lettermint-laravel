<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class BotDetectionData
{
    /**
     * @param  array<string>  $reasonCodes
     */
    public function __construct(
        public bool $detected,
        public float $probability,
        public string $classification,
        public ?string $proxyType,
        public array $reasonCodes,
        public bool $machine,
        public bool $countsForMetrics,
    ) {}

    /**
     * @param  array{detected?: bool, probability?: int|float, classification?: string, proxy_type?: string|null, reason_codes?: array<string>, machine?: bool, counts_for_metrics?: bool}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            detected: $data['detected'] ?? false,
            probability: (float) ($data['probability'] ?? 0),
            classification: $data['classification'] ?? 'unknown',
            proxyType: $data['proxy_type'] ?? null,
            reasonCodes: $data['reason_codes'] ?? [],
            machine: $data['machine'] ?? false,
            countsForMetrics: $data['counts_for_metrics'] ?? true,
        );
    }
}
