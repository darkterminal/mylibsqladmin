<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitation extends Notification // Remove ShouldQueue
{
    use Queueable;

    public function __construct(
        public $team,
        public $invitation
    ) {
    }

    public function via($notifiable)
    {
        return ['mail', 'log']; // Add log channel
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject("Invitation to join {$this->team->name}")
            ->line("You've been invited to join {$this->team->name} team as {$this->invitation->permission_level}")
            ->action('Accept Invitation', route('invitations.accept', $this->invitation->token))
            ->line("This invitation expires in 7 days");
    }

    public function toLog($notifiable)
    {
        return [
            'team' => $this->team->name,
            'email' => $notifiable->email,
            'role' => $this->invitation->permission_level,
            'expires' => now()->addDays(7)->toDateTimeString()
        ];
    }
}
