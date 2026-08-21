@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'User Groups')

@section('content')
    <div class="page-head">
        <div>
            <h2>User Groups</h2>
            <p>Permission sets for staff: Super Admin, Admin, Manager or custom groups.</p>
        </div>
        <a class="btn" href="{{ route('admin.system.groups.create') }}">+ Add Group</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Group</th><th>Members</th><th>Permissions</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($groups as $group)
                    <tr>
                        <td><strong>{{ $group->name }}</strong></td>
                        <td>{{ $group->users_count }}</td>
                        <td style="max-width:420px;">
                            @if (in_array('*', $group->permissions ?? [], true))
                                <span class="badge active">Full access</span>
                            @else
                                <span style="color:var(--ka-muted); font-size:.8rem;">{{ implode(', ', $group->permissions ?? []) ?: 'No permissions' }}</span>
                            @endif
                        </td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.system.groups.edit', $group) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.system.groups.destroy', $group) }}" onsubmit="return confirm('Delete group {{ $group->name }}? Members will fall back to defaults.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No groups yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
