@extends('Backend.Layouts.Dashboard.master')

@section('content')
<div class="container-xxl flex-grow-1 container-p-y">

    {{-- Ecosystem Section --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Ecosystem Management</h5>
            @if (session('status'))
                <div class="alert alert-success mb-0 p-2">{{ session('status') }}</div>
            @endif
        </div>

        <div class="card-body">
            {{-- Header Form --}}
            <form method="POST" action="{{ route('admin.landing.ecosystem.store') }}" class="mb-5">
                @csrf
                <input type="hidden" name="update_detail" value="1">
                <h6>Section Header</h6>
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
                    <button type="submit" class="btn btn-primary btn-sm">Update Header</button>
                </div>
            </form>

            <hr>

            {{-- Cards Form --}}
            <form method="POST" action="{{ route('admin.landing.ecosystem.store') }}">
                @csrf
                <h6 class="mt-4">Ecosystem Cards</h6>
                <div class="feature-wrapper">
                    @php $ecosystems_list = $ecosystems ?? collect(); @endphp

                    @if($ecosystems_list->count() > 0)
                        @foreach($ecosystems_list as $index => $item)
                            <div class="row g-3 feature-item mb-3" data-id="{{ $item->id }}">
                                <input type="hidden" name="card_id[]" value="{{ $item->id }}">
                                <div class="col-md-5">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title[]" class="form-control" value="{{ $item->title }}" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Description</label>
                                    <input type="text" name="description[]" class="form-control" value="{{ $item->description }}" required>
                                </div>
                                <div class="col-md-2 d-flex align-items-end">
                                    <button type="button" class="btn btn-danger remove_card_db" data-id="{{ $item->id }}">
                                        <i class="bi bi-trash text-white"></i> Delete
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="row g-3 feature-item mb-3">
                            <input type="hidden" name="card_id[]" value="new">
                            <div class="col-md-5">
                                <label class="form-label">Title</label>
                                <input type="text" name="title[]" class="form-control" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Description</label>
                                <input type="text" name="description[]" class="form-control" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-secondary remove_card">
                                    <i class="bi bi-trash text-white"></i> Remove
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="mt-3 d-flex gap-2">
                    <button type="button" class="btn btn-success btn-sm add_card">+ Add More Card</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save All Cards</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function(){
    $(document).on('click', '.add_card', function(){
        $('.feature-wrapper').append(`
            <div class="row g-3 feature-item mb-3">
                <input type="hidden" name="card_id[]" value="new">
                <div class="col-md-5">
                    <input type="text" name="title[]" class="form-control" placeholder="Title" required>
                </div>
                <div class="col-md-5">
                    <input type="text" name="description[]" class="form-control" placeholder="Description" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="button" class="btn btn-secondary remove_card"><i class="bi bi-trash text-white"></i> Remove</button>
                </div>
            </div>
        `);
    });

    $(document).on('click', '.remove_card', function(){
        $(this).closest('.feature-item').remove();
    });

    $(document).on('click', '.remove_card_db', function(){
        let id = $(this).data('id');
        let card = $(this).closest('.feature-item');
        if(confirm('Are you sure you want to delete this card?')){
            $.ajax({
                url: "{{ url('admin/landing/ecosystem/delete') }}/" + id,
                type: 'DELETE',
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function(response){
                    card.remove();
                }
            });
        }
    });
});
</script>
@endpush
@endsection

