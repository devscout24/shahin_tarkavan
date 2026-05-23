@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #cities-table td,
    #cities-table th {
        white-space: nowrap;
    }

    #cities-table .d-flex {
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
                    <h5 class="mb-0">City List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="cities-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Country</th>
                                    <th>Name</th>
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
                    <h5 class="mb-0" id="form-title">Create City</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none" id="form-errors"></div>
                    <div class="alert alert-success d-none" id="form-success"></div>

                    <form id="city-form">
                        @csrf
                        <input type="hidden" id="city_id" name="city_id">

                        <div class="mb-3">
                            <label class="form-label">Country</label>
                            <select name="country_id" id="country_id" class="form-select" required>
                                <option value="">Select country</option>
                                @foreach($countries as $country)
                                    <option value="{{ $country->id }}">{{ $country->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Enter city name" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="d-grid d-sm-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submit-btn">Create City</button>
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
    const $form = $('#city-form');
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
        $('#city_id').val('');
        $('#status').val('active');
        $('#form-title').text('Create City');
        $('#submit-btn').text('Create City');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');
    }

    const table = $('#cities-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        ajax: '{{ route('admin.settings.cities.data') }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'country_name', name: 'country.name', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        const cityId = $('#city_id').val();
        const isUpdate = cityId !== '';
        const url = isUpdate
            ? '{{ url('admin/settings/cities') }}/' + cityId
            : '{{ route('admin.settings.cities.store') }}';

        const payload = {
            country_id: $('#country_id').val(),
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

    $('#cities-table').on('click', '.js-edit-city', function () {
        const cityId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        $.ajax({
            url: '{{ url('admin/settings/cities') }}/' + cityId + '/edit',
            method: 'GET',
            success: function (res) {
                const city = res.data;
                $('#city_id').val(city.id);
                $('#country_id').val(city.country_id || '');
                $('#name').val(city.name || '');
                $('#status').val(city.status || 'active');
                $('#form-title').text('Update City');
                $('#submit-btn').text('Update City');
            },
            error: function (xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#cities-table').on('click', '.js-delete-city', function () {
        const cityId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        if (!window.confirm('Are you sure you want to delete this city?')) {
            return;
        }

        $.ajax({
            url: '{{ url('admin/settings/cities') }}/' + cityId,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'DELETE'
            },
            success: function (res) {
                $successBox.removeClass('d-none').html(res.message || 'Deleted successfully.');
                table.ajax.reload(null, false);

                if ($('#city_id').val() === String(cityId)) {
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

