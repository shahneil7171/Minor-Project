@extends('layouts.app')

@section('title', 'Inventory')

@section('content')
<div class="container-fluid" style="max-width:1200px;">
    <div class="my-4">
        <h1 class="h3 mb-1"><i class="fas fa-boxes-stacked me-2" style="color:var(--primary-color);"></i>Inventory</h1>
        <p class="text-muted mb-0">
            Stock levels for the products you own. Available = Stock &minus; Reserved.
            Items with {{ $defaultThreshold }} or fewer available units are flagged as low stock.
        </p>
    </div>

    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @error('quantity')<div class="alert alert-danger">{{ $message }}</div>@enderror
    @error('reason')<div class="alert alert-danger">{{ $message }}</div>@enderror

    {{-- Filters: All / In Stock / Low Stock / Out of Stock + search --}}
    <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
        @php
            $chips = [
                'all'          => 'All',
                'in_stock'     => $statusLabels['in_stock'],
                'low_stock'    => $statusLabels['low_stock'],
                'out_of_stock' => $statusLabels['out_of_stock'],
            ];
        @endphp
        @foreach ($chips as $key => $label)
            <a href="{{ route('seller.inventory.index', ['status' => $key, 'q' => $search]) }}"
               class="btn btn-sm {{ $status === $key ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }} <span class="badge bg-light text-dark ms-1">{{ $counts[$key] ?? 0 }}</span>
            </a>
        @endforeach

        <form method="GET" action="{{ route('seller.inventory.index') }}" class="d-flex gap-2 ms-auto">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" value="{{ $search }}" class="form-control form-control-sm" style="min-width:220px" placeholder="Search product, SKU or variant">
            <button class="btn btn-sm btn-outline-primary">Search</button>
        </form>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Variant</th>
                            <th class="text-end">Stock</th>
                            <th class="text-end">Reserved</th>
                            <th class="text-end">Available</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $badge = $row['status'] === 'out_of_stock'
                                    ? 'bg-danger'
                                    : ($row['status'] === 'low_stock' ? 'bg-warning text-dark' : 'bg-success');
                            @endphp
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        @if ($row['product']->image)
                                            <img src="{{ $row['product']->image }}" alt="{{ $row['title'] }}" style="width:38px; height:38px; object-fit:contain; border-radius:8px; border:1px solid rgba(0,0,0,0.08); background:#fff; padding:2px;">
                                        @endif
                                        <div>
                                            <div class="fw-bold">{{ $row['title'] }}</div>
                                            <div class="small text-muted">{{ $row['product']->category?->name ?? $row['product']->category_name ?? '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="small">{{ $row['sku'] ?? '—' }}</td>
                                <td class="small">{{ $row['variant'] ?? '—' }}</td>
                                <td class="text-end fw-bold">{{ $row['stock'] }}</td>
                                <td class="text-end text-muted">{{ $row['reserved'] }}</td>
                                <td class="text-end fw-bold">{{ $row['available'] }}</td>
                                <td><span class="badge {{ $badge }}">{{ $row['status_label'] }}</span></td>
                                <td class="small text-muted">{{ $row['updated_at']?->format('d M Y H:i') ?? '—' }}</td>
                                <td class="text-end">
                                    @include('seller.partials.inventory-adjust', ['row' => $row])
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">No inventory items found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-5">
        <a href="{{ route('seller.inventory.history') }}" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-history me-1"></i>Inventory history
        </a>
        @if ($inventory->hasPages())
            <nav aria-label="Inventory pages">
                <ul class="pagination mb-0">
                    @if ($inventory->onFirstPage())
                        <li class="page-item disabled"><span class="page-link">Previous</span></li>
                    @else
                        <li class="page-item"><a class="page-link" href="{{ $inventory->previousPageUrl() }}">Previous</a></li>
                    @endif
                    <li class="page-item disabled"><span class="page-link">Page {{ $inventory->currentPage() }} of {{ $inventory->lastPage() }}</span></li>
                    @if ($inventory->hasMorePages())
                        <li class="page-item"><a class="page-link" href="{{ $inventory->nextPageUrl() }}">Next</a></li>
                    @else
                        <li class="page-item disabled"><span class="page-link">Next</span></li>
                    @endif
                </ul>
            </nav>
        @endif
    </div>
</div>
@endsection
