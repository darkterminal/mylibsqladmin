<?php

namespace App\Jobs;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Notifications\TeamInvitation;
use Illuminate\Support\Facades\Notification;

class SendTeamInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation
    ) {
    }

    public function handle()
    {
        logger()->debug('Sending team invitation notification', $this->invitation->toArray());
        $user = User::where('email', $this->invitation->email)->first();

        $notification = new TeamInvitation(
            $this->invitation->team,
            $this->invitation
        );

        if ($user) {
            $user->notify($notification);
        } else {
            Notification::route('mail', $this->invitation->email)
                ->notify($notification);
        }
    }
}
