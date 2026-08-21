@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', $option->exists ? 'Edit Option' : 'Add Option')

@section('content')
    <div class="page-head">
        <h2>{{ $option->exists ? 'Edit — ' . $option->name : 'Add Option' }}</h2>
        <a class="btn gray" href="{{ route('admin.options.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:640px;">
        <form method="POST"
              action="{{ $option->exists ? route('admin.options.update', $option) : route('admin.options.store') }}">
            @csrf
            @if ($option->exists) @method('PUT') @endif

            <div class="field">
                <label>Option Name</label>
                <input type="text" name="name" value="{{ old('name', $option->name) }}" required maxlength="100" placeholder="Color">
                @error('name')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Values (one per line or comma separated)</label>
                <textarea name="values" rows="5" required placeholder="Red&#10;Blue&#10;Green">{{ old('values', implode("\n", (array) $option->values)) }}</textarea>
                @error('values')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Sort Order</label>
                <input type="number" name="sort_order" value="{{ old('sort_order', $option->sort_order ?? 0) }}" min="0">
            </div>

            <div class="field">
                <label style="display:flex; align-items:center; gap:8px; color:var(--ka-text);">
                    <input type="checkbox" name="status" value="1" {{ old('status', $option->exists ? $option->status : true) ? 'checked' : '' }}>
                    Enabled
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Option</button>
            </div>
        </form>
    </div>
@endsection
