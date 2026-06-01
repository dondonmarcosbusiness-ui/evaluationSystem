<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private $studentName;
    private $courseSection;

    /**
     * Create a new notification instance.
     */
    public function __construct($studentName, $courseSection)
    {
        $this->studentName = $studentName;
        $this->courseSection = $courseSection;
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
                    ->subject('Faculty Evaluation Reminder')
                    ->greeting('Hello ' . $this->studentName . ',')
                    ->line('You have pending faculty evaluations for your enrolled subjects in ' . $this->courseSection . '.')
                    ->line('Please log in and complete your evaluations as soon as possible.')
                    ->action('Go to Dashboard', url('/dashboard'))
                    ->line('Thank you for participating.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
