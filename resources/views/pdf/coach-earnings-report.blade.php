<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Coach Earnings Report</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1f2937;
        }

        .header {
            margin-bottom: 18px;
            border-bottom: 2px solid #111827;
            padding-bottom: 12px;
        }

        .title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 6px 0;
        }

        .subtext {
            color: #6b7280;
            margin: 0;
        }

        .summary {
            width: 100%;
            margin-bottom: 18px;
            border-collapse: collapse;
        }

        .summary td {
            width: 25%;
            border: 1px solid #d1d5db;
            padding: 10px;
            vertical-align: top;
        }

        .summary .label {
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 4px;
        }

        .summary .value {
            font-size: 16px;
            font-weight: 700;
            color: #111827;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            border: 1px solid #d1d5db;
            padding: 8px;
            text-align: left;
            vertical-align: top;
        }

        .table th {
            background: #111827;
            color: #ffffff;
            font-size: 11px;
            text-transform: uppercase;
        }

        .muted {
            color: #6b7280;
        }

        .right {
            text-align: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Coach Earnings Report</h1>
        <p class="subtext">{{ trim((string) $coach->name . ' ' . (string) $coach->last_name) }} @if(!empty($coach->currentPosition?->name)) - {{ $coach->currentPosition?->name }} @endif</p>
        <p class="subtext">Generated on {{ now()->format('F d, Y') }}</p>
    </div>

    <table class="summary">
        <tr>
            <td>
                <div class="label">Total Bookings</div>
                <div class="value">{{ $summary['total_bookings'] }}</div>
            </td>
            <td>
                <div class="label">Gross Total</div>
                <div class="value">USD {{ number_format($summary['gross_total'], 2) }}</div>
            </td>
            <td>
                <div class="label">Commission Total</div>
                <div class="value">USD {{ number_format($summary['commission_total'], 2) }}</div>
            </td>
            <td>
                <div class="label">Net Earnings</div>
                <div class="value">USD {{ number_format($summary['net_total'], 2) }}</div>
            </td>
        </tr>
    </table>

    <table class="table">
        <thead>
            <tr>
                <th>#</th>
                <th>Program</th>
                <th>Player</th>
                <th>Parent</th>
                <th>Date</th>
                <th class="right">Gross</th>
                <th class="right">Commission</th>
                <th class="right">Net</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>{{ $row['id'] }}</td>
                    <td>
                        <strong>{{ $row['program_name'] }}</strong><br>
                        {{--  <span class="muted">{{ $row['commission_label'] }}</span>  --}}
                    </td>
                    <td>{{ $row['athlete_name'] }}</td>
                    <td>{{ $row['parent_name'] }}</td>
                    <td>{{ $row['booking_date'] ?? 'N/A' }}</td>
                    <td class="right">{{ $row['currency'] }} {{ number_format($row['gross'], 2) }}</td>
                    <td class="right">{{ $row['currency'] }} {{ number_format($row['commission_amount'], 2) }}</td>
                    <td class="right">{{ $row['currency'] }} {{ number_format($row['coach_pay'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="muted">No paid bookings found for this coach.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
