<?php

namespace Lettermint\Laravel\Transport;

use Exception;
use Lettermint\Endpoints\EmailEndpoint;
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
        protected \Lettermint\Lettermint $lettermint,
        protected array $config = []
    ) {
        parent::__construct();
    }

    /**
     * {@inheritDoc}
     */
    protected function doSend(SentMessage $message): void
    {
        $original = $message->getOriginalMessage();

        if (! $original instanceof Message) {
            throw new LogicException('Lettermint transport requires a Message instance, RawMessage given.');
        }

        $email = MessageConverter::toEmail($original);
        $envelope = $message->getEnvelope();

        $headers = [];
        foreach ($email->getHeaders()->all() as $name => $header) {
            if (in_array($name, self::BYPASS_HEADERS, true)) {
                continue;
            }

            $headers[$header->getName()] = $header->getBodyAsString();
        }

        $attachments = [];
        if ($email->getAttachments()) {
            foreach ($email->getAttachments() as $attachment) {
                $attachmentHeaders = $attachment->getPreparedHeaders();
                $filename = $attachmentHeaders->getHeaderParameter('Content-Disposition', 'filename');

                $item = [
                    'content' => str_replace("\r\n", '', $attachment->bodyToString()),
                    'filename' => $filename,
                    'content_type' => $attachmentHeaders->get('Content-Type')->getBody(),
                ];

                $contentId = $attachmentHeaders->get('Content-ID');
                if ($contentId) {
                    $item['content_id'] = trim($contentId->getBodyAsString(), '<>');
                }

                $attachments[] = $item;
            }
        }

        try {
            $builder = $this->lettermint->email
                ->headers($headers)
                ->from($envelope->getSender()->toString())
                ->to(...$this->stringifyAddresses($this->getRecipients($email, $envelope)))
                ->subject($email->getSubject())
                ->html($email->getHtmlBody())
                ->text($email->getTextBody())
                ->cc(...$this->stringifyAddresses($email->getCc()))
                ->bcc(...$this->stringifyAddresses($email->getBcc()))
                ->replyTo(...$this->stringifyAddresses($email->getReplyTo()));

            if (isset($this->config['route_id']) && $this->config['route_id']) {
                $builder->route($this->config['route_id']);
            }

            // Handle idempotency based on configuration
            $this->handleIdempotency($builder, $email);

            // Handle tags and metadata
            $this->handleTagsAndMetadata($builder, $email);

            foreach ($attachments as $attachment) {
                $builder->attach(
                    $attachment['filename'],
                    $attachment['content'],
                    $attachment['content_id'] ?? null
                );
            }

            $result = $builder->send();

            if (! empty($result['message_id'])) {
                // RFC 5322 requires Message-ID format: <local-part@domain>
                // Format the message_id to comply with RFC 5322 if it doesn't contain @
                $formattedId = str_contains($result['message_id'], '@')
                    ? $result['message_id']
                    : $result['message_id'].'@lmta.net';

                $message->setMessageId($formattedId);
            }
        } catch (Exception $exception) {
            throw new TransportException(
                sprintf('Sending email via Lettermint API failed: %s', $exception->getMessage()),
                is_int($exception->getCode()) ? $exception->getCode() : 0,
                $exception
            );
        }
    }

    protected function handleIdempotency(EmailEndpoint $builder, Email $email): void
    {
        // Always check for custom idempotency key in headers first - this overrides any config
        $customIdempotencyKey = $email->getHeaders()->get('Idempotency-Key');
        if ($customIdempotencyKey) {
            $builder->idempotencyKey($customIdempotencyKey->getBodyAsString());

            return;
        }

        // Check if automatic idempotency is enabled (default: false)
        $automaticIdempotency = $this->config['idempotency'] ?? false;

        if ($automaticIdempotency !== true) {
            // Automatic idempotency disabled for this mailer
            return;
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
        $idempotencyKey = hash('sha256', implode('|', array_filter($keyParts)));
        $builder->idempotencyKey($idempotencyKey);
    }

    protected function handleTagsAndMetadata(EmailEndpoint $builder, Email $email): void
    {
        $tag = null;
        $metadata = [];

        foreach ($email->getHeaders()->all() as $header) {
            if ($header instanceof TagHeader) {
                $tag = $header->getValue();

                continue;
            }

            if ($header instanceof MetadataHeader) {
                $metadata[$header->getKey()] = $header->getValue();

                continue;
            }
        }

        // Fallback: Check for X-LM-Tag header for backward compatibility
        if ($tag === null) {
            $customTagHeader = $email->getHeaders()->get('X-LM-Tag');
            if ($customTagHeader) {
                $tag = $customTagHeader->getBodyAsString();
            }
        }

        // Apply tag if found
        if ($tag !== null) {
            $builder->tag($tag);
        }

        // Apply metadata if any exists
        if (! empty($metadata)) {
            $builder->metadata($metadata);
        }
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
