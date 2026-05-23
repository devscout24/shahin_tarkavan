<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClubRecruitment;
use App\Models\City;
use App\Models\Country;
use App\Models\Coach;
use App\Models\Commission;
use App\Models\ErProgram;
use App\Models\ProgramBooking;
use App\Models\RecruitementApply;
use App\Support\AgeGroup;
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

    private function getActiveCommissionForRole(?string $role, ?int $ownerUserId = null): ?Commission
    {
        $normalizedRole = strtolower((string) $role);
        if (! in_array($normalizedRole, ['coach', 'club'], true)) {
            $normalizedRole = 'all';
        }

        return Commission::query()
            ->where('status', 'active')
            ->where(function ($query) use ($normalizedRole, $ownerUserId): void {
                if ($ownerUserId) {
                    $query->where('user_id', $ownerUserId);
                }

                $query->orWhere(function ($nested) use ($normalizedRole): void {
                    $nested->whereNull('user_id')
                        ->whereIn('applies_to', [$normalizedRole, 'all']);
                });
            })
            ->orderByRaw('CASE WHEN user_id = ? THEN 0 WHEN applies_to = ? THEN 1 ELSE 2 END', [$ownerUserId ?: 0, $normalizedRole])
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
        $commission = $this->getActiveCommissionForRole('coach', (int) ($booking->coach_id ?? 0));

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

    private function resolveLocationPayload(?int $cityId, ?int $countryId, ?string $cityName, ?string $countryName): array
    {
        $resolvedCityId = $cityId ?: ($cityName ? City::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($cityName))])->value('id') : null);
        $resolvedCountryId = $countryId ?: ($countryName ? Country::query()->whereRaw('LOWER(name) = ?', [strtolower(trim($countryName))])->value('id') : null);

        return [
            'city_id' => $resolvedCityId ? (int) $resolvedCityId : null,
            'city' => $cityName,
            'country_id' => $resolvedCountryId ? (int) $resolvedCountryId : null,
            'country' => $countryName,
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

            $activeCommission = $this->getActiveCommissionForRole('coach', (int) $user->id);

            $commissionType = strtolower((string) ($activeCommission?->type ?? 'percentage'));
            $commissionValue = (float) ($activeCommission?->amount ?? 0);

            $monthlyPaidBookings = ProgramBooking::query()
                ->where('coach_id', $user->id)
                ->where('payment_status', 'paid')
                ->whereDate('created_at', '>=', $monthStart)
                ->get(['amount', 'discount', 'tax', 'coach_id']);

            $platformFee = (float) $monthlyPaidBookings->sum(function (ProgramBooking $booking) {
                $totals = $this->calculateBookingTotals($booking);

                return (float) ($totals['commission_amount'] ?? 0);
            });

            $grossEarnings = (float) $monthlyPaidBookings->sum(function (ProgramBooking $booking) {
                $totals = $this->calculateBookingTotals($booking);

                return (float) ($totals['total'] ?? 0);
            });
            $netEarnings = max(0, round($grossEarnings - $platformFee, 2));

            $appliedRecruitmentIds = RecruitementApply::query()
                ->where('user_id', $user->id)
                ->where('type', 'coach')
                ->pluck('recruitment_id')
                ->all();

            $recentOpportunities = ClubRecruitment::query()
                ->with([
                    'club:id,name,last_name,email',
                    'club.club:id,user_id,club_name,club_logo,city,state,country,city_id,country_id',
                    'clubTeam:id,name,age_group,image,competition_level_id',
                    'clubTeam.competitionLevel:id,name',
                    'coachPosition:id,name',
                ])
                ->where('status', 'active')
                ->where('recruitment_type', 'coach')
                // Show only recruitment where today is between start_date and end_date
                ->where(function ($dateQuery) use ($today) {
                    $dateQuery->where(function ($q) use ($today) {
                        $q->whereNull('start_date')
                            ->orWhereDate('start_date', '<=', $today);
                    })
                        ->where(function ($q) use ($today) {
                            $q->whereNull('end_date')
                                ->orWhereDate('end_date', '>=', $today);
                        });
                })
                // Apply inclusive filtering: location OR position OR no position requirement
                ->where(function ($query) use ($coach, $today) {
                    // If coach has location, show recruitment in that location
                    if ($coach->city_id || $coach->country_id) {
                        $query->whereHas('club.club', function ($q) use ($coach) {
                            $q->where(function ($locationQuery) use ($coach) {
                                if ($coach->city_id) {
                                    $locationQuery->where('city_id', $coach->city_id);
                                }
                                if ($coach->country_id) {
                                    $locationQuery->orWhere('country_id', $coach->country_id);
                                }
                            });
                        });
                    }

                    // If coach has current position, show recruitment requiring that position (or no position requirement)
                    if ($coach->current_role) {
                        $query->orWhere(function ($positionQuery) use ($coach) {
                            $positionQuery->where('coach_position_id', $coach->current_role)
                                ->orWhereNull('coach_position_id');
                        });
                    }

                    // If coach has neither location nor position, show all recruitment with no position requirement
                    if (! $coach->city_id && ! $coach->country_id && ! $coach->current_role) {
                        $query->whereNull('coach_position_id')
                            ->orWhereHas('club.club');
                    }
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
                            'city_id' => $recruitment->club?->club?->city_id ? (int) $recruitment->club->club->city_id : null,
                            'country_id' => $recruitment->club?->club?->country_id ? (int) $recruitment->club->club->country_id : null,
                        ],
                        'headline' => 'Looking For Coaches',
                        'position' => [
                            'id' => $recruitment->coach_position_id ? (int) $recruitment->coach_position_id : null,
                            'name' => $recruitment->coachPosition?->name,
                        ],
                        'team' => [
                            'id' => $recruitment->clubTeam?->id,
                            'name' => $recruitment->clubTeam?->name,
                            'age_group' => AgeGroup::normalize($recruitment->clubTeam?->age_group),
                            'image' => ! empty($recruitment->clubTeam?->image) ? asset($recruitment->clubTeam->image) : null,
                            'competition_level' => $competitionLevel,
                        ],
                        'meta' => implode(' | ', $meta) ?: null,
                        'start_date' => $recruitment->start_date
                            ? ($recruitment->start_date instanceof CarbonInterface
                                ? $recruitment->start_date->toDateString()
                                : Carbon::parse($recruitment->start_date)->toDateString())
                            : null,
                        'tryout_date' => $recruitment->end_date
                            ? ($recruitment->end_date instanceof CarbonInterface
                                ? $recruitment->end_date->format('F d, Y')
                                : Carbon::parse($recruitment->end_date)->format('F d, Y'))
                            : null,
                        'end_date' => $recruitment->end_date
                            ? ($recruitment->end_date instanceof CarbonInterface
                                ? $recruitment->end_date->toDateString()
                                : Carbon::parse($recruitment->end_date)->toDateString())
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
                    'position' => [
                        'id' => $coach->current_role ? (int) $coach->current_role : null,
                        'name' => $coach->currentPosition?->name,
                    ],
                    'years_of_experience' => $coach->years_of_experience,
                    'city' => $coach->city,
                    'country' => $coach->country,
                    'city_id' => $coach->city_id ? (int) $coach->city_id : null,
                    'country_id' => $coach->country_id ? (int) $coach->country_id : null,
                    'location' => $this->resolveLocationPayload($coach->city_id, $coach->country_id, $coach->city, $coach->country),
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
