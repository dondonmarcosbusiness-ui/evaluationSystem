<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private string $semester;
    private string $academicYear;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $semester, string $academicYear)
    {
        $this->semester = $semester;
        $this->academicYear = $academicYear;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
                    ->subject('You have finished your evaluations')
                    ->greeting('Hello ' . $notifiable->name . ',')
                    ->line('Thank you! You have completed all of your faculty evaluations for ' . $this->semester . ', ' . $this->academicYear . '.')
                    ->line('Your responses help improve the quality of instruction at NEUST.')
                    ->action('Go to Dashboard', url('/dashboard'))
                    ->line('This is a confirmation message — no further action is needed.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'semester' => $this->semester,
            'academic_year' => $this->academicYear,
        ];
    }
}
