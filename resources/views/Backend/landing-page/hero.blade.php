@extends('Backend.Layouts.Dashboard.master')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Hero Section Settings</h5>
        </div>
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('admin.landing.hero.update') }}" enctype="multipart/form-data">
                @csrf
                <div class="row g-3">
                    <div class="col-md-12">
                        <label class="form-label">Banner Title</label>
                        <input type="text" name="baner_title" class="form-control" value="{{ old('baner_title', $hero->baner_title ?? '') }}" required>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label">Banner Description</label>
                        <textarea name="baner_description" class="form-control" rows="3" required>{{ old('baner_description', $hero->baner_description ?? '') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Banner Image</label>
                        <input type="file" name="baner_image" class="form-control" id="baner_image_input" accept="image/*">
                        <div class="mt-2">
                             <img id="baner_image_preview" src="{{ !empty($hero->baner_image) ? asset($hero->baner_image) : '' }}"
                                  style="max-height: 100px; {{ empty($hero->baner_image) ? 'display: none;' : '' }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Logo Image (Floating or Support Logo)</label>
                        <input type="file" name="logo_image" class="form-control" id="logo_image_input" accept="image/*">
                        <div class="mt-2">
                            <img id="logo_image_preview" src="{{ !empty($hero->logo_image) ? asset($hero->logo_image) : '' }}"
                                 style="max-height: 50px; {{ empty($hero->logo_image) ? 'display: none;' : '' }}">
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">Save Hero Settings</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function setupImagePreview(inputId, previewId) {
    const input = document.getElementById(inputId);
    const preview = document.getElementById(previewId);

    if (input && preview) {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                reader.readAsDataURL(file);
            }
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    setupImagePreview('baner_image_input', 'baner_image_preview');
    setupImagePreview('logo_image_input', 'logo_image_preview');
});
</script>
@endpush

