@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Categories')

@section('content')
    <div class="page-head">
        <div>
            <h2>Categories</h2>
            <p>The store category tree used by the storefront navigation and filters. Expand a main category to see its subcategories.</p>
        </div>
        <a class="btn" href="{{ route('admin.categories.create') }}">+ Add Category</a>
    </div>

    <style>
        .cat-tree { display: flex; flex-direction: column; gap: 10px; }
        .cat-group { border: 1px solid var(--ka-border, rgba(148,163,184,.25)); border-radius: 12px; background: rgba(255,255,255,0.03); overflow: hidden; }
        .cat-group > summary { display: grid; grid-template-columns: minmax(220px, 2fr) 1fr auto auto auto; gap: 12px; align-items: center; padding: 12px 16px; cursor: pointer; list-style: none; }
        .cat-group > summary::-webkit-details-marker { display: none; }
        .cat-group > summary::before { content: '▸'; margin-right: 8px; color: var(--ka-muted); transition: transform .15s ease; display: inline-block; }
        .cat-group[open] > summary::before { transform: rotate(90deg); }
        .cat-group > summary:hover { background: rgba(255,255,255,0.05); }
        .cat-name { display: flex; align-items: center; }
        .cat-slug { color: var(--ka-muted); font-size: .85rem; }
        .cat-children { border-top: 1px solid rgba(148,163,184,.18); padding: 6px 12px 10px 42px; display: flex; flex-direction: column; gap: 4px; }
        .cat-child-row { display: grid; grid-template-columns: minmax(200px, 2fr) 1fr auto auto auto; gap: 12px; align-items: center; padding: 8px 6px; border-radius: 8px; }
        .cat-child-row:hover { background: rgba(255,255,255,0.04); }
        .cat-child-row .cat-name::before { content: '└'; margin-right: 8px; color: var(--ka-muted); }
        .cat-empty-note { color: var(--ka-muted); padding: 10px 6px; font-size: .9rem; }
        .row-actions { display: flex; gap: 10px; align-items: center; }
        .row-actions a, .row-actions button { background: none; border: none; padding: 0; font: inherit; cursor: pointer; }
    </style>

    <div class="cat-tree">
        @forelse ($categories as $category)
            <details class="cat-group" {{ $loop->first ? 'open' : '' }}>
                <summary>
                    <span class="cat-name"><strong>{{ $category->name }}</strong></span>
                    <span class="cat-slug">{{ $category->slug }}</span>
                    <span>
                        @if ($category->products_count > 0)
                            <span class="badge active" title="Products currently assigned via category_id">{{ $category->products_count }}</span>
                        @else
                            <span style="color:var(--ka-muted);">0</span>
                        @endif
                    </span>
                    <span class="badge {{ $category->is_active ? 'active' : 'disabled' }}">{{ $category->is_active ? 'Active' : 'Hidden' }}</span>
                    <span class="row-actions">
                        <a href="{{ route('admin.categories.edit', $category) }}" onclick="event.stopPropagation()">Edit</a>
                        <form method="POST" action="{{ route('admin.categories.destroy', $category) }}"
                              onclick="event.stopPropagation()"
                              onsubmit="return confirm('Delete {{ $category->name }}?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="red">Delete</button>
                        </form>
                    </span>
                </summary>
                <div class="cat-children">
                    @forelse ($category->children as $child)
                        <div class="cat-child-row">
                            <span class="cat-name">{{ $child->name }}</span>
                            <span class="cat-slug">{{ $child->slug }}</span>
                            <span>
                                @if ($child->products_count > 0)
                                    <span class="badge active" title="Products currently assigned via category_id">{{ $child->products_count }}</span>
                                @else
                                    <span style="color:var(--ka-muted);">0</span>
                                @endif
                            </span>
                            <span class="badge {{ $child->is_active ? 'active' : 'disabled' }}">{{ $child->is_active ? 'Active' : 'Hidden' }}</span>
                            <span class="row-actions">
                                <a href="{{ route('admin.categories.edit', $child) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.categories.destroy', $child) }}"
                                      onsubmit="return confirm('Delete {{ $child->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </span>
                        </div>
                    @empty
                        <div class="cat-empty-note">No subcategories yet. Use “+ Add Category” and pick {{ $category->name }} as the parent.</div>
                    @endforelse
                </div>
            </details>
        @empty
            <p class="cat-empty-note">No categories yet.</p>
        @endforelse
    </div>

    @if ($categories->hasPages())
        <div class="pagination">{{ $categories->links() }}</div>
    @endif
@endsection
