@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', $manufacturer->exists ? 'Edit Manufacturer' : 'Add Manufacturer')

@section('content')
    <div class="page-head">
        <h2>{{ $manufacturer->exists ? 'Edit — ' . $manufacturer->name : 'Add Manufacturer' }}</h2>
        <a class="btn gray" href="{{ route('admin.manufacturers.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:680px;">
        <form method="POST" enctype="multipart/form-data"
              action="{{ $manufacturer->exists ? route('admin.manufacturers.update', $manufacturer) : route('admin.manufacturers.store') }}">
            @csrf
            @if ($manufacturer->exists) @method('PUT') @endif

            <div class="field">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $manufacturer->name) }}" required maxlength="255" placeholder="Samsung">
                @error('name')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Logo URL</label>
                <input type="text" name="logo" value="{{ old('logo', $manufacturer->logo) }}" maxlength="1000" placeholder="https://…/logo.png">
            </div>

            <div class="field">
                <label>…or upload a logo file</label>
                <input type="file" name="logo_file" accept="image/*" style="color:var(--ka-text);">
                @error('logo_file')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Description</label>
                <textarea name="description" rows="4" maxlength="5000" placeholder="Short brand description…">{{ old('description', $manufacturer->description) }}</textarea>
            </div>

            <div class="field">
                <label style="display:flex; align-items:center; gap:8px; color:var(--ka-text);">
                    <input type="checkbox" name="status" value="1" {{ old('status', $manufacturer->status ?? true) ? 'checked' : '' }}>
                    Enabled
                </label>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Manufacturer</button>
            </div>
        </form>
    </div>
@endsection
