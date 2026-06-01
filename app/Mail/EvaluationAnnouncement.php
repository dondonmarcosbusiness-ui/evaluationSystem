<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EvaluationAnnouncement extends Mailable
{
    use Queueable, SerializesModels;

    public $studentName;
    public $semester;
    public $academicYear;

    /**
     * Create a new message instance.
     */
    public function __construct($studentName, $semester, $academicYear)
    {
        $this->studentName = $studentName;
        $this->semester = $semester;
        $this->academicYear = $academicYear;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Evaluation System is now OPEN!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.evaluation_announcement',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
