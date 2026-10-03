<?php

namespace Lettermint\Laravel\Transport;

use Lettermint\Exceptions\LettermintException;
use Lettermint\Lettermint;
use LogicException;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Header\MetadataHeader;
use Symfony\Component\Mailer\Header\TagHeader;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Message;
use Symfony\Component\Mime\MessageConverter;

class LettermintTransportFactory extends AbstractTransport
{
    protected const BYPASS_HEADERS = [
        'from',
        'to',
        'cc',
        'bcc',
        'subject',
        'content-type',
        'sender',
        'reply-to',
        'idempotency-key',
        'x-lm-tag',
    ];

    /**
     * Create a new Lettermint transport instance.
     */
    public function __construct(
        protected Lettermint $lettermint,
        protected array $config = []
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     *
     * The transport (and the Lettermint client it holds) lives for the lifetime
     * of the mailer, e.g. a whole queue worker. Each message is therefore built
     * as a local payload and sent in a single emails->send() call together with
     * its idempotency key, so no per-message state can survive a failed send
     * and leak into the next one.
     *
     * @throws TransportException When the SDK rejects the message or the API
     *                            fails; the code is the HTTP status, or 0 when
     *                            no response was received.
     */
    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new LogicException('Lettermint transport requires a Message instance, RawMessage given.');
        }

        $email = MessageConverter::toEmail($original);
        $envelope = $message->getEnvelope();

        if ($email->getSubject() === null) {
            throw new TransportException('Lettermint requires a subject, but the email has none.');
        }

        $attachments = $this->getAttachments($email);

        try {
            $result = $this->lettermint->emails->send(
                $this->buildPayload($email, $envelope, $attachments),
                idempotencyKey: $this->resolveIdempotencyKey($email),
            );
            // get() rather than ->message_id: a response without the field
            // must not set an empty Message-ID.
            $messageId = $result->get('message_id');

            if (is_string($messageId) && $messageId !== '') {
                // RFC 5322 requires Message-ID format: <local-part@domain>
                // Format the message_id to comply with RFC 5322 if it doesn't contain @
                $formattedId = str_contains($messageId, '@')
                    ? $messageId
                    : $messageId.'@lmta.net';

                $message->setMessageId($formattedId);
            }
        } catch (LettermintException $exception) {
            // ApiException and UnexpectedResponseException carry the HTTP status
            // as their code; exceptions raised before a response carry 0.
            throw new TransportException(
                sprintf('Sending email via Lettermint API failed: %s', $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * Build the request body for the Lettermint send endpoint.
     *
     * @param  list<array{filename: string, content: string, content_type: string, content_id?: string}>  $attachments
     * @return array<string, mixed>
     */
    protected function buildPayload(Email $email, Envelope $envelope, array $attachments): array
    {
        $payload = [
            'headers' => $this->getCustomHeaders($email),
            'from' => $envelope->getSender()->toString(),
            'to' => array_values($this->stringifyAddresses($this->getRecipients($email, $envelope))),
            'subject' => $email->getSubject(),
            'html' => $email->getHtmlBody(),
            'text' => $email->getTextBody(),
            'cc' => array_values($this->stringifyAddresses($email->getCc())),
            'bcc' => array_values($this->stringifyAddresses($email->getBcc())),
            'reply_to' => array_values($this->stringifyAddresses($email->getReplyTo())),
        ];

        if (isset($this->config['route_id']) && $this->config['route_id']) {
            $payload['route'] = (string) $this->config['route_id'];
        }

        $tag = $this->resolveTag($email);
        if ($tag !== null) {
            $payload['tag'] = $tag;
        }

        $metadata = $this->resolveMetadata($email);
        if (! empty($metadata)) {
            $payload['metadata'] = $metadata;
        }

        if ($attachments !== []) {
            $payload['attachments'] = $attachments;
        }

        return $payload;
    }

    /**
     * @return array<string, string>
     */
    protected function getCustomHeaders(Email $email): array
    {
        $headers = [];

        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array($name, self::BYPASS_HEADERS, true)) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        return $headers;
    }

    /**
     * @return list<array{filename: string, content: string, content_type: string, content_id?: string}>
     *
     * @throws TransportException When an attachment has no filename, which the Lettermint API requires.
     */
    protected function getAttachments(Email $email): array
    {
        $attachments = [];

        foreach ($email->getAttachments() as $attachment) {
            $attachmentHeaders = $attachment->getPreparedHeaders();
            $filename = $attachmentHeaders->getHeaderParameter('Content-Disposition', 'filename');
            $contentType = $attachmentHeaders->get('Content-Type')->getBody();

            if ($filename === null || $filename === '') {
                throw new TransportException(sprintf(
                    'Lettermint requires every attachment to have a filename, but a "%s" attachment has none. Pass a name when attaching the file.',
                    $contentType,
                ));
            }

            $item = [
                'filename' => $filename,
                'content' => str_replace("\r\n", '', $attachment->bodyToString()),
                'content_type' => $contentType,
            ];

            $contentId = $attachmentHeaders->get('Content-ID');
            if ($contentId) {
                $item['content_id'] = trim($contentId->getBodyAsString(), '<>');
            }

            $attachments[] = $item;
        }

        return $attachments;
    }

    protected function resolveIdempotencyKey(Email $email): ?string
    {
        // Always check for custom idempotency key in headers first - this overrides any config
        $customIdempotencyKey = $email->getHeaders()->get('Idempotency-Key');
        if ($customIdempotencyKey) {
            return $customIdempotencyKey->getBodyAsString();
        }

        // Check if automatic idempotency is enabled (default: false)
        $automaticIdempotency = $this->config['idempotency'] ?? false;

        if ($automaticIdempotency !== true) {
            // Automatic idempotency disabled for this mailer
            return null;
        }

        // Get idempotency window in seconds (default: 24 hours to match API retention)
        $idempotencyWindow = $this->config['idempotency_window'] ?? 86400; // 24 hours in seconds

        // Generate stable idempotency key based on email content
        // This ensures the same email content always generates the same key,
        // making it safe for retries in queue workers
        $keyParts = [
            $email->getSubject() ?? '',
            implode(',', $this->stringifyAddresses($email->getTo())),
            implode(',', $this->stringifyAddresses($email->getCc())),
            implode(',', $this->stringifyAddresses($email->getBcc())),
            $email->getHtmlBody() ?? $email->getTextBody() ?? '',
            // Include sender to differentiate between different sending contexts
            $email->getFrom() ? $this->stringifyAddresses($email->getFrom())[0] ?? '' : '',
        ];

        // Only include timestamp if window is less than 24 hours
        // This allows permanent deduplication when window matches API retention
        if ($idempotencyWindow < 86400) {
            // Include timestamp rounded to the configured window
            $keyParts[] = floor(time() / $idempotencyWindow);
        }

        // Generate SHA256 hash of the content for the idempotency key
        return hash('sha256', implode('|', array_filter($keyParts)));
    }

    protected function resolveTag(Email $email): ?string
    {
        $tag = null;

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tag = $header->getValue();
            }
        }

        if ($tag !== null) {
            return $tag;
        }

        // Fallback: Check for X-LM-Tag header for backward compatibility
        return $email->getHeaders()->get('X-LM-Tag')?->getBodyAsString();
    }

    /**
     * @return array<string, string>
     */
    protected function resolveMetadata(Email $email): array
    {
        $metadata = [];

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();
            }
        }

        return $metadata;
    }

    protected function getRecipients(Email $email, Envelope $envelope): array
    {
        $copies = array_merge($email->getCc(), $email->getBcc());

        return array_filter($envelope->getRecipients(), function (Address $address) use ($copies) {
            return in_array($address, $copies, true) === false;
        });
    }

    public function __toString(): string
    {
        return 'lettermint';
    }
}
