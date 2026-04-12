<?php

namespace App\Mail;

use App\Models\MatchBid;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class MatchBidStatusMail extends Mailable
{
    use Queueable, SerializesModels;

    public MatchBid $bid;
    public string $statusLabel;
    public string $headline;
    public string $bodyMessage;
    public string $statusBg;
    public string $statusColor;

    public function __construct(MatchBid $bid)
    {
        $bid->loadMissing([
            'match.clubTeam.club.clubTeams.competitionLevel',
            'match.opponentClub.user.clubTeams.competitionLevel',
            'createdClub.user.clubTeams.competitionLevel',
            'requestedClub.user.clubTeams.competitionLevel',
        ]);

        $this->bid = $bid;
        $this->statusLabel = ucfirst((string) $bid->status);

        [$this->statusBg, $this->statusColor] = match ($bid->status) {
            'accepted' => ['#e7f8ee', '#0f8f4f'],
            'rejected' => ['#fdecec', '#b42318'],
            default => ['#eef2ff', '#1d4ed8'],
        };

        $this->headline = match ($bid->status) {
            'accepted' => 'Your Match Bid Has Been Accepted',
            'rejected' => 'Your Match Bid Was Not Selected',
            default => 'Match Bid Status Update',
        };

        $this->bodyMessage = match ($bid->status) {
            'accepted' => 'Congratulations. Your club has been accepted for this match. The organizer will follow up with the next steps.',
            'rejected' => 'Thank you for your interest. Another club has been selected for this match.',
            default => 'Your match bid status has been updated.',
        };
    }

    public function build()
    {
        return $this->subject('Match Bid ' . $this->statusLabel)
            ->view('emails.match-bid-status');
    }
}
