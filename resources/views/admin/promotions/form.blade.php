@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', $promotion->exists ? 'Edit Promotion' : 'New Promotion')

@section('content')
    <div class="page-head">
        <h2>{{ $promotion->exists ? 'Edit — ' . $promotion->title : 'New Promotion' }}</h2>
        <a class="btn gray" href="{{ route('admin.promotions.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:680px;">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $promotion->exists ? route('admin.promotions.update', $promotion) : route('admin.promotions.store') }}">
            @csrf
            @if ($promotion->exists) @method('PUT') @endif

            <div class="field">
                <label>Title</label>
                <input type="text" name="title" value="{{ old('title', $promotion->title) }}" required maxlength="255" placeholder="Summer Sale — 30% Off">
                @error('title')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Image URL</label>
                <input type="text" name="image" value="{{ old('image', $promotion->image) }}" maxlength="1000" placeholder="https://…/banner.jpg">
            </div>

            <div class="field">
                <label>…or upload a banner image</label>
                <input type="file" name="image_file" accept="image/*" style="color:var(--ka-text);">
                @error('image_file')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Link (optional)</label>
                <input type="text" name="link" value="{{ old('link', $promotion->link) }}" maxlength="1000" placeholder="/products?category=offers">
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div class="field">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="{{ old('start_date', optional($promotion->start_date)->format('Y-m-d')) }}">
                </div>
                <div class="field">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="{{ old('end_date', optional($promotion->end_date)->format('Y-m-d')) }}">
                    @error('end_date')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Sort Order</label>
                    <input type="number" name="sort_order" value="{{ old('sort_order', $promotion->sort_order ?? 0) }}" min="0">
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status_select" disabled>
                        <option>{{ old('status', $promotion->exists ? $promotion->status : true) ? 'Active' : 'Paused' }}</option>
                    </select>
                    <label style="display:flex; align-items:center; gap:8px; margin-top:8px; color:var(--ka-text);">
                        <input type="checkbox" name="status" value="1" {{ old('status', $promotion->exists ? $promotion->status : true) ? 'checked' : '' }}>
                        Active (show on storefront)
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Promotion</button>
            </div>
        </form>
    </div>
@endsection
