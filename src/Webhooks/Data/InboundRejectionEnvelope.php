<?php

namespace Lettermint\Laravel\Webhooks\Data;

/**
 * SMTP envelope data of a rejected inbound message. A field is null when the session did not
 * provide a usable value; a null mailFrom is an empty sender (<>), as bounces use.
 */
final readonly class InboundRejectionEnvelope
{
    public function __construct(
        public ?string $mailFrom,
        public ?string $remoteIp,
        public ?string $remoteHostname,
        public ?string $helo,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            mailFrom: Field::string($data, 'mail_from'),
            remoteIp: Field::string($data, 'remote_ip'),
            remoteHostname: Field::string($data, 'remote_hostname'),
            helo: Field::string($data, 'helo'),
        );
    }
}
