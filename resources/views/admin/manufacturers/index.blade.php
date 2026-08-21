@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Manufacturers')

@section('content')
    <div class="page-head">
        <div>
            <h2>Manufacturers</h2>
            <p>Brands available in your catalog (e.g. Samsung, Apple, Sony, HP, Canon).</p>
        </div>
        <a class="btn" href="{{ route('admin.manufacturers.create') }}">+ Add Manufacturer</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Logo</th><th>Description</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($manufacturers as $manufacturer)
                    <tr>
                        <td><strong>{{ $manufacturer->name }}</strong></td>
                        <td>
                            @if ($manufacturer->logo)
                                <img src="{{ $manufacturer->logo }}" alt="" style="width:42px; height:42px; object-fit:contain; border-radius:8px; background:#0b1120; border:1px solid var(--ka-border);">
                            @else
                                <span style="color:var(--ka-muted);">—</span>
                            @endif
                        </td>
                        <td style="max-width:340px;">{{ Str::limit($manufacturer->description, 90) ?: '—' }}</td>
                        <td><span class="badge {{ $manufacturer->status ? 'active' : 'disabled' }}">{{ $manufacturer->status ? 'Enabled' : 'Disabled' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.manufacturers.edit', $manufacturer) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.manufacturers.destroy', $manufacturer) }}" onsubmit="return confirm('Delete {{ $manufacturer->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No manufacturers yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($manufacturers->hasPages())
        <div class="pagination">{{ $manufacturers->links() }}</div>
    @endif
@endsection
