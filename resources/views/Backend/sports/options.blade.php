@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #sport-options-table td,
    #sport-options-table th {
        white-space: nowrap;
    }

    #sport-options-table .d-flex {
        flex-wrap: wrap;
    }
</style>
@endpush

@section('title', 'Sport Options')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <h5 class="mb-0">Sport Options</h5>
                    <small class="text-muted">Manage the sport lists used by player and coach flows.</small>
                </div>
                <button type="button" class="btn btn-primary" id="open-form-btn">Add Sport Option</button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered" id="sport-options-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Audience</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="sportOptionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="form-title">Create Sport Option</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="sport-option-form">
                    @csrf
                    <input type="hidden" id="option-id" value="">
                    <div class="mb-3">
                        <label for="name" class="form-label">Name</label>
                        <input type="text" name="name" id="name" class="form-control" placeholder="Football (Soccer)" required>
                    </div>
                    <div class="mb-3">
                        <label for="audience" class="form-label">Audience</label>
                        <select name="audience" id="audience" class="form-select" required>
                            <option value="player">Player / Parent</option>
                            <option value="coach">Coach / Program</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select name="status" id="status" class="form-select" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    <div class="d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary" id="save-btn">Save</button>
                    </div>
                </form>
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
        const modal = new bootstrap.Modal(document.getElementById('sportOptionModal'));
        const form = $('#sport-option-form');

        const table = $('#sport-options-table').DataTable({
            processing: true,
            serverSide: true,
            autoWidth: false,
            responsive: false,
            scrollX: true,
            ajax: '{{ route('admin.sport-options.data') }}',
            columns: [
                { data: 'id', name: 'id' },
                { data: 'name', name: 'name' },
                { data: 'audience_badge', name: 'audience', orderable: false, searchable: false },
                { data: 'status_badge', name: 'status', orderable: false, searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false },
            ],
        });

        $('#open-form-btn').on('click', function () {
            form.trigger('reset');
            $('#option-id').val('');
            $('#form-title').text('Create Sport Option');
            $('#save-btn').text('Save');
            modal.show();
        });

        form.on('submit', function (event) {
            event.preventDefault();

            const optionId = $('#option-id').val();
            const url = optionId ? '{{ url('admin/sport-options') }}/' + optionId : '{{ route('admin.sport-options.store') }}';
            const method = optionId ? 'PUT' : 'POST';

            $.ajax({
                url: url,
                method: method,
                data: form.serialize(),
                success: function (response) {
                    modal.hide();
                    form.trigger('reset');
                    table.ajax.reload(null, false);
                    toastr.success(response.message || 'Saved successfully.');
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to save sport option.';
                    toastr.error(message);
                }
            });
        });

        $('#sport-options-table').on('click', '.js-edit-option', function () {
            const optionId = $(this).data('id');

            $.ajax({
                url: '{{ url('admin/sport-options') }}/' + optionId + '/edit',
                method: 'GET',
                success: function (response) {
                    const option = response.data;
                    $('#option-id').val(option.id);
                    $('#name').val(option.name);
                    $('#audience').val(option.audience);
                    $('#status').val(option.status);
                    $('#form-title').text('Update Sport Option');
                    $('#save-btn').text('Update');
                    modal.show();
                },
                error: function () {
                    toastr.error('Unable to load sport option.');
                }
            });
        });

        $('#sport-options-table').on('click', '.js-delete-option', function () {
            const optionId = $(this).data('id');

            if (!window.confirm('Are you sure you want to delete this sport option?')) {
                return;
            }

            $.ajax({
                url: '{{ url('admin/sport-options') }}/' + optionId,
                method: 'DELETE',
                data: { _token: '{{ csrf_token() }}' },
                success: function (response) {
                    table.ajax.reload(null, false);
                    toastr.success(response.message || 'Deleted successfully.');
                },
                error: function (xhr) {
                    const message = xhr.responseJSON?.message || 'Failed to delete sport option.';
                    toastr.error(message);
                }
            });
        });
    });
</script>
@endpush

