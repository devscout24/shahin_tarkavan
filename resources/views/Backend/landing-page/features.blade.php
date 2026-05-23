@extends('Backend.Layouts.Dashboard.master')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    {{-- Header Section --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Features Section Header</h5>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.landing.features.store') }}">
                @csrf
                <input type="hidden" name="update_detail" value="1">
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Main Title</label>
                        <input type="text" name="title" class="form-control" value="{{ $detail->title ?? '' }}" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Sub Description</label>
                        <textarea name="description" class="form-control" rows="2" required>{{ $detail->description ?? '' }}</textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Update Header</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Add Feature Card --}}
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Add Feature Card</h5>
        </div>
        <div class="card-body">
            <form method="POST" action="{{ route('admin.landing.features.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Feature Image/Icon</label>
                        <input type="file" name="icon" class="form-control" id="feature_icon_input" accept="image/*" required>
                        <div class="mt-2 text-center">
                            <img id="feature_icon_preview" src="" style="max-height: 50px; display: none; margin: 0 auto;">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Add Card</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Current Feature Cards --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Existing Feature Cards</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Title</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($features as $item)
                    <tr>
                        <td><img src="{{ asset($item->icon) }}" style="max-height: 30px;"></td>
                        <td>{{ $item->title }}</td>
                        <td>{{ $item->description }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('feature_icon_input').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        const preview = document.getElementById('feature_icon_preview');
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.style.display = 'block';
        }
        reader.readAsDataURL(file);
    }
});
</script>
@endpush
@endsection

