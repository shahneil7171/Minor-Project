@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Promotions')

@section('content')
    <div class="page-head">
        <div>
            <h2>Promotions</h2>
            <p>Banners shown on the storefront while their schedule and status allow.</p>
        </div>
        <a class="btn" href="{{ route('admin.promotions.create') }}">+ New Promotion</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Title</th><th>Image</th><th>Schedule</th><th>Sort</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($promotions as $promotion)
                    <tr>
                        <td><strong>{{ $promotion->title }}</strong></td>
                        <td>
                            @if ($promotion->image)
                                <img src="{{ $promotion->image }}" alt="" style="width:64px; height:34px; object-fit:cover; border-radius:6px; border:1px solid var(--ka-border);">
                            @else
                                <span style="color:var(--ka-muted);">—</span>
                            @endif
                        </td>
                        <td style="color:var(--ka-muted);">
                            {{ optional($promotion->start_date)->format('M d, Y') ?? '∞' }} → {{ optional($promotion->end_date)->format('M d, Y') ?? '∞' }}
                        </td>
                        <td>{{ $promotion->sort_order }}</td>
                        <td><span class="badge {{ $promotion->status ? 'active' : 'disabled' }}">{{ $promotion->status ? 'Active' : 'Paused' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.promotions.edit', $promotion) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.promotions.destroy', $promotion) }}" onsubmit="return confirm('Delete promotion {{ $promotion->title }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No promotions yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($promotions->hasPages())
        <div class="pagination">{{ $promotions->links() }}</div>
    @endif
@endsection
