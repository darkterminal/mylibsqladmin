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

class SendTeamInvitation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Invitation $invitation
    ) {
    }

    public function handle()
    {
        $user = User::where('email', $this->invitation->email)->first();

        if ($user) {
            $user->notify(new TeamInvitation(
                $this->invitation->team,
                $this->invitation
            ));
        }
    }
}
