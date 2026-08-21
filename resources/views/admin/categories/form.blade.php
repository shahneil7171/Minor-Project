@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', $category->exists ? 'Edit Category' : 'Add Category')

@section('content')
    <div class="page-head">
        <h2>{{ $category->exists ? 'Edit — ' . $category->name : 'Add Category' }}</h2>
        <a class="btn gray" href="{{ route('admin.categories.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:640px;">
        <form method="POST"
              action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}">
            @csrf
            @if ($category->exists) @method('PUT') @endif

            <div class="field">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $category->name) }}" required maxlength="255">
                @error('name')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Parent Category (optional)</label>
                <select name="parent_id">
                    <option value="">— Top level —</option>
                    @foreach ($categories as $parent)
                        <option value="{{ $parent->id }}" {{ old('parent_id', $category->parent_id) == $parent->id ? 'selected' : '' }}>
                            {{ $parent->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $category->sort_order ?? 0) }}" min="0">
            </div>

            <div class="field">
                <label style="display:flex; align-items:center; gap:8px; color:var(--ka-text);">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', $category->exists ? $category->is_active : true) ? 'checked' : '' }}>
                    Active (visible in storefront navigation)
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Category</button>
            </div>
        </form>
    </div>
@endsection
