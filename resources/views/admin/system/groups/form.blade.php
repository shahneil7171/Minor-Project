@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', $group->exists ? 'Edit Group' : 'Add Group')

@php $current = $group->permissions ?? []; @endphp

@section('content')
    <div class="page-head">
        <h2>{{ $group->exists ? 'Edit — ' . $group->name : 'Add User Group' }}</h2>
        <a class="btn gray" href="{{ route('admin.system.groups.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:760px;">
        <form method="POST"
              action="{{ $group->exists ? route('admin.system.groups.update', $group) : route('admin.system.groups.store') }}">
            @csrf
            @if ($group->exists) @method('PUT') @endif

            <div class="field">
                <label>Group Name</label>
                <input type="text" name="name" value="{{ old('name', $group->name) }}" required maxlength="100" placeholder="Manager">
                @error('name')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label style="display:flex; align-items:center; gap:8px; color:var(--ka-text); font-size:.9rem;">
                    <input type="checkbox" name="permissions[]" value="*" {{ in_array('*', $current, true) ? 'checked' : '' }} onchange="this.closest('form').querySelectorAll('.perm-matrix input').forEach(i => { if (i !== this) i.checked = this.checked; i.disabled = this.checked; });">
                    <strong>Full access (Super Admin)</strong>
                </label>
            </div>

            <div class="perm-matrix" style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left; padding:8px; color:var(--ka-muted); font-size:.75rem; text-transform:uppercase;">Module</th>
                            @foreach (\App\Models\UserGroup::ACTIONS as $action)
                                <th style="padding:8px; color:var(--ka-muted); font-size:.75rem; text-transform:uppercase;">{{ $action }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (\App\Models\UserGroup::MODULES as $module)
                            <tr>
                                <td style="padding:8px; font-weight:700; color:#fff; text-transform:capitalize;">{{ $module }}</td>
                                @foreach (\App\Models\UserGroup::ACTIONS as $action)
                                    @php $perm = $module . '.' . $action; @endphp
                                    <td style="text-align:center; padding:8px;">
                                        <input type="checkbox" name="permissions[]" value="{{ $perm }}"
                                               {{ in_array('*', $current, true) ? 'disabled' : '' }}
                                               {{ in_array($perm, $current, true) || in_array('*', $current, true) ? 'checked' : '' }}>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn">Save Group</button>
            </div>
        </form>
    </div>
@endsection
