<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubRecruitment;
use App\Models\Coach;
use App\Models\Commission;
use App\Models\ErProgram;
use App\Models\ProgramBooking;
use App\Models\RecruitementApply;
use App\Traits\ApiResponse;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CoachDashboardController extends Controller
{
    use ApiResponse;

    private function getActiveCommission(): ?Commission
    {
        return Commission::query()
            ->where('status', 'active')
            ->latest('id')
            ->first();
    }

    private function calculateBookingTotals(ProgramBooking $booking): array
    {
        $amount = (float) ($booking->amount ?? 0);
        $discount = (float) ($booking->discount ?? 0);
        $taxPercent = (float) ($booking->tax ?? 0);
        $subtotal = max(0.0, $amount - $discount);
        $taxAmount = round(($subtotal * $taxPercent) / 100, 2);
        $total = round($subtotal + $taxAmount, 2);
        $commission = $this->getActiveCommission();

        if (! $commission) {
            return [
                'commission_label' => 'N/A',
                'commission_amount' => 0.0,
                'coach_pay' => $total,
                'total' => $total,
                'gross' => $subtotal,
                'tax_amount' => $taxAmount,
            ];
        }

        $commissionType = strtolower((string) $commission->type);
        $commissionValue = (float) $commission->amount;

        $commissionAmount = $commissionType === 'fixed'
            ? min($total, $commissionValue)
            : (($total * $commissionValue) / 100);

        $commissionAmount = round(max(0.0, min($total, $commissionAmount)), 2);
        $coachPay = round(max(0.0, $total - $commissionAmount), 2);

        return [
            'commission_label' => $commissionType === 'percentage'
                ? number_format($commissionValue, 2) . '%'
                : 'Fixed ' . number_format($commissionValue, 2),
            'commission_amount' => $commissionAmount,
            'coach_pay' => $coachPay,
            'total' => $total,
            'gross' => $subtotal,
            'tax_amount' => $taxAmount,
        ];
    }

    public function coachDashboard(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return $this->errors([], 'Authentication required.', 401);
            }

            if ($user->role !== 'coach') {
                return $this->errors([], 'Only coach can view this dashboard.', 403);
            }

            $coach = Coach::query()
                ->with(['currentPosition:id,name'])
                ->where('user_id', $user->id)
                ->first();

            if (! $coach) {
                return $this->errors([], 'Coach profile not found.', 404);
            }

            $today = now()->toDateString();
            $monthStart = now()->startOfMonth()->toDateString();

            $activePrograms = ErProgram::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereDate('program_end', '>=', $today)
                ->count();

            $upcomingPrograms = ErProgram::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereDate('program_start', '>=', $today)
                ->count();

            $activeCommission = Commission::query()
                ->where('status', 'active')
                ->latest('id')
                ->first();

            $commissionType = strtolower((string) ($activeCommission?->type ?? 'percentage'));
            $commissionValue = (float) ($activeCommission?->amount ?? 0);

            $monthlyPaidBookings = ProgramBooking::query()
                ->where('coach_id', $user->id)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', '>=', $monthStart)
                ->get(['amount']);

            $platformFee = (float) $monthlyPaidBookings->sum(function (ProgramBooking $booking) use ($commissionType, $commissionValue) {
                $grossAmount = (float) ($booking->amount ?? 0);
                if ($grossAmount <= 0) {
                    return 0;
                }

                if ($commissionType === 'fixed') {
                    return round(min($grossAmount, $commissionValue), 2);
                }

                return round(($grossAmount * $commissionValue) / 100, 2);
            });

            $grossEarnings = (float) $monthlyPaidBookings->sum(fn(ProgramBooking $booking) => (float) ($booking->amount ?? 0));
            $netEarnings = max(0, round($grossEarnings - $platformFee, 2));

            $appliedRecruitmentIds = RecruitementApply::query()
                ->where('user_id', $user->id)
                ->where('type', 'coach')
                ->pluck('recruitment_id')
                ->all();

            $recentOpportunities = ClubRecruitment::query()
                ->with([
                    'club:id,name,last_name,email',
                    'club.club:id,user_id,club_name,club_logo,city,state,country',
                    'clubTeam:id,name,age_group,image,competition_level_id',
                    'clubTeam.competitionLevel:id,name',
                    'coachPosition:id,name',
                ])
                ->where('status', 'active')
                ->where('recruitment_type', 'coach')
                ->whereDate('end_date', '>=', $today)
                ->when($coach->current_role, function ($query) use ($coach) {
                    return $query->where('coach_position_id', $coach->current_role);
                })
                ->orderBy('end_date')
                ->limit(5)
                ->get()
                ->map(function (ClubRecruitment $recruitment) use ($appliedRecruitmentIds) {
                    $competitionLevel = $recruitment->clubTeam?->competitionLevel?->name;
                    $experience = $recruitment->experience;
                    $meta = array_values(array_filter([
                        $competitionLevel,
                        $experience ? 'Experience: ' . $experience : null,
                    ]));

                    return [
                        'id' => $recruitment->id,
                        'club' => [
                            'id' => $recruitment->club?->club?->id,
                            'club_name' => $recruitment->club?->club?->club_name,
                            'club_logo' => ! empty($recruitment->club?->club?->club_logo) ? asset($recruitment->club->club->club_logo) : null,
                            'city' => $recruitment->club?->club?->city,
                            'state' => $recruitment->club?->club?->state,
                            'country' => $recruitment->club?->club?->country,
                        ],
                        'headline' => 'Looking For Coaches',
                        'position' => $recruitment->coachPosition?->name,
                        'team' => [
                            'id' => $recruitment->clubTeam?->id,
                            'name' => $recruitment->clubTeam?->name,
                            'age_group' => $recruitment->clubTeam?->age_group,
                            'image' => ! empty($recruitment->clubTeam?->image) ? asset($recruitment->clubTeam->image) : null,
                            'competition_level' => $competitionLevel,
                        ],
                        'meta' => implode(' | ', $meta) ?: null,
                        'tryout_date' => $recruitment->end_date
                            ? ($recruitment->end_date instanceof CarbonInterface
                                ? $recruitment->end_date->format('F d-j, Y')
                                : Carbon::parse($recruitment->end_date)->format('F d-j, Y'))
                            : null,
                        'description' => $recruitment->description,
                        'is_applied' => in_array($recruitment->id, $appliedRecruitmentIds, true),
                    ];
                })
                ->values();

            return $this->success([
                'coach_info' => [
                    'id' => $coach->id,
                    'name' => trim((string) $coach->name . ' ' . (string) $coach->last_name),
                    'image' => ! empty($coach->coach_profile_pic) ? asset($coach->coach_profile_pic) : null,
                    'position' => $coach->currentPosition?->name,
                    'years_of_experience' => $coach->years_of_experience,
                    'city' => $coach->city,
                    'country' => $coach->country,
                ],
                'summary' => [
                    'active_programs' => $activePrograms,
                    'upcoming_programs' => $upcomingPrograms,
                    'net_earnings_month' => round($netEarnings, 2),
                    'platform_fee_month' => round($platformFee, 2),
                    'platform_fee_rate' => $commissionValue,
                    'platform_fee_type' => $commissionType,
                ],
                'recent_opportunities' => $recentOpportunities,

            ], 'Coach dashboard data fetched successfully.', 200);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }

    public function exportEarnings(Request $request)
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return $this->errors([], 'Authentication required.', 401);
            }

            if ($user->role !== 'coach') {
                return $this->errors([], 'Only coach can export earnings.', 403);
            }

            $coach = Coach::query()
                ->with(['currentPosition:id,name'])
                ->where('user_id', $user->id)
                ->first();

            if (! $coach) {
                return $this->errors([], 'Coach profile not found.', 404);
            }

            $bookings = ProgramBooking::query()
                ->with([
                    'program:id,program_name,program_location,program_start,program_end,program_photo',
                    'athlete:id,name,last_name',
                    'parent:id,name,last_name,email',
                    'bookingTime:id,time',
                ])
                ->where('coach_id', $user->id)
                ->where('payment_status', 'paid')
                ->latest('id')
                ->get();

            $rows = $bookings->map(function (ProgramBooking $booking) {
                $totals = $this->calculateBookingTotals($booking);
                $currency = strtoupper((string) ($booking->currency ?: 'usd'));

                return [
                    'id' => $booking->id,
                    'program_name' => $booking->program?->program_name ?? 'N/A',
                    'athlete_name' => trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? '')) ?: 'N/A',
                    'parent_name' => trim((string) ($booking->parent?->name ?? '') . ' ' . (string) ($booking->parent?->last_name ?? '')) ?: 'N/A',
                    'booking_date' => optional($booking->created_at)?->toDateString(),
                    'currency' => $currency,
                    'gross' => $totals['gross'],
                    'tax_amount' => $totals['tax_amount'],
                    'commission_label' => $totals['commission_label'],
                    'commission_amount' => $totals['commission_amount'],
                    'coach_pay' => $totals['coach_pay'],
                    'total' => $totals['total'],
                ];
            })->values();

            $summary = [
                'total_bookings' => $rows->count(),
                'gross_total' => round((float) $rows->sum('gross'), 2),
                'commission_total' => round((float) $rows->sum('commission_amount'), 2),
                'net_total' => round((float) $rows->sum('coach_pay'), 2),
            ];

            $html = view('pdf.coach-earnings-report', [
                'coach' => $coach,
                'summary' => $summary,
                'rows' => $rows,
            ])->render();

            $fileName = 'coach-earnings-report-' . now()->format('Y-m-d') . '.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->download($fileName);
        } catch (\Throwable $e) {
            return $this->errors([], $e->getMessage(), 500);
        }
    }
}
