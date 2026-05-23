@extends('Backend.Layouts.Dashboard.master')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Stats Section Settings</h5>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.landing.stats.update') }}">
                @csrf
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Active Athletes</label>
                        <input type="number" name="active_athletes" class="form-control" value="{{ old('active_athletes', $stats->active_athletes ?? 0) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Certified Coaches</label>
                        <input type="number" name="certified_coaches" class="form-control" value="{{ old('certified_coaches', $stats->certified_coaches ?? 0) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Teams</label>
                        <input type="number" name="teams" class="form-control" value="{{ old('teams', $stats->teams ?? 0) }}" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Session Booked</label>
                        <input type="number" name="session_booked" class="form-control" value="{{ old('session_booked', $stats->session_booked ?? 0) }}" required>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Stats</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

