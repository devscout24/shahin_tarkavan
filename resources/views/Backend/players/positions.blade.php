@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #player-positions-table td,
    #player-positions-table th {
        white-space: nowrap;
    }

    #player-positions-table .d-flex {
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
                    <h5 class="mb-0">Player Position List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="player-positions-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Position Name</th>
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
                    <h5 class="mb-0" id="form-title">Create Player Position</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none" id="form-errors"></div>
                    <div class="alert alert-success d-none" id="form-success"></div>

                    <form id="player-position-form">
                        @csrf
                        <input type="hidden" id="player_position_id" name="player_position_id">

                        <div class="mb-3">
                            <label class="form-label">Position Name</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Goalkeeper, Defender" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="d-grid d-sm-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submit-btn">Create Position</button>
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
    const $form = $('#player-position-form');
    const $errorBox = $('#form-errors');
    const $successBox = $('#form-success');

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
        $('#player_position_id').val('');
        $('#status').val('active');
        $('#form-title').text('Create Player Position');
        $('#submit-btn').text('Create Position');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');
    }

    const table = $('#player-positions-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        ajax: '{{ route('admin.player-positions.data') }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        const positionId = $('#player_position_id').val();
        const isUpdate = positionId !== '';
        const url = isUpdate
            ? '{{ url('admin/player-positions') }}/' + positionId
            : '{{ route('admin.player-positions.store') }}';

        const payload = {
            name: $('#name').val(),
            status: $('#status').val(),
            _token: '{{ csrf_token() }}'
        };

        if (isUpdate) {
            payload._method = 'PUT';
        }

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

    $('#player-positions-table').on('click', '.js-edit-position', function () {
        const positionId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        $.ajax({
            url: '{{ url('admin/player-positions') }}/' + positionId + '/edit',
            method: 'GET',
            success: function (res) {
                const position = res.data;
                $('#player_position_id').val(position.id);
                $('#name').val(position.name || '');
                $('#status').val(position.status || 'active');
                $('#form-title').text('Update Player Position');
                $('#submit-btn').text('Update Position');
            },
            error: function (xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#player-positions-table').on('click', '.js-delete-position', function () {
        const positionId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        if (!window.confirm('Are you sure you want to delete this player position?')) {
            return;
        }

        $.ajax({
            url: '{{ url('admin/player-positions') }}/' + positionId,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'DELETE'
            },
            success: function (res) {
                $successBox.removeClass('d-none').html(res.message || 'Deleted successfully.');
                table.ajax.reload(null, false);

                if ($('#player_position_id').val() === String(positionId)) {
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
});
</script>
@endpush

