{{--
    Inline "Adjust stock" form for one inventory row (seller).

    Only the +/- delta and a mandatory reason are posted: the product and the
    variant are verified server-side against the seller's ownership, never
    taken from this form.
--}}
<details class="d-inline-block text-end">
    <summary class="btn btn-sm btn-outline-primary" style="display:inline-block; cursor:pointer; list-style:none;">Adjust</summary>
    <form method="POST" action="{{ route('seller.inventory.adjust', ['product' => $row['product_id']]) }}"
          class="mt-2 p-2 border rounded text-start"
          style="min-width:250px; background:#f8f9fa;">
        @csrf
        <input type="hidden" name="variant_id" value="{{ $row['variant_id'] }}">
        <label class="small text-muted d-block mb-1">
            Change for {{ $row['variant'] ? $row['variant'] : ($row['sku'] ?? 'this product') }}
        </label>
        <div class="d-flex gap-2 mb-2">
            <input type="number" step="1" name="quantity" class="form-control form-control-sm" placeholder="e.g. +5" required>
            <input type="text" name="reason" class="form-control form-control-sm" placeholder="Reason" maxlength="255" required>
        </div>
        <button class="btn btn-sm btn-primary w-100">Save adjustment</button>
    </form>
</details>
