@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #booking-payments-table td,
    #booking-payments-table th {
        white-space: nowrap;
        vertical-align: middle;
    }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Program Booking Payments</h5>
            <small class="text-muted">Superadmin can payout to coach Stripe connected account</small>
        </div>
        <div class="card-body">
            <div class="alert alert-danger d-none" id="payment-errors"></div>
            <div class="alert alert-success d-none" id="payment-success"></div>

            <div class="table-responsive">
                <table class="table table-bordered" id="booking-payments-table" style="width:100%">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>Program</th>
                        <th>Athlete</th>
                        <th>Parent</th>
                        <th>Coach</th>
                        <th>Amount</th>
                        <th>Coach Commission</th>
                        <th>Coach Pay</th>
                        <th>Payment</th>
                        <th>Payout</th>
                        <th>Action</th>
                    </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="bookingViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Booking Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="booking-view-content">
                <div class="text-muted">Loading booking details...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="payoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="payout-form" novalidate>
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Send Stripe Payout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="booking_id" name="booking_id">
                    <input type="hidden" id="stripe_account_id" name="stripe_account_id">

                    <div class="mb-3">
                        <label class="form-label">Coach Stripe Account ID</label>
                        <input type="text" class="form-control" id="stripe_account_id_display" placeholder="Auto fetched from coach account" readonly>
                        <small class="text-muted">Auto fetched from coach connected Stripe account.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Payout Amount</label>
                        <input type="number" step="0.01" min="1" class="form-control" id="amount" name="amount" required>
                        <small class="text-muted" id="amount-hint"></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary" id="payout-submit">Send Payout</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function () {
    const $errorBox = $('#payment-errors');
    const $successBox = $('#payment-success');
    const payoutModal = new bootstrap.Modal(document.getElementById('payoutModal'));
    const bookingViewModal = new bootstrap.Modal(document.getElementById('bookingViewModal'));

    function showError(message) {
        $successBox.addClass('d-none').html('');
        $errorBox.removeClass('d-none').html('<div>' + message + '</div>');
    }

    function showSuccess(message) {
        $errorBox.addClass('d-none').html('');
        $successBox.removeClass('d-none').html('<div>' + message + '</div>');
    }

    const table = $('#booking-payments-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        ajax: '{{ route('admin.program-bookings.data') }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'program_name', name: 'program.program_name' },
            { data: 'athlete_name', name: 'athlete.name' },
            { data: 'parent_name', name: 'parent.name' },
            { data: 'coach_name', name: 'coach.name' },
            { data: 'amount_display', name: 'amount' },
            { data: 'commission_display', name: 'commission_display', orderable: false, searchable: false },
            { data: 'coach_pay_display', name: 'coach_pay_display', orderable: false, searchable: false },
            { data: 'payment_badge', name: 'payment_status', orderable: false, searchable: false },
            { data: 'payout_badge', name: 'payout_status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $('#booking-payments-table').on('click', '.js-view-booking', function () {
        const bookingId = $(this).data('id');
        $('#booking-view-content').html('<div class="text-muted">Loading booking details...</div>');
        bookingViewModal.show();

        $.ajax({
            url: '{{ url('admin/program-bookings') }}/' + bookingId + '/view',
            method: 'GET',
            success: function (res) {
                if (!res || !res.status) {
                    $('#booking-view-content').html('<div class="alert alert-danger mb-0">Unable to load booking details.</div>');
                    return;
                }

                const d = res.data || {};
                const rows = [
                    ['Booking ID', d.id ?? '-'],
                    ['Program', d.program?.name ?? '-'],
                    ['Program Location', d.program?.location ?? '-'],
                    ['Program Start', d.program?.start ?? '-'],
                    ['Program End', d.program?.end ?? '-'],
                    ['Athlete', d.athlete?.name ?? '-'],
                    ['Parent', d.parent?.name ?? '-'],
                    ['Coach', d.coach?.name ?? '-'],
                    ['Booking Time', d.booking_time ?? '-'],
                    ['Status', d.status ?? '-'],
                    ['Payment Status', d.payment_status ?? '-'],
                    ['Payout Status', d.payout_status ?? '-'],
                    ['Main Amount', `${d.currency ?? ''} ${Number(d.amount ?? 0).toFixed(2)}`],
                    ['Discount', `${d.currency ?? ''} ${Number(d.discount ?? 0).toFixed(2)}`],
                    ['Tax (%)', `${Number(d.tax ?? 0).toFixed(2)}%`],
                    ['HST Amount', `${d.currency ?? ''} ${Number(d.tax_amount ?? 0).toFixed(2)}`],
                    ['Payable Amount', `${d.currency ?? ''} ${Number(d.payable_amount ?? 0).toFixed(2)}`],
                    ['Commission', `${d.commission?.label ?? 'N/A'} / ${d.currency ?? ''} ${Number(d.commission?.amount ?? 0).toFixed(2)}`],
                    ['Coach Pay', `${d.currency ?? ''} ${Number(d.coach_pay ?? 0).toFixed(2)}`],
                    ['Payment Intent', d.stripe?.payment_intent_id ?? '-'],
                    ['Transfer ID', d.stripe?.transfer_id ?? '-']
                ];

                let html = '';
                if (d.program?.photo) {
                    html += `<div class="mb-3"><img src="${d.program.photo}" alt="Program Photo" class="img-fluid rounded" style="max-height:220px; object-fit:cover;"></div>`;
                }

                html += '<div class="table-responsive"><table class="table table-sm table-bordered mb-0"><tbody>';
                rows.forEach(([label, value]) => {
                    html += `<tr><th style="width:220px;">${label}</th><td>${value ?? '-'}</td></tr>`;
                });
                html += '</tbody></table></div>';

                $('#booking-view-content').html(html);
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to fetch booking details.';
                $('#booking-view-content').html('<div class="alert alert-danger mb-0">' + msg + '</div>');
            }
        });
    });

    $('#booking-payments-table').on('click', '.js-open-payout', function () {
        const bookingId = $(this).data('id');
        const amount = Number($(this).data('amount') || 0);
        const stripeAccountId = String($(this).data('stripe-account-id') || '');
        const currency = String($(this).data('currency') || 'usd').toUpperCase();

        $('#booking_id').val(bookingId);
        $('#amount').val(amount.toFixed(2));
        $('#amount-hint').text('Max payout amount: ' + currency + ' ' + amount.toFixed(2));
        $('#stripe_account_id').val(stripeAccountId);
        $('#stripe_account_id_display').val(stripeAccountId || 'Not connected');

        payoutModal.show();
    });

    $('#payout-form').on('submit', function (e) {
        e.preventDefault();

        const bookingId = $('#booking_id').val();
        const payload = {
            _token: '{{ csrf_token() }}',
            stripe_account_id: $('#stripe_account_id').val(),
            amount: $('#amount').val()
        };

        $('#payout-submit').prop('disabled', true).text('Processing...');

        $.ajax({
            url: '{{ url('admin/program-bookings') }}/' + bookingId + '/payout',
            method: 'POST',
            data: payload,
            success: function (res) {
                showSuccess(res.message || 'Payout sent successfully.');
                payoutModal.hide();
                table.ajax.reload(null, false);
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to send payout.';
                showError(msg);
            },
            complete: function () {
                $('#payout-submit').prop('disabled', false).text('Send Payout');
            }
        });
    });
});
</script>
@endpush
