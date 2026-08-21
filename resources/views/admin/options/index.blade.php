@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Options')

@section('content')
    <div class="page-head">
        <div>
            <h2>Options</h2>
            <p>Reusable product option templates (e.g. Color, Size, Storage, RAM).</p>
        </div>
        <a class="btn" href="{{ route('admin.options.create') }}">+ Add Option</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Option Name</th><th>Values</th><th>Sort Order</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                @forelse ($options as $option)
                    <tr>
                        <td><strong>{{ $option->name }}</strong></td>
                        <td>{{ $option->valuesLabel() }}</td>
                        <td>{{ $option->sort_order }}</td>
                        <td><span class="badge {{ $option->status ? 'active' : 'disabled' }}">{{ $option->status ? 'Enabled' : 'Disabled' }}</span></td>
                        <td>
                            <div class="row-actions">
                                <a href="{{ route('admin.options.edit', $option) }}">Edit</a>
                                <form method="POST" action="{{ route('admin.options.destroy', $option) }}" onsubmit="return confirm('Delete {{ $option->name }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="red">Delete</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No options yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if ($options->hasPages())
        <div class="pagination">{{ $options->links() }}</div>
    @endif
@endsection
