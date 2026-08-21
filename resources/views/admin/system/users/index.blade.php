@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'System Users')

@section('content')
    <div class="page-head">
        <div>
            <h2>Users</h2>
            <p>Staff accounts with admin panel access. Shoppers are managed under Sales ▸ Customers.</p>
        </div>
        <a class="btn" href="{{ route('admin.system.users.create') }}">+ Add Staff User</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Group</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($users as $user)
                    <tr>
                        <td>
                            <strong>{{ $user->name }}</strong>
                            @if ($user->id === auth()->id()) <span style="color:var(--ka-muted); font-size:.75rem;">(you)</span> @endif
                        </td>
                        <td style="color:var(--ka-muted);">{{ $user->email }}</td>
                        <td><span class="badge {{ $user->account_type === 'admin' ? 'active' : 'processing' }}">{{ ucfirst($user->account_type) }}</span></td>
                        <td>{{ $user->group?->name ?? '—' }}</td>
                        <td><span class="badge {{ $user->status === 'active' ? 'active' : 'disabled' }}">{{ ucfirst($user->status ?? 'active') }}</span></td>
                        <td>
                            <div class="row-actions">
                                @if (auth()->user()->hasPermission('system.edit'))
                                    <a href="{{ route('admin.system.users.edit', $user) }}">Edit</a>
                                @endif
                                @if (auth()->user()->hasPermission('system.delete') && $user->id !== auth()->id())
                                    <form method="POST" action="{{ route('admin.system.users.destroy', $user) }}" onsubmit="return confirm('Delete {{ $user->name }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="red">Delete</button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No staff accounts.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($users->hasPages())
        <div class="pagination">{{ $users->links() }}</div>
    @endif
@endsection
