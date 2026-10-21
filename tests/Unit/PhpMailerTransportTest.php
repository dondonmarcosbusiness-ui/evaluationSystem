<?php

namespace Tests\Unit;

use App\Mail\PhpMailerTransport;
use PHPMailer\PHPMailer\PHPMailer;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;
use Tests\TestCase;

class PhpMailerTransportTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $config
     */
    private function transport(array $config = []): PhpMailerTransport
    {
        return new PhpMailerTransport(array_merge([
            'host' => 'smtp-relay.brevo.com',
            'port' => 587,
            'username' => 'smtp-user@example.com',
            'password' => 'smtp-key',
            'timeout' => 20,
        ], $config));
    }

    public function test_configures_smtp_connection_from_mailer_config(): void
    {
        $email = (new Email)
            ->from(new Address('sender@example.com', 'Sender'))
            ->to('to@example.com')
            ->subject('Hello')
            ->text('body');

        $mail = $this->transport()->toPhpMailer($email, Envelope::create($email));

        $this->assertSame('smtp', $mail->Mailer);
        $this->assertSame('smtp-relay.brevo.com', $mail->Host);
        $this->assertSame(587, $mail->Port);
        $this->assertSame(20, $mail->Timeout);
        $this->assertTrue($mail->SMTPAuth);
        $this->assertSame('smtp-user@example.com', $mail->Username);
        $this->assertSame('smtp-key', $mail->Password);
        $this->assertSame('UTF-8', $mail->CharSet);
    }

    public function test_port_465_defaults_to_ssl_encryption(): void
    {
        $email = (new Email)->from('sender@example.com')->to('to@example.com')->text('body');

        $mail = $this->transport(['port' => 465])->toPhpMailer($email, Envelope::create($email));

        $this->assertSame(PHPMailer::ENCRYPTION_SMTPS, $mail->SMTPSecure);
    }

    public function test_explicit_tls_encryption_is_applied(): void
    {
        $email = (new Email)->from('sender@example.com')->to('to@example.com')->text('body');

        $mail = $this->transport(['encryption' => 'tls'])->toPhpMailer($email, Envelope::create($email));

        $this->assertSame(PHPMailer::ENCRYPTION_STARTTLS, $mail->SMTPSecure);
    }

    public function test_missing_username_disables_smtp_auth(): void
    {
        $email = (new Email)->from('sender@example.com')->to('to@example.com')->text('body');

        $mail = $this->transport(['username' => null, 'password' => null])
            ->toPhpMailer($email, Envelope::create($email));

        $this->assertFalse($mail->SMTPAuth);
    }

    public function test_maps_recipients_subject_and_bodies(): void
    {
        $email = (new Email)
            ->from(new Address('sender@example.com', 'Sender'))
            ->to(new Address('to@example.com', 'Recipient'))
            ->cc('cc@example.com')
            ->bcc('bcc@example.com')
            ->replyTo('reply@example.com')
            ->subject('Evaluation is open')
            ->html('<p>Hello</p>')
            ->text('Hello');

        $mail = $this->transport()->toPhpMailer($email, Envelope::create($email));

        $this->assertSame('sender@example.com', $mail->From);
        $this->assertSame('Sender', $mail->FromName);
        $this->assertSame(['to@example.com'], array_column($mail->getToAddresses(), 0));
        $this->assertSame(['Recipient'], array_column($mail->getToAddresses(), 1));
        $this->assertSame(['cc@example.com'], array_column($mail->getCcAddresses(), 0));
        $this->assertSame(['bcc@example.com'], array_column($mail->getBccAddresses(), 0));
        $this->assertSame(['reply@example.com'], array_column($mail->getReplyToAddresses(), 0));
        $this->assertSame('Evaluation is open', $mail->Subject);
        $this->assertSame(PHPMailer::CONTENT_TYPE_TEXT_HTML, $mail->ContentType);
        $this->assertSame('<p>Hello</p>', $mail->Body);
        $this->assertSame('Hello', $mail->AltBody);
    }

    public function test_plain_text_only_message_is_not_sent_as_html(): void
    {
        $email = (new Email)->from('sender@example.com')->to('to@example.com')->text('just text');

        $mail = $this->transport()->toPhpMailer($email, Envelope::create($email));

        $this->assertSame(PHPMailer::CONTENT_TYPE_PLAINTEXT, $mail->ContentType);
        $this->assertSame('just text', $mail->Body);
    }

    public function test_adds_regular_attachments(): void
    {
        $email = (new Email)
            ->from('sender@example.com')
            ->to('to@example.com')
            ->text('body')
            ->attach('pdf-bytes', 'report.pdf', 'application/pdf');

        $mail = $this->transport()->toPhpMailer($email, Envelope::create($email));

        $attachments = $mail->getAttachments();

        $this->assertCount(1, $attachments);
        $this->assertSame('report.pdf', $attachments[0][1]);
        $this->assertSame('application/pdf', $attachments[0][4]);
        $this->assertSame('attachment', $attachments[0][6]);
    }

    public function test_send_rejects_non_email_messages(): void
    {
        $this->expectException(TransportException::class);

        $this->transport()->send(new RawMessage('not an email'));
    }

    public function test_to_string_exposes_transport_target(): void
    {
        $this->assertSame('phpmailer://smtp-relay.brevo.com', (string) $this->transport());
    }
}
