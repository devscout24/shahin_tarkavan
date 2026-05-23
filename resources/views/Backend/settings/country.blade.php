@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #countries-table td,
    #countries-table th {
        white-space: nowrap;
    }

    #countries-table .d-flex {
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
                    <h5 class="mb-0">Country List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="countries-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>ISO Code</th>
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
                    <h5 class="mb-0" id="form-title">Create Country</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none" id="form-errors"></div>
                    <div class="alert alert-success d-none" id="form-success"></div>

                    <form id="country-form">
                        @csrf
                        <input type="hidden" id="country_id" name="country_id">

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Enter country name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">ISO Code</label>
                            <input type="text" name="iso_code" id="iso_code" class="form-control" placeholder="e.g. US">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="d-grid d-sm-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submit-btn">Create Country</button>
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
    const $form = $('#country-form');
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
        $('#country_id').val('');
        $('#status').val('active');
        $('#form-title').text('Create Country');
        $('#submit-btn').text('Create Country');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');
    }

    const table = $('#countries-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        ajax: '{{ route('admin.settings.countries.data') }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'iso_code', name: 'iso_code' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        const countryId = $('#country_id').val();
        const isUpdate = countryId !== '';
        const url = isUpdate
            ? '{{ url('admin/settings/countries') }}/' + countryId
            : '{{ route('admin.settings.countries.store') }}';

        const payload = {
            name: $('#name').val(),
            iso_code: $('#iso_code').val(),
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

    $('#countries-table').on('click', '.js-edit-country', function () {
        const countryId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        $.ajax({
            url: '{{ url('admin/settings/countries') }}/' + countryId + '/edit',
            method: 'GET',
            success: function (res) {
                const country = res.data;
                $('#country_id').val(country.id);
                $('#name').val(country.name || '');
                $('#iso_code').val(country.iso_code || '');
                $('#status').val(country.status || 'active');
                $('#form-title').text('Update Country');
                $('#submit-btn').text('Update Country');
            },
            error: function (xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#countries-table').on('click', '.js-delete-country', function () {
        const countryId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        if (!window.confirm('Are you sure you want to delete this country?')) {
            return;
        }

        $.ajax({
            url: '{{ url('admin/settings/countries') }}/' + countryId,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'DELETE'
            },
            success: function (res) {
                $successBox.removeClass('d-none').html(res.message || 'Deleted successfully.');
                table.ajax.reload(null, false);

                if ($('#country_id').val() === String(countryId)) {
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

