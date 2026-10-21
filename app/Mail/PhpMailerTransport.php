<?php

namespace App\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Throwable;

class PhpMailerTransport implements TransportInterface
{
    /**
     * Temporary files created for inline (cid) attachments, removed after each send.
     *
     * @var list<string>
     */
    private array $tempFiles = [];

    /**
     * @param  array<string, mixed>  $config  A "phpmailer" mailer entry from config/mail.php
     */
    public function __construct(private readonly array $config) {}

    /**
     * @throws TransportExceptionInterface
     */
    public function send(RawMessage $message, ?Envelope $envelope = null): ?SentMessage
    {
        if (! $message instanceof Email) {
            throw new TransportException('PhpMailerTransport only supports Symfony Mime Email messages.');
        }

        try {
            $envelope ??= Envelope::create($message);

            $this->toPhpMailer($message, $envelope)->send();
        } catch (Throwable $e) {
            throw new TransportException('PHPMailer send failed: '.$e->getMessage(), 0, $e);
        } finally {
            foreach ($this->tempFiles as $file) {
                @unlink($file);
            }
            $this->tempFiles = [];
        }

        return new SentMessage($message);
    }

    public function toPhpMailer(Email $message, Envelope $envelope): PHPMailer
    {
        $mail = new PHPMailer(true);

        $mail->CharSet = 'UTF-8';
        $mail->isSMTP();
        $mail->Host = (string) ($this->config['host'] ?? '');
        $mail->Port = (int) ($this->config['port'] ?? 587);
        $mail->Timeout = (int) ($this->config['timeout'] ?? 20);

        if (! empty($this->config['username'])) {
            $mail->SMTPAuth = true;
            $mail->Username = (string) $this->config['username'];
            $mail->Password = (string) ($this->config['password'] ?? '');
        }

        $encryption = $this->config['encryption'] ?? null;

        if ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } elseif ($encryption === 'ssl' || $mail->Port === 465) {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        }
        // No explicit encryption: leave SMTPAutoTLS on so STARTTLS is used
        // whenever the server advertises it (Brevo's relay on 587 does).

        if (! empty($this->config['local_domain'])) {
            $mail->Hostname = (string) $this->config['local_domain'];
        }

        $from = $message->getFrom() ?: [$envelope->getSender()];

        if ($from !== []) {
            $mail->setFrom($from[0]->getAddress(), $from[0]->getName());
        }

        foreach ($message->getTo() ?: [] as $address) {
            $mail->addAddress($address->getAddress(), $address->getName());
        }

        foreach ($message->getCc() ?: [] as $address) {
            $mail->addCc($address->getAddress(), $address->getName());
        }

        foreach ($message->getBcc() ?: [] as $address) {
            $mail->addBcc($address->getAddress(), $address->getName());
        }

        if ($message->getTo() === null && $message->getCc() === null && $message->getBcc() === null) {
            foreach ($envelope->getRecipients() as $address) {
                $mail->addAddress($address->getAddress(), $address->getName());
            }
        }

        foreach ($message->getReplyTo() ?: [] as $address) {
            $mail->addReplyTo($address->getAddress(), $address->getName());
        }

        if ($message->getSubject() !== null) {
            $mail->Subject = (string) $message->getSubject();
        }

        $html = $this->stringify($message->getHtmlBody());
        $text = $this->stringify($message->getTextBody());

        if ($html !== null && $html !== '') {
            $mail->isHTML(true);
            $mail->Body = $html;
            $mail->AltBody = $text ?? '';
        } elseif ($text !== null) {
            $mail->Body = $text;
        }

        foreach ($message->getAttachments() as $part) {
            $filename = $part->getFilename() ?? 'attachment';
            $type = $part->getMediaType().'/'.$part->getMediaSubtype();
            $body = $part->getBody();

            if ($part->getDisposition() === 'inline') {
                $path = tempnam(sys_get_temp_dir(), 'phpmailer_');

                if ($path === false) {
                    throw new TransportException('Unable to create a temporary file for an inline attachment.');
                }

                file_put_contents($path, $body);
                $this->tempFiles[] = $path;

                $mail->addEmbeddedImage($path, $part->getContentId(), $filename, PHPMailer::ENCODING_BASE64, $type);
            } else {
                $mail->addStringAttachment($body, $filename, PHPMailer::ENCODING_BASE64, $type);
            }
        }

        return $mail;
    }

    public function __toString(): string
    {
        return sprintf('phpmailer://%s', $this->config['host'] ?? '');
    }

    /**
     * @param  string|resource|null  $body
     */
    private function stringify(mixed $body): ?string
    {
        if (is_resource($body)) {
            return (string) stream_get_contents($body);
        }

        return $body;
    }
}
