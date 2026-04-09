@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
<style>
    #subscription-plans-table td,
    #subscription-plans-table th {
        white-space: nowrap;
    }

    #subscription-plans-table .d-flex {
        flex-wrap: wrap;
    }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="row g-4">
        <div class="col-12 col-xl-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Subscription Plan List</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="subscription-plans-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Discount</th>
                                    <th>Billing</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0" id="form-title">Create Subscription Plan</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-danger d-none" id="form-errors"></div>
                    <div class="alert alert-success d-none" id="form-success"></div>

                    <form id="subscription-plan-form" novalidate>
                        @csrf
                        <input type="hidden" id="plan_id" name="plan_id">

                        <div class="mb-3">
                            <label class="form-label">Plan Name</label>
                            <input type="text" name="name" id="name" class="form-control" placeholder="Basic, Pro, Premium">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" id="price" class="form-control">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Discount Price</label>
                            <input type="number" step="0.01" name="discount_price" id="discount_price" class="form-control" placeholder="Optional discount price">
                        </div>

                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label">Billing Period</label>
                                <select name="billing_period" id="billing_period" class="form-select">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label">Trial Days</label>
                                <input type="number" name="trial_days" id="trial_days" class="form-control">
                            </div>
                        </div>

                        <div class="row g-3 mt-0">
                            <div class="col-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="description" class="form-control" rows="4"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Features</label>
                            <textarea name="features" id="features" class="form-control" rows="5" placeholder="One feature per line or comma separated"></textarea>
                        </div>

                        <div class="d-grid d-sm-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="submit-btn">Create Plan</button>
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
    const $form = $('#subscription-plan-form');
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
        $('#plan_id').val('');
        $('#form-title').text('Create Subscription Plan');
        $('#submit-btn').text('Create Plan');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');
        $('#billing_period').val('monthly');
        $('#status').val('active');
    }

    function setFormValues(plan) {
        $('#plan_id').val(plan.id);
        $('#name').val(plan.name || '');
        $('#price').val(plan.price || '');
        $('#discount_price').val(plan.discount_price || '');
        $('#billing_period').val(plan.billing_period || 'monthly');
        $('#trial_days').val(plan.trial_days || '');
        $('#description').val(plan.description || '');
        $('#features').val((plan.features || []).join('\n'));
        $('#status').val(plan.status || 'active');
        $('#form-title').text('Update Subscription Plan');
        $('#submit-btn').text('Update Plan');
    }

    const table = $('#subscription-plans-table').DataTable({
        processing: true,
        serverSide: true,
        autoWidth: false,
        responsive: false,
        scrollX: true,
        ajax: '{{ route('admin.subscriptions.data') }}',
        columns: [
            { data: 'id', name: 'id' },
            { data: 'name', name: 'name' },
            { data: 'price', name: 'price' },
            { data: 'discount_price', name: 'discount_price' },
            { data: 'billing_period_badge', name: 'billing_period', orderable: false, searchable: false },
            { data: 'status_badge', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    $form.on('submit', function (e) {
        e.preventDefault();

        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        const planId = $('#plan_id').val();
        const isUpdate = planId !== '';
        const formData = new FormData(this);

        if (isUpdate) {
            formData.append('_method', 'PUT');
        }

        const url = isUpdate
            ? '{{ url('admin/subscriptions') }}/' + planId
            : '{{ route('admin.subscriptions.store') }}';

        $.ajax({
            url: url,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
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

    $('#subscription-plans-table').on('click', '.js-edit-plan', function () {
        const planId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        $.ajax({
            url: '{{ url('admin/subscriptions') }}/' + planId + '/edit',
            method: 'GET',
            success: function (res) {
                setFormValues(res.data || {});
            },
            error: function (xhr) {
                showErrors(xhr);
            }
        });
    });

    $('#subscription-plans-table').on('click', '.js-delete-plan', function () {
        const planId = $(this).data('id');
        $errorBox.addClass('d-none').html('');
        $successBox.addClass('d-none').html('');

        if (!window.confirm('Are you sure you want to delete this subscription plan?')) {
            return;
        }

        $.ajax({
            url: '{{ url('admin/subscriptions') }}/' + planId,
            method: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                _method: 'DELETE'
            },
            success: function (res) {
                $successBox.removeClass('d-none').html(res.message || 'Deleted successfully.');
                table.ajax.reload(null, false);

                if ($('#plan_id').val() === String(planId)) {
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
