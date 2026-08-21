@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Backup & Restore')

@section('content')
    <div class="page-head">
        <div>
            <h2>Backup</h2>
            <p>JSON snapshots of the database (catalog, orders, customers, settings…). Stored in <code>storage/app/backups</code>.</p>
        </div>
        <form method="POST" action="{{ route('admin.backup.create') }}" onsubmit="return confirm('Create a new backup now?');">
            @csrf
            <button type="submit" class="btn green">⬇ Create Backup</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>File</th><th>Size</th><th>Created</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($files as $file)
                    <tr>
                        <td><strong>{{ $file['name'] }}</strong></td>
                        <td>{{ number_format($file['size'] / 1024, 1) }} KB</td>
                        <td style="color:var(--ka-muted);">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['modified'])->format('M d, Y h:i A') }}</td>
                        <td>
                            <div class="row-actions">
                                <a class="primary" href="{{ route('admin.backup.download', $file['name']) }}">Download</a>
                                <form method="POST" action="{{ route('admin.backup.restore', $file['name']) }}"
                                      onsubmit="return confirm('RESTORE {{ $file['name'] }}? Current data in backed-up tables will be replaced!');">
                                    @csrf
                                    <button type="submit">Restore</button>
                                </form>
                                <form method="POST" action="{{ route('admin.backup.destroy', $file['name']) }}" onsubmit="return confirm('Delete backup file?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No backups yet. Create your first one.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
