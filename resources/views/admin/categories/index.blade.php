@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Categories')

@section('content')
    <div class="page-head">
        <div>
            <h2>Categories</h2>
            <p>The store category tree used by the storefront navigation and filters.</p>
        </div>
        <a class="btn" href="{{ route('admin.categories.create') }}">+ Add Category</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Slug</th><th>Parent</th><th>Sort Order</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($categories as $category)
                    <tr>
                        <td><strong>{{ $category->name }}</strong></td>
                        <td style="color:var(--ka-muted);">{{ $category->slug }}</td>
                        <td>{{ $category->parent?->name ?? '— (top level)' }}</td>
                        <td>{{ $category->sort_order }}</td>
                        <td><span class="badge {{ $category->is_active ? 'active' : 'disabled' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.categories.edit', $category) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}" onsubmit="return confirm('Delete {{ $category->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="empty">No categories yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($categories->hasPages())
        <div class="pagination">{{ $categories->links() }}</div>
    @endif
@endsection
