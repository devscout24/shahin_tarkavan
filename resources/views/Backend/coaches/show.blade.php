@extends('Backend.Layouts.Dashboard.master')

@push('styles')
<style>
    .coach-hero {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.95));
        box-shadow: 0 10px 30px rgba(2, 6, 23, 0.08);
    }

    .coach-avatar {
        width: 100%;
        max-width: 260px;
        aspect-ratio: 4 / 5;
        object-fit: cover;
        border-radius: 14px;
        border: 1px solid rgba(15, 23, 42, 0.08);
    }

    .info-kv {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 8px 14px;
    }

    .info-kv .key {
        color: #475569;
        font-weight: 600;
    }

    .info-kv .val {
        color: #0f172a;
    }

    .section-card {
        border: 1px solid rgba(15, 23, 42, 0.08);
        border-radius: 14px;
    }

    .gallery-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
        gap: 14px;
    }

    .gallery-item {
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid rgba(15, 23, 42, 0.1);
        background: #fff;
    }

    .gallery-item img {
        width: 100%;
        height: 150px;
        object-fit: cover;
        display: block;
    }

    .media-file-card {
        padding: 12px;
        min-height: 150px;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .media-file-type {
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.4px;
        color: #334155;
    }

    .media-file-name {
        font-size: 12px;
        color: #64748b;
        word-break: break-all;
    }

    .media-actions {
        display: flex;
        gap: 8px;
    }

    @media (max-width: 768px) {
        .info-kv {
            grid-template-columns: 1fr;
            gap: 4px;
        }
    }
</style>
@endpush

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">
    <div class="mb-4 d-flex justify-content-between align-items-center">
        <h4 class="mb-0 fw-bold">Coach Profile Details</h4>
        <a href="{{ route('admin.coaches.index') }}" class="btn btn-outline-secondary">Back</a>
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @php
        $profileImageUrl = null;
        if (!empty($coach->coach_profile_pic)) {
            $profileImageUrl = \Illuminate\Support\Str::startsWith($coach->coach_profile_pic, ['http://', 'https://'])
                ? $coach->coach_profile_pic
                : asset(ltrim($coach->coach_profile_pic, '/'));
        }
    @endphp

    <div class="mb-4 coach-hero card">
        <div class="p-4 card-body">
            <div class="row g-4 align-items-start">
                <div class="text-center col-md-4 col-lg-3 text-md-start">
                    @if ($profileImageUrl)
                        <img src="{{ $profileImageUrl }}" alt="Coach Photo" class="coach-avatar">
                    @else
                        <div class="p-4 text-center border rounded text-muted">No profile image</div>
                    @endif
                </div>

                <div class="col-md-8 col-lg-9">
                    <div class="flex-wrap gap-2 mb-3 d-flex align-items-center">
                        <h3 class="mb-0">{{ trim(($coach->name ?? '').' '.($coach->last_name ?? '')) }}</h3>
                        @if ($coach->status === 'approve')
                            <span class="badge bg-success">Approved</span>
                        @elseif ($coach->status === 'pending')
                            <span class="badge bg-warning text-dark">Pending</span>
                        @else
                            <span class="badge bg-danger">{{ ucfirst($coach->status) }}</span>
                        @endif
                    </div>

                    <div class="info-kv">
                        <div class="key">Email</div>
                        <div class="val">{{ $coach->email ?: 'N/A' }}</div>
                        <div class="key">Sports</div>
                        <div class="val">{{ $coach->sports_display ?: 'N/A' }}</div>
                        <div class="key">Nationality</div>
                        <div class="val">{{ $coach->nationality ?: 'N/A' }}</div>
                        <div class="key">Current Role</div>
                        <div class="val">{{ $coach->current_role_display ?: 'N/A' }}</div>
                        <div class="key">Experience</div>
                        <div class="val">{{ $coach->years_of_experience ?: 'N/A' }}</div>
                        <div class="key">Highest Education</div>
                        <div class="val">{{ $coach->highest_education ?: 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mb-4 section-card card">
        <div class="card-header"><h6 class="mb-0">Bio</h6></div>
        <div class="card-body">
            <p class="mb-0">{{ $coach->coaching_philosophy ?: 'N/A' }}</p>
        </div>
    </div>

    <div class="mb-4 section-card card">
        <div class="card-header"><h6 class="mb-0">Coaching Titles</h6></div>
        <div class="card-body">
            @if ($coach->coachingTitles->count())
                @foreach ($coach->coachingTitles as $title)
                    <span class="mb-1 badge bg-label-primary me-1">{{ $title->title }}</span>
                @endforeach
            @else
                <p class="mb-0 text-muted">No coaching titles found.</p>
            @endif
        </div>
    </div>

    <div class="mb-4 section-card card">
        <div class="card-header"><h6 class="mb-0">Coach Media</h6></div>
        <div class="card-body">
            <div class="gallery-grid">
                @forelse ($coach->media as $media)

                    @php
                        $mediaUrl = null;
                        $extension = null;
                        $isImage = false;
                        $isPdf = false;

                        if (!empty($media->image)) {
                            $mediaUrl = \Illuminate\Support\Str::startsWith($media->image, ['http://', 'https://'])
                                ? $media->image
                                : asset(ltrim($media->image, '/'));

                            $path = parse_url($mediaUrl, PHP_URL_PATH);
                            $extension = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
                            $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'], true);
                            $isPdf = $extension === 'pdf';
                        }
                    @endphp

                    @if ($mediaUrl)
                    <div class="gallery-item">
                        @if ($isImage)
                        <img src="{{ $mediaUrl }}" alt="Coach Media {{ $loop->iteration }}" loading="lazy">
                        @elseif ($isPdf)
                        <div class="media-file-card">
                            <div>
                                <div class="media-file-type">PDF DOCUMENT</div>
                                <div class="mt-2 media-file-name">{{ basename(parse_url($mediaUrl, PHP_URL_PATH)) }}</div>
                            </div>
                            <div class="mt-3 media-actions">
                                <a href="{{ $mediaUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">View</a>
                                <a href="{{ $mediaUrl }}" download class="btn btn-sm btn-outline-secondary">Download</a>
                            </div>
                        </div>
                        @else
                        <div class="media-file-card">
                            <div>
                                <div class="media-file-type">FILE</div>
                                <div class="mt-2 media-file-name">{{ basename(parse_url($mediaUrl, PHP_URL_PATH)) }}</div>
                            </div>
                            <div class="mt-3 media-actions">
                                <a href="{{ $mediaUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">Open</a>
                                <a href="{{ $mediaUrl }}" download class="btn btn-sm btn-outline-secondary">Download</a>
                            </div>
                        </div>
                        @endif
                    </div>
                    @endif
                @empty
                    <div>
                        <p class="mb-0 text-muted">No media found.</p>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($coach->status !== 'approve')
        <form action="{{ route('admin.coaches.approve', $coach) }}" method="POST" ">
            @csrf
            <button type="submit" class="btn btn-success">Approve Coach & Send Email</button>
        </form>
    @else
        <button type="button" class="btn btn-success" disabled>Already Approved</button>
    @endif
</div>
@endsection

