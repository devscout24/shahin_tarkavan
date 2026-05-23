@extends('Backend.Layouts.Dashboard.master')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Add Trusted Review</h5>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.landing.reviews.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">User Name</label>
                        <input type="text" name="user_name" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Designation</label>
                        <input type="text" name="user_designation" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Rating</label>
                        <input type="text" name="rating" class="form-control" value="5.0" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">User Image</label>
                        <input type="file" name="user_image" class="form-control" id="review_image_input" accept="image/*">
                        <div class="mt-2 text-center">
                            <img id="review_image_preview" src="" style="max-height: 50px; border-radius: 50%; display: none; margin: 0 auto;">
                        </div>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Review Text</label>
                        <textarea name="review_text" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" class="btn btn-primary">Add Review</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Existing Reviews</h5>
        </div>
        <div class="table-responsive text-nowrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Designation</th>
                        <th>Rating</th>
                        <th>Review</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($reviews as $review)
                    <tr>
                        <td>{{ $review->user_name }}</td>
                        <td>{{ $review->user_designation }}</td>
                        <td>{{ $review->rating }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($review->review_text, 50) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('review_image_input').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        const reader = new FileReader();
        const preview = document.getElementById('review_image_preview');
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

