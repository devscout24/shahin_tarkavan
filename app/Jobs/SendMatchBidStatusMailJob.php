<?php

namespace App\Jobs;

use App\Mail\MatchBidStatusMail;
use App\Models\MatchBid;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendMatchBidStatusMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $bidId) {}

    public function handle(): void
    {
        $bid = MatchBid::query()->with([
            'requestedClub.user',
            'createdClub.user',
            'match.clubTeam.club.clubTeams.competitionLevel',
            'match.opponentClub.user.clubTeams.competitionLevel',
        ])->find($this->bidId);

        if (! $bid || ! $bid->requestedClub?->user?->email) {
            return;
        }

        Mail::to($bid->requestedClub->user->email)->send(new MatchBidStatusMail($bid));
    }
}
