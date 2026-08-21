@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', $user->exists ? 'Edit Staff User' : 'Add Staff User')

@section('content')
    <div class="page-head">
        <h2>{{ $user->exists ? 'Edit — ' . $user->name : 'Add Staff User' }}</h2>
        <a class="btn gray" href="{{ route('admin.system.users.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:640px;">
        <form method="POST"
              action="{{ $user->exists ? route('admin.system.users.update', $user) : route('admin.system.users.store') }}">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            <div class="field">
                <label>Name</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
                @error('name')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255">
                @error('email')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div style="display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <div class="field">
                    <label>Role</label>
                    <select name="account_type" {{ $user->id === auth()->id() ? 'disabled' : '' }}>
                        <option value="admin" {{ old('account_type', $user->account_type ?? 'admin') === 'admin' ? 'selected' : '' }}>Admin</option>
                        <option value="manager" {{ old('account_type', $user->account_type ?? '') === 'manager' ? 'selected' : '' }}>Manager</option>
                    </select>
                    @if ($user->id === auth()->id())
                        <input type="hidden" name="account_type" value="{{ $user->account_type }}">
                        <div class="hint">You cannot change your own role.</div>
                    @endif
                </div>
                <div class="field">
                    <label>User Group (optional)</label>
                    <select name="user_group_id">
                        <option value="">— None / default —</option>
                        @foreach ($groups as $group)
                            <option value="{{ $group->id }}" {{ old('user_group_id', $user->user_group_id) == $group->id ? 'selected' : '' }}>
                                {{ $group->name }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Controls panel permissions. Admins without a group keep full access.</div>
                </div>
            </div>

            <div class="field">
                <label>Password {{ $user->exists ? '(leave blank to keep current)' : '' }}</label>
                <input type="password" name="password" {{ $user->exists ? '' : 'required' }} minlength="8">
                @error('password')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            @if ($user->exists && $user->id !== auth()->id())
                <div class="field">
                    <label>Account Status</label>
                    <select name="status">
                        @foreach (\App\Models\User::STATUSES as $statusOption)
                            <option value="{{ $statusOption }}" {{ old('status', $user->status) === $statusOption ? 'selected' : '' }}>
                                {{ \App\Models\User::STATUS_LABELS[$statusOption] }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="form-actions">
                <button type="submit" class="btn">Save User</button>
            </div>
        </form>
    </div>
@endsection
