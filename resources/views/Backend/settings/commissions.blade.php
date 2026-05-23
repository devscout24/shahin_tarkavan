@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #commissions-table td,
    #commissions-table th {
        white-space: nowrap;
    }

    #commissions-table .d-flex {
        flex-wrap: wrap;
    }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row g-4">
        <div class="col-12 col-lg-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Commission List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="commissions-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Applies To</th>
                                    <th>Specific Target</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0" id="form-title">Create Commission</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none" id="form-errors"></div>
                    <div class="alert alert-success d-none" id="form-success"></div>

                    <form id="commission-form">
                        @csrf
                        <input type="hidden" id="commission_id" name="commission_id">

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Platform Commission" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Applies To</label>
                            <select name="applies_to" id="applies_to" class="form-select" required>
                                <option value="all">All Programs</option>
                                <option value="coach">Coach Programs</option>
                                <option value="club">Club Programs</option>
                            </select>
                        </div>

                        <div class="mb-3" id="target-user-wrapper" style="display: none;">
                            <label class="form-label">Specific Coach/Club (Optional)</label>
                            <select name="user_id" id="user_id" class="form-select">
                                <option value="">All Selected Role</option>
                            </select>
                            <small class="text-muted">Leave empty to apply this commission to all users of selected role.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" id="type" class="form-select" required>
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" min="0" name="amount" id="amount" class="form-control" placeholder="10.00" required>
                            <small class="text-muted">For percentage type, use value like 10 for 10%.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="d-grid d-sm-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submit-btn">Create Commission</button>
                            <button type="button" class="btn btn-outline-secondary" id="reset-btn">Reset</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script>
$(function () {
    const $form = $('#commission-form');
    const $errorBox = $('#form-errors');
    const $successBox = $('#form-success');
    const targetUsers = @json($targetUsers ?? []);

    function rebuildTargetUsers(selectedRole, selectedUserId = '') {
        const $select = $('#user_id');
        const role = (selectedRole || 'all').toLowerCase();
        $select.empty();
        $select.append('<option value="">All Selected Role</option>');

        if (role === 'all') {
            $('#target-user-wrapper').hide();
            return;
        }

        const options = targetUsers.filter(function (user) {
            return (user.role || '').toLowerCase() === role;
        });

        options.forEach(function (user) {
            const selected = String(selectedUserId) === String(user.id) ? ' selected' : '';
            $select.append('<option value="' + user.id + '"' + selected + '>' + user.label + '</option>');
        });

        $('#target-user-wrapper').show();
    }

    function showErrors(xhr) {
        const errors = xhr.responseJSON?.errors || {};
        let html = '';

        Object.keys(errors).forEach(function (key) {
            html += '<div>' + errors[key][0] + '</div>';
        });

        if (!html && xhr.responseJSON?.message) {
            html = '<div>' + xhr.responseJSON.message + '</div>';
        }

        $errorBox.removeClass('d-none').html(html || '<div>Something went wrong.</div>');
    }

    function resetForm() {
        $form[0].reset();
        $('#commission_id').val('');
        $('#applies_to').val('all');
        rebuildTargetUsers('all', '');
        $('#type').val('percentage');
        $('#status').val('active');
        $('#form-title').text('Create Commission');
        $('#submit-btn').text('Create Commission');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');
    }

    const table = $('#commissions-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        ajax: '{{ route('admin.settings.commissions.data') }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'applies_to_badge', name: 'applies_to', orderable: false, searchable: false },
            { data: 'target_user', name: 'user_id', orderable: false, searchable: false },
            { data: 'type_badge', name: 'type', orderable: false, searchable: false },
            { data: 'display_amount', name: 'amount', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        const commissionId = $('#commission_id').val();
        const isUpdate = commissionId !== '';
        const payload = {
            name: $('#name').val(),
            applies_to: $('#applies_to').val(),
            user_id: $('#user_id').val(),
            type: $('#type').val(),
            amount: $('#amount').val(),
            status: $('#status').val(),
            _token: '{{ csrf_token() }}'
        };

        if (isUpdate) {
            payload._method = 'PUT';
        }

        const url = isUpdate
            ? '{{ url('admin/settings/commissions') }}/' + commissionId
            : '{{ route('admin.settings.commissions.store') }}';

        $.ajax({
            url: url,
            method: 'POST',
            data: payload,
            success: function (res) {
                $successBox.removeClass('d-none').html(res.message || 'Saved successfully.');
                table.ajax.reload(null, false);

                if (!isUpdate) {
                    resetForm();
                }
            },
            error: function (xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#commissions-table').on('click', '.js-edit-commission', function () {
        const commissionId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        $.ajax({
            url: '{{ url('admin/settings/commissions') }}/' + commissionId + '/edit',
            method: 'GET',
            success: function (res) {
                const commission = res.data;
                $('#commission_id').val(commission.id);
                $('#name').val(commission.name || '');
                $('#applies_to').val(commission.applies_to || 'all');
                rebuildTargetUsers(commission.applies_to || 'all', commission.user_id || '');
                $('#type').val(commission.type || 'percentage');
                $('#amount').val(commission.amount || '');
                $('#status').val(commission.status || 'active');
                $('#form-title').text('Update Commission');
                $('#submit-btn').text('Update Commission');
            },
            error: function (xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#commissions-table').on('click', '.js-delete-commission', function () {
        const commissionId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        if (!window.confirm('Are you sure you want to delete this commission?')) {
            return;
        }

        $.ajax({
            url: '{{ url('admin/settings/commissions') }}/' + commissionId,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'DELETE'
            },
            success: function (res) {
                $successBox.removeClass('d-none').html(res.message || 'Deleted successfully.');
                table.ajax.reload(null, false);

                if ($('#commission_id').val() === String(commissionId)) {
                    resetForm();
                }
            },
            error: function (xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#reset-btn').on('click', function () {
        resetForm();
    });

    $('#applies_to').on('change', function () {
        rebuildTargetUsers($(this).val(), '');
    });

    rebuildTargetUsers($('#applies_to').val(), '');
});
</script>
@endpush

