@extends('Backend.Layouts.Dashboard.master')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Voting Limits</h5>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.settings.voting.update') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Provincial Monthly Limit</label>
                        <input
                            type="number"
                            name="provencial_monthly_limit"
                            class="form-control"
                            min="1"
                            max="1000"
                            value="{{ old('provencial_monthly_limit', $settings['provencial_monthly_limit'] ?? '7') }}"
                            required>
                        @error('provencial_monthly_limit')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Professional Monthly Limit</label>
                        <input
                            type="number"
                            name="professional_monthly_limit"
                            class="form-control"
                            min="1"
                            max="1000"
                            value="{{ old('professional_monthly_limit', $settings['professional_monthly_limit'] ?? '12') }}"
                            required>
                        @error('professional_monthly_limit')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Voting Limits</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

