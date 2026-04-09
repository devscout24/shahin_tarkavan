<?php

namespace App\Mail;

use App\Models\RecruitementApply;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Carbon;
use Illuminate\Queue\SerializesModels;

class RecruitementMail extends Mailable
{
    use Queueable, SerializesModels;

    public RecruitementApply $application;
    public string $statusLabel;
    public string $statusBg;
    public string $statusColor;
    public ?string $teamName;
    public ?string $tryoutDate;
    public ?string $applicantName;
    public ?string $clubName;
    public string $actionMessage;
    public string $statusHeadline;
    public bool $isScheduled;

    public function __construct(RecruitementApply $application)
    {
        $application->loadMissing([
            'team:id,name',
            'recruitment:id,end_date',
            'user:id,name,last_name',
            'club:id,name,last_name',
        ]);

        $this->application = $application;
        $this->statusLabel = ucfirst((string) $application->status);

        $statusPalette = match ($application->status) {
            'accepted' => ['#e7f8ee', '#0f8f4f'],
            'rejected' => ['#fdecec', '#b42318'],
            'scheduled' => ['#edf3ff', '#1f4db8'],
            default => ['#fff5e8', '#b54708'],
        };

        $this->statusBg = $statusPalette[0];
        $this->statusColor = $statusPalette[1];
        $this->teamName = $application->team?->name;
        $rawTryoutDate = $application->recruitment?->end_date;
        $this->tryoutDate = $rawTryoutDate
            ? Carbon::parse($rawTryoutDate)->format('M d, Y')
            : null;
        $this->applicantName = trim((string) (($application->user?->name ?? '') . ' ' . ($application->user?->last_name ?? '')));
        $this->clubName = trim((string) (($application->club?->name ?? '') . ' ' . ($application->club?->last_name ?? '')));
        $this->isScheduled = $application->status === 'scheduled';

        $this->actionMessage = match ($application->status) {
            'accepted' => 'Congratulations! Your application has been accepted. The club will contact you for the next steps.',
            'rejected' => 'Thank you for your interest. This time your application was not selected. Keep going and apply again.',
            'scheduled' => 'Great news! Your tryout has been scheduled. Please arrive early and bring your required documents and kit.',
            default => 'Your application is under review. We will notify you as soon as there is an update.',
        };

        $this->statusHeadline = match ($application->status) {
            'accepted' => 'You Have Been Accepted',
            'rejected' => 'Application Not Selected',
            'scheduled' => 'Your Tryout Is Scheduled',
            default => 'Application Under Review',
        };
    }

    public function build()
    {
        return $this->subject(match ($this->application->status) {
            'scheduled' => 'Tryout Scheduled - ' . ($this->teamName ?: 'Recruitment Team'),
            default => 'Recruitment Application ' . $this->statusLabel,
        })
            ->view('emails.recruitment');
    }
}
