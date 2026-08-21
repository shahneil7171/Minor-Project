@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Newsletter')

@section('content')
    <div class="page-head">
        <div>
            <h2>Newsletter</h2>
            <p>{{ $activeCount }} active subscriber(s) · {{ $subscribers->total() }} total.</p>
        </div>
        <a class="btn" href="{{ route('admin.newsletter.compose') }}">✉ Compose &amp; Send</a>
    </div>

    <div class="card">
        <h3>Add subscriber</h3>
        <form method="POST" action="{{ route('admin.newsletter.store') }}" style="display:flex; gap:10px; flex-wrap:wrap;">
            @csrf
            <input type="email" name="email" required placeholder="subscriber@example.com"
                   style="flex:1; min-width:220px; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text);">
            <button type="submit" class="btn">Add</button>
        </form>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Email</th><th>Subscribed At</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($subscribers as $subscriber)
                    <tr>
                        <td>{{ $subscriber->email }}</td>
                        <td style="color:var(--ka-muted);">{{ optional($subscriber->subscribed_at)->format('M d, Y') ?? $subscriber->created_at->format('M d, Y') }}</td>
                        <td><span class="badge {{ $subscriber->status ? 'active' : 'disabled' }}">{{ $subscriber->status ? 'Subscribed' : 'Unsubscribed' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <form method="POST" action="{{ route('admin.newsletter.toggle', $subscriber) }}">
                                    @csrf
                                    <button type="submit">{{ $subscriber->status ? 'Unsubscribe' : 'Resubscribe' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.newsletter.destroy', $subscriber) }}" onsubmit="return confirm('Remove {{ $subscriber->email }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="empty">No subscribers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($subscribers->hasPages())
        <div class="pagination">{{ $subscribers->links() }}</div>
    @endif
@endsection
