<?php

namespace Lettermint\Laravel\Webhooks\Data;

final readonly class BotDetectionData
{
    /**
     * @param  list<string>  $reasonCodes
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
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            detected: Field::bool($data, 'detected', false),
            probability: Field::float($data, 'probability') ?? 0.0,
            classification: Field::string($data, 'classification') ?? 'unknown',
            proxyType: Field::string($data, 'proxy_type'),
            reasonCodes: Field::strings($data, 'reason_codes'),
            machine: Field::bool($data, 'machine', false),
            countsForMetrics: Field::bool($data, 'counts_for_metrics', true),
        );
    }
}
