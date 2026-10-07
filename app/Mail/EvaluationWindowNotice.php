<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EvaluationWindowNotice extends Mailable
{
    use Queueable, SerializesModels;

    public $studentName;
    public $event;
    public $departments;
    public $semester;
    public $academicYear;

    /**
     * @param array<int, string> $departments
     */
    public function __construct($studentName, $event, array $departments, $semester, $academicYear)
    {
        $this->studentName = $studentName;
        $this->event = $event;
        $this->departments = $departments;
        $this->semester = $semester;
        $this->academicYear = $academicYear;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->event === 'open'
                ? 'Faculty Evaluation is now Open!'
                : 'Faculty Evaluation Window has Closed',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.evaluation_window_notice',
        );
    }

    /**
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
