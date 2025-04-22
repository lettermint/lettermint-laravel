<?php

namespace Lettermint\Laravel\Transport;

use Exception;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
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
        $email = MessageConverter::toEmail($message->getOriginalMessage());
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

                $attachments[] = $item;
            }
        }

        try {
            $builder = $this->lettermint->email
                ->headers(...$headers)
                ->from($envelope->getSender()->toString())
                ->to(...$this->stringifyAddresses($this->getRecipients($email, $envelope)))
                ->subject($email->getSubject())
                ->html($email->getHtmlBody())
                ->text($email->getTextBody())
                ->cc(...$this->stringifyAddresses($email->getCc()))
                ->bcc(...$this->stringifyAddresses($email->getBcc()))
                ->replyTo(...$this->stringifyAddresses($email->getReplyTo()));

            foreach ($attachments as $attachment) {
                $builder->attach($attachment['filename'], $attachment['content']);
            }

            $result = $builder->send();
        } catch (Exception $exception) {
            throw new TransportException(
                sprintf('Sending email via Lettermint API failed: %s', $exception->getMessage()),
                is_int($exception->getCode()) ? $exception->getCode() : 0,
                $exception
            );
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
