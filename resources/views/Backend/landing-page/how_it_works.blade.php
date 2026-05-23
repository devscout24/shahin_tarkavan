@extends('Backend.Layouts.Dashboard.master')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Add "How It Works" Step</h5>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.landing.how_it_works.update') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Image</label>
                        <input type="file" name="image" class="form-control" id="how_image_input" accept="image/*">
                        <div class="mt-2 text-center">
                            <img id="how_image_preview" src="" style="max-height: 80px; display: none; margin: 0 auto;">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Add Step</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Current Steps</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Title</th>
                        <th>Description</th>
                        <th>Image</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($howWorks as $step)
                    <tr>
                        <td>{{ $step->title }}</td>
                        <td>{{ $step->description }}</td>
                        <td><img src="{{ asset($step->image) }}" style="max-height: 40px;"></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('how_image_input').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        const preview = document.getElementById('how_image_preview');
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

