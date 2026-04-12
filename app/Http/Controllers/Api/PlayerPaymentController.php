<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AthleteProfiles;
use App\Models\ProgramBooking;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class PlayerPaymentController extends Controller
{
    private function getAthleteProfileIdsForUser($user): array
    {
        $profileIds = [];

        $ownProfileIds = AthleteProfiles::query()
            ->where('user_id', $user->id)
            ->pluck('id')
            ->all();

        if (! empty($ownProfileIds)) {
            $profileIds = array_merge($profileIds, $ownProfileIds);
        }

        if ($user->role === 'parent') {
            $childProfileIds = AthleteProfiles::query()
                ->where('parent_id', $user->id)
                ->pluck('id')
                ->all();

            $profileIds = array_merge($profileIds, $childProfileIds);
        }

        return array_values(array_unique(array_map('intval', $profileIds)));
    }

    private function calculateBookingTotals(ProgramBooking $booking): array
    {
        $amount = (float) ($booking->amount ?? 0);
        $discount = (float) ($booking->discount ?? 0);
        $taxPercent = (float) ($booking->tax ?? 0);
        $subtotal = max(0.0, $amount - $discount);
        $taxAmount = round(($subtotal * $taxPercent) / 100, 2);
        $total = round($subtotal + $taxAmount, 2);

        return [
            'amount' => round($amount, 2),
            'discount' => round($discount, 2),
            'tax_amount' => $taxAmount,
            'total' => $total,
        ];
    }

    private function formatMoney(float $amount, string $currency): string
    {
        return strtoupper($currency) . ' ' . number_format($amount, 2);
    }

    private function paymentBadgeClass(string $status): string
    {
        return match ($status) {
            'paid' => 'success',
            'refunded' => 'warning',
            'failed' => 'danger',
            default => 'secondary',
        };
    }

    private function buildInvoiceHtml(ProgramBooking $booking, array $totals): string
    {
        $programName = e($booking->program?->program_name ?? 'Program Booking');
        $childName = e(trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? '')) ?: 'N/A');
        $currency = strtoupper((string) ($booking->currency ?: 'usd'));
        $paymentStatus = strtolower((string) ($booking->payment_status ?: 'pending'));
        $statusLabel = ucfirst($paymentStatus);
        $statusClass = $this->paymentBadgeClass($paymentStatus);
        $paidAt = optional($booking->created_at)?->format('M d, Y h:i A') ?? 'N/A';

        return '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Invoice #' . e($booking->id) . '</title>

<style>
body {
    font-family: Arial, sans-serif;
    background: #f5f7fb;
    margin: 0;
    padding: 20px;
}

.invoice-box {
    max-width: 800px;
    margin: auto;
    background: #ffffff;
    padding: 30px;
    border-radius: 10px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.05);
}

.header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
}

.header h1 {
    margin: 0;
    color: #111827;
}

.status {
    padding: 8px 15px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
    background: #e5e7eb;
}

.info {
    margin-bottom: 25px;
    font-size: 14px;
    color: #374151;
}

.section {
    margin-bottom: 25px;
}

.section h3 {
    margin-bottom: 10px;
    color: #111827;
}

.table {
    width: 100%;
    border-collapse: collapse;
}

.table th {
    background: #f3f4f6;
    padding: 10px;
    text-align: left;
}

.table td {
    padding: 10px;
    border-bottom: 1px solid #e5e7eb;
}

.total-box {
    margin-top: 20px;
    text-align: right;
}

.total-box p {
    margin: 5px 0;
}

.total-box .grand {
    font-size: 18px;
    font-weight: bold;
}

.footer {
    margin-top: 30px;
    text-align: center;
    font-size: 12px;
    color: #9ca3af;
}
</style>
</head>

<body>

<div class="invoice-box">

<div class="header">
    <h1>Invoice</h1>
    <div class="status">' . e($statusLabel) . '</div>
</div>

<div class="info">
    <strong>Invoice #:</strong> ' . e($booking->id) . '<br>
    <strong>Date:</strong> ' . e($paidAt) . '<br>
    <strong>Program:</strong> ' . $programName . '<br>
    <strong>Child:</strong> ' . $childName . '
</div>

<div class="section">
    <h3>Payment Details</h3>

    <table class="table">
        <tr>
            <th>Description</th>
            <th>Amount</th>
        </tr>

        <tr>
            <td>Program Fee</td>
            <td>' . e($this->formatMoney($totals['amount'], $currency)) . '</td>
        </tr>

        <tr>
            <td>Discount</td>
            <td>- ' . e($this->formatMoney($totals['discount'], $currency)) . '</td>
        </tr>

        <tr>
            <td>Tax (HST)</td>
            <td>' . e($this->formatMoney($totals['tax_amount'], $currency)) . '</td>
        </tr>
    </table>
</div>

<div class="total-box">
    <p>Subtotal: ' . e($this->formatMoney($totals['amount'] - $totals['discount'], $currency)) . '</p>
    <p>Tax: ' . e($this->formatMoney($totals['tax_amount'], $currency)) . '</p>
    <p class="grand">Total: ' . e($this->formatMoney($totals['total'], $currency)) . '</p>
</div>

<div class="footer">
    Thank you for your payment ❤️ <br>
    Generated by Tarkavan System
</div>

</div>

</body>
</html>';
    }

    private function buildPaymentsReportHtml(array $summary, iterable $payments): string
    {
        $rowsHtml = '';

        foreach ($payments as $payment) {
            $status = strtolower((string) ($payment['payment_status'] ?? 'pending'));
            $statusClass = $this->paymentBadgeClass($status);
            $rowsHtml .= '<tr>'
                . '<td>' . e((string) ($payment['program_name'] ?? 'N/A')) . '</td>'
                . '<td>' . e((string) ($payment['child'] ?? 'N/A')) . '</td>'
                . '<td>' . e((string) ($payment['amount_display'] ?? 'N/A')) . '</td>'
                . '<td>' . e((string) ($payment['hst_display'] ?? 'N/A')) . '</td>'
                . '<td>' . e((string) ($payment['discount_display'] ?? 'N/A')) . '</td>'
                . '<td><strong>' . e((string) ($payment['total_display'] ?? 'N/A')) . '</strong></td>'
                . '<td>' . e((string) ($payment['date'] ?? 'N/A')) . '</td>'
                . '<td><span class="badge ' . $statusClass . '">' . e(ucfirst($status)) . '</span></td>'
                . '</tr>';
        }

        return '<!DOCTYPE html>'
            . '<html lang="en">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>Player Payment Report</title>'
            . '<style>'
            . 'body{margin:0;font-family:Inter,Arial,sans-serif;background:linear-gradient(180deg,#050816 0%,#0a1020 55%,#111827 100%);color:#e5eef8;}'
            . '.sheet{max-width:1280px;margin:0 auto;padding:28px 22px 34px;}'
            . '.hero{background:radial-gradient(circle at top right,rgba(59,130,246,.16),transparent 28%),linear-gradient(135deg,#0c1222 0%,#111b33 50%,#18284a 100%);border:1px solid rgba(148,163,184,.16);border-radius:30px;padding:28px;box-shadow:0 24px 70px rgba(0,0,0,.38);position:relative;overflow:hidden;}'
            . '.hero:after{content:"";position:absolute;inset:auto -60px -80px auto;width:240px;height:240px;border-radius:999px;background:rgba(250,204,21,.12);filter:blur(8px);}'
            . '.top{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;flex-wrap:wrap;position:relative;z-index:1;}'
            . '.title{max-width:760px;}'
            . '.eyebrow{display:inline-flex;align-items:center;gap:8px;width:max-content;padding:8px 12px;border-radius:999px;background:rgba(15,23,42,.72);border:1px solid rgba(148,163,184,.18);color:#cbd5e1;font-size:11px;letter-spacing:.14em;text-transform:uppercase;}'
            . '.title h1{margin:10px 0 0;font-size:36px;line-height:1.05;letter-spacing:-.02em;}'
            . '.title p{margin:12px 0 0;color:#cbd5e1;line-height:1.65;max-width:680px;}'
            . '.summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-top:20px;position:relative;z-index:1;}'
            . '.stat{background:rgba(8,15,30,.74);border:1px solid rgba(148,163,184,.16);border-radius:22px;padding:18px 18px 16px;backdrop-filter:blur(12px);}'
            . '.stat span{display:block;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#94a3b8;margin-bottom:10px;}'
            . '.stat strong{font-size:20px;color:#f8fafc;line-height:1.35;word-break:break-word;}'
            . '.content{margin-top:20px;background:linear-gradient(180deg,#f9fbff 0%,#eef5ff 100%);color:#0f172a;border-radius:30px;padding:24px;border:1px solid rgba(148,163,184,.16);box-shadow:0 20px 60px rgba(8,15,30,.2);overflow:hidden;}'
            . '.table-wrap{overflow:auto;border-radius:20px;border:1px solid #dbe3ef;background:#fff;}'
            . '.report-table{width:100%;border-collapse:collapse;min-width:1020px;}'
            . '.report-table th,.report-table td{padding:14px 16px;border-bottom:1px solid #e6edf6;text-align:left;vertical-align:top;}'
            . '.report-table th{background:#eaf1ff;color:#0f172a;font-size:12px;text-transform:uppercase;letter-spacing:.08em;position:sticky;top:0;z-index:1;}'
            . '.report-table tr:nth-child(even) td{background:#fbfdff;}'
            . '.report-table tr:last-child td{border-bottom:none;}'
            . '.badge{display:inline-flex;align-items:center;gap:8px;border-radius:999px;padding:8px 14px;font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.08em;}'
            . '.badge.success{background:#0f2f1c;color:#9ef7be;border:1px solid rgba(110,231,183,.35);}'
            . '.badge.warning{background:#3a2a0f;color:#ffd49b;border:1px solid rgba(251,191,36,.35);}'
            . '.badge.danger{background:#3b1111;color:#ffb4b4;border:1px solid rgba(248,113,113,.35);}'
            . '.badge.secondary{background:#1f2937;color:#d1d5db;border:1px solid rgba(148,163,184,.28);}'
            . '.footer{margin-top:18px;color:#94a3b8;font-size:12px;text-align:center;letter-spacing:.02em;}'
            . '@media print{body{background:#fff;color:#000}.hero,.content{box-shadow:none}}'
            . '@media (max-width: 900px){.summary{grid-template-columns:1fr 1fr}.sheet{padding:12px}.hero,.content{border-radius:22px}.title h1{font-size:30px}}'
            . '@media (max-width: 640px){.summary{grid-template-columns:1fr}}'
            . '</style>'
            . '</head>'
            . '<body>'
            . '<div class="sheet">'
            . '<div class="hero">'
            . '<div class="top">'
            . '<div class="title">'
            . '<div class="eyebrow">Tarkavan Payment Export</div>'
            . '<h1>Player Payment Report</h1>'
            . '<p>Full payment history download for the authenticated player or parent account. The layout is designed for quick review, sharing, and printing.</p>'
            . '</div>'
            . '<div class="badge secondary">Export Ready</div>'
            . '</div>'
            . '<div class="summary">'
            . '<div class="stat"><span>Total Paid</span><strong>' . e($this->formatMoney((float) ($summary['total_paid'] ?? 0), 'usd')) . '</strong></div>'
            . '<div class="stat"><span>Pending Payments</span><strong>' . e($this->formatMoney((float) ($summary['pending_payments'] ?? 0), 'usd')) . '</strong></div>'
            . '<div class="stat"><span>Refunded Payments</span><strong>' . e($this->formatMoney((float) ($summary['refunded_payments'] ?? 0), 'usd')) . '</strong></div>'
            . '<div class="stat"><span>Total Transactions</span><strong>' . e((string) ($summary['total_transactions'] ?? 0)) . '</strong></div>'
            . '</div>'
            . '</div>'
            . '<div class="content">'
            . '<div class="table-wrap">'
            . '<table class="report-table">'
            . '<thead><tr><th>Program Name</th><th>Child</th><th>Amount</th><th>HST</th><th>Discount</th><th>Total</th><th>Date</th><th>Status</th></tr></thead>'
            . '<tbody>' . ($rowsHtml !== '' ? $rowsHtml : '<tr><td colspan="8">No payment records found.</td></tr>') . '</tbody>'
            . '</table>'
            . '</div>'
            . '<div class="footer">Downloaded from Tarkavan payment API</div>'
            . '</div>'
            . '</div>'
            . '</body>'
            . '</html>';
    }

    public function playerPaymentList(Request $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authentication required.',
                ], 401);
            }

            if (! in_array($user->role, ['player', 'parent'], true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only player or parent accounts can view payment history.',
                ], 403);
            }

            $athleteProfileIds = $this->getAthleteProfileIdsForUser($user);

            if (empty($athleteProfileIds)) {
                return response()->json([
                    'status' => true,
                    'message' => 'No payment history found.',
                    'data' => [
                        'summary' => [
                            'total_paid' => 0,
                            'pending_payments' => 0,
                            'refunded_payments' => 0,
                            'total_transactions' => 0,
                        ],
                        'payments' => [],
                        'pagination' => [
                            'current_page' => 1,
                            'per_page' => (int) $request->input('per_page', 15),
                            'total' => 0,
                            'last_page' => 1,
                        ],
                    ],
                ], 200);
            }

            $statusFilter = strtolower((string) $request->input('status', 'all'));
            $search = trim((string) $request->input('search', ''));
            $perPage = (int) $request->input('per_page', 15);
            $perPage = max(1, min(100, $perPage));

            $baseQuery = ProgramBooking::query()
                ->with([
                    'program:id,program_name,program_photo',
                    'athlete:id,name,last_name,parent_id,user_id',
                ])
                ->whereIn('athlete_profile_id', $athleteProfileIds)
                ->latest('id');

            if ($statusFilter !== 'all') {
                $baseQuery->where('payment_status', $statusFilter);
            }

            if ($search !== '') {
                $baseQuery->whereHas('program', function ($query) use ($search): void {
                    $query->where('program_name', 'like', '%' . $search . '%');
                });
            }

            $summaryQuery = ProgramBooking::query()
                ->with(['program:id,program_name,program_photo', 'athlete:id,name,last_name,parent_id,user_id'])
                ->whereIn('athlete_profile_id', $athleteProfileIds);

            $allBookings = $summaryQuery->get();

            $summary = [
                'total_paid' => round((float) $allBookings->where('payment_status', 'paid')->sum(fn(ProgramBooking $booking) => $this->calculateBookingTotals($booking)['total']), 2),
                'pending_payments' => round((float) $allBookings->where('payment_status', 'pending')->sum(fn(ProgramBooking $booking) => $this->calculateBookingTotals($booking)['total']), 2),
                'refunded_payments' => round((float) $allBookings->where('payment_status', 'refunded')->sum(fn(ProgramBooking $booking) => $this->calculateBookingTotals($booking)['total']), 2),
                'total_transactions' => $allBookings->count(),
            ];

            $paginatedPayments = $baseQuery->paginate($perPage);

            $payments = $paginatedPayments->getCollection()->map(function (ProgramBooking $booking) {
                $totals = $this->calculateBookingTotals($booking);
                $currency = strtoupper((string) ($booking->currency ?: 'usd'));
                $child = trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? ''));

                return [
                    'id' => $booking->id,
                    'program_name' => $booking->program?->program_name ?? 'N/A',
                    'program_photo' => $booking->program?->program_photo ? asset($booking->program->program_photo) : null,
                    'child' => $child !== '' ? $child : 'N/A',
                    'amount' => $totals['amount'],
                    'amount_display' => $currency . ' ' . number_format($totals['amount'], 2),
                    'hst' => $totals['tax_amount'],
                    'hst_display' => $currency . ' ' . number_format($totals['tax_amount'], 2),
                    'discount' => $totals['discount'],
                    'discount_display' => '- ' . $currency . ' ' . number_format($totals['discount'], 2),
                    'total' => $totals['total'],
                    'total_display' => $currency . ' ' . number_format($totals['total'], 2),
                    'date' => optional($booking->created_at)?->toDateString(),
                    'payment_status' => $booking->payment_status ?? 'pending',
                    'currency' => $currency,
                ];
            })->values();

            return response()->json([
                'status' => true,
                'message' => 'Player payment history retrieved successfully.',
                'data' => [
                    'summary' => $summary,
                    'payments' => $payments,
                    'pagination' => [
                        'current_page' => $paginatedPayments->currentPage(),
                        'per_page' => $paginatedPayments->perPage(),
                        'total' => $paginatedPayments->total(),
                        'last_page' => $paginatedPayments->lastPage(),

                        'next_page_url' => $paginatedPayments->nextPageUrl(),
                        'prev_page_url' => $paginatedPayments->previousPageUrl(),
                        'first_page_url' => $paginatedPayments->url(1),
                        'last_page_url' => $paginatedPayments->url($paginatedPayments->lastPage()),
                    ],
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function downloadInvoice(Request $request, $booking_id = null): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        try {
            $user = Auth::guard('api')->user();

            if (! $user) {
                return response()->json([
                    'status' => false,
                    'message' => 'Authentication required.',
                ], 401);
            }

            if (! in_array($user->role, ['player', 'parent'], true)) {
                return response()->json([
                    'status' => false,
                    'message' => 'Only player or parent accounts can download invoices.',
                ], 403);
            }

            $athleteProfileIds = $this->getAthleteProfileIdsForUser($user);

            if (empty($athleteProfileIds)) {
                return response()->json([
                    'status' => false,
                    'message' => 'No associated athlete profiles found for the user.',
                ], 404);
            }

            if ($booking_id !== null) {
                $booking = ProgramBooking::query()
                    ->whereIn('athlete_profile_id', $athleteProfileIds)
                    ->where('id', $booking_id)
                    ->with([
                        'program:id,program_name,program_photo',
                        'athlete:id,name,last_name,parent_id,user_id',
                    ])
                    ->first();

                if (! $booking) {
                    return response()->json(['status' => false, 'message' => 'Booking not found.'], 404);
                }

                $totals = $this->calculateBookingTotals($booking);
                $html = $this->buildInvoiceHtml($booking, $totals);
                $fileName = 'booking-invoice-' . $booking->id . '.pdf';

                return Pdf::loadHTML($html)
                    ->setPaper('a4', 'portrait')
                    ->download($fileName);
            }

            $statusFilter = strtolower((string) $request->input('status', 'all'));
            $search = trim((string) $request->input('search', ''));

            $query = ProgramBooking::query()
                ->with([
                    'program:id,program_name,program_photo',
                    'athlete:id,name,last_name,parent_id,user_id',
                ])
                ->whereIn('athlete_profile_id', $athleteProfileIds)
                ->latest('id');

            if ($statusFilter !== 'all') {
                $query->where('payment_status', $statusFilter);
            }

            if ($search !== '') {
                $query->whereHas('program', function ($programQuery) use ($search): void {
                    $programQuery->where('program_name', 'like', '%' . $search . '%');
                });
            }

            $bookings = $query->get();

            $summary = [
                'total_paid' => round((float) $bookings->where('payment_status', 'paid')->sum(fn(ProgramBooking $booking) => $this->calculateBookingTotals($booking)['total']), 2),
                'pending_payments' => round((float) $bookings->where('payment_status', 'pending')->sum(fn(ProgramBooking $booking) => $this->calculateBookingTotals($booking)['total']), 2),
                'refunded_payments' => round((float) $bookings->where('payment_status', 'refunded')->sum(fn(ProgramBooking $booking) => $this->calculateBookingTotals($booking)['total']), 2),
                'total_transactions' => $bookings->count(),
            ];

            $payments = $bookings->map(function (ProgramBooking $booking) {
                $totals = $this->calculateBookingTotals($booking);
                $currency = strtoupper((string) ($booking->currency ?: 'usd'));
                $child = trim((string) ($booking->athlete?->name ?? '') . ' ' . (string) ($booking->athlete?->last_name ?? ''));

                return [
                    'program_name' => $booking->program?->program_name ?? 'N/A',
                    'child' => $child !== '' ? $child : 'N/A',
                    'amount_display' => $currency . ' ' . number_format($totals['amount'], 2),
                    'hst_display' => $currency . ' ' . number_format($totals['tax_amount'], 2),
                    'discount_display' => '- ' . $currency . ' ' . number_format($totals['discount'], 2),
                    'total_display' => $currency . ' ' . number_format($totals['total'], 2),
                    'date' => optional($booking->created_at)?->toDateString(),
                    'payment_status' => $booking->payment_status ?? 'pending',
                ];
            })->values();

            $html = $this->buildPaymentsReportHtml($summary, $payments);
            $fileName = 'player-payment-report.pdf';

            return Pdf::loadHTML($html)
                ->setPaper('a4', 'landscape')
                ->download($fileName);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
