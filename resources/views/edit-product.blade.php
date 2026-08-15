<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #050816 0%, #08152d 45%, #0f2045 100%);
            color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 900px;
            background: rgba(8, 15, 31, 0.96);
            border: 1px solid rgba(56, 189, 248, 0.2);
            border-radius: 30px;
            padding: 32px;
            box-shadow: 0 32px 100px rgba(0, 0, 0, 0.45);
        }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 30px; }
        .header h1 { margin: 0; font-size: 2rem; }
        .header a { color: #7dd3fc; text-decoration: none; font-weight: 700; }
        .form-grid { display: grid; gap: 18px; }
        .field { display: grid; gap: 8px; }
        label { color: #cbd5e1; font-size: 0.95rem; }
        input, textarea, select {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.18);
            background: rgba(15, 23, 42, 0.98);
            color: #f8fafc;
            border-radius: 14px;
            padding: 14px 16px;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        select option { background: #0f172a; color: #f8fafc; }
        input:focus, textarea:focus, select:focus { border-color: rgba(56,189,248,0.9); box-shadow: 0 0 0 4px rgba(56,189,248,0.14); }
        textarea { min-height: 140px; resize: vertical; }
        .button { display: inline-flex; align-items: center; justify-content: center; padding: 14px 18px; border: none; border-radius: 14px; background: linear-gradient(135deg, #10b981, #059669); color: white; font-weight: 700; cursor: pointer; }
        .secondary { border: 1px solid rgba(56,189,248,0.6); background: transparent; color: #7dd3fc; }
        .error-box { background: rgba(248,113,113,0.12); border: 1px solid rgba(248,113,113,0.4); color: #fecaca; padding: 16px; border-radius: 14px; margin-bottom: 18px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <p style="margin:0; color:#7dd3fc; letter-spacing:0.16em; font-size:0.8rem; text-transform:uppercase;">Seller panel</p>
                <h1>Edit product</h1>
            </div>
            <a href="{{ route('products') }}">Back to products</a>
        </div>

        @if ($errors->any())
            <div class="error-box">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('products.update', ['product' => $slug]) }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                @php
                    $parentCategories = collect($categories ?? [])->whereNull('parent_id');
                    $currentCategory = old('category', $product['category'] ?? '');
                    $currentTags = is_array($product['tags'] ?? null) ? implode(',', $product['tags']) : ($product['tags'] ?? '');
                    $currentGallery = is_array($product['images'] ?? null)
                        ? implode("\n", array_slice($product['images'], 1))
                        : '';
                    $currentPrice = (float) filter_var($product['price'] ?? '0', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
                    $currentSpecial = isset($product['special_price']) && $product['special_price'] !== ''
                        ? (float) filter_var($product['special_price'], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION) : '';
                @endphp

                <!-- ===== Product Information ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product information</span>
                </div>
                <div class="field">
                    <label for="title">Product name <span style="color:#f87171;">*</span></label>
                    <input id="title" name="title" type="text" value="{{ old('title', $product['title']) }}" required>
                </div>
                <div class="field">
                    <label for="sku">Model / SKU</label>
                    <input id="sku" name="sku" type="text" value="{{ old('sku', $product['sku'] ?? '') }}" placeholder="e.g. KDP-001">
                </div>
                <div class="field">
                    <label for="subtitle">Subtitle</label>
                    <input id="subtitle" name="subtitle" type="text" value="{{ old('subtitle', $product['subtitle'] ?? '') }}" placeholder="Short product tagline">
                </div>
                <div class="field">
                    <label for="brand">Brand / Manufacturer</label>
                    <input id="brand" name="brand" type="text" value="{{ old('brand', $product['brand'] ?? '') }}" placeholder="e.g. Apple, Samsung">
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="description">Description <span style="color:#f87171;">*</span></label>
                    <textarea id="description" name="description" required>{{ old('description', $product['description']) }}</textarea>
                </div>
                <!-- ===== Product Data ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product data</span>
                </div>
                <div class="field">
                    <label for="price">Price (USD) <span style="color:#f87171;">*</span></label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price', $currentPrice) }}" required placeholder="e.g. 149.00">
                </div>
                <div class="field">
                    <label for="special_price">Special price (USD)</label>
                    <input id="special_price" name="special_price" type="number" step="0.01" min="0" value="{{ old('special_price', $currentSpecial) }}" placeholder="Lower sale price (optional)">
                </div>
                <div class="field">
                    <label for="quantity">Quantity <span style="color:#f87171;">*</span></label>
                    <input id="quantity" name="quantity" type="number" min="0" value="{{ old('quantity', $product['quantity'] ?? 0) }}" required>
                </div>
                <div class="field">
                    <label for="stock_status">Stock status</label>
                    <select id="stock_status" name="stock_status">
                        @php $stock = old('stock_status', $product['stock_status'] ?? 'in-stock'); @endphp
                        <option value="in-stock" @selected($stock === 'in-stock')>In Stock</option>
                        <option value="pre-order" @selected($stock === 'pre-order')>Pre-Order</option>
                        <option value="out-of-stock" @selected($stock === 'out-of-stock')>Out of Stock</option>
                    </select>
                </div>
                <div class="field">
                    <label for="tax">Tax (%)</label>
                    <input id="tax" name="tax" type="number" step="0.01" min="0" max="100" value="{{ old('tax', $product['tax'] ?? 0) }}" placeholder="e.g. 18">
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        @php $status = old('status', $product['status'] ?? 1); @endphp
                        <option value="1" @selected((int) $status === 1)>Enabled</option>
                        <option value="0" @selected((int) $status === 0)>Disabled</option>
                    </select>
                </div>

                <!-- ===== Product Links ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product links (category)</span>
                </div>
                <div class="field">
                    <label for="category">Category <span style="color:#f87171;">*</span></label>
                    <select id="category" name="category" required>
                        <option value="">Select a category</option>
                        @foreach($parentCategories as $parent)
                            <option value="{{ $parent->name }}" @selected($currentCategory === $parent->name)>{{ $parent->name }}</option>
                            @foreach($parent->children as $child)
                                <option value="{{ $child->name }}" @selected($currentCategory === $child->name)>&nbsp;&nbsp;— {{ $child->name }}</option>
                            @endforeach
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="subcategory">Subcategory</label>
                    <input id="subcategory" name="subcategory" type="text" value="{{ old('subcategory', $product['subcategory'] ?? '') }}" placeholder="e.g. Mobiles">
                </div>

                <!-- ===== SEO ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">SEO</span>
                </div>
                <div class="field">
                    <label for="slug">SEO slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug', $product['slug'] ?? '') }}" placeholder="leave blank to keep current">
                </div>
                <div class="field">
                    <label for="tags">Tags (comma separated)</label>
                    <input id="tags" name="tags" type="text" value="{{ old('tags', $currentTags) }}" placeholder="e.g. wireless, audio, anc">
                </div>

                <!-- ===== Images ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product images</span>
                </div>
                <div class="field">
                    <label for="image">Main image URL (optional)</label>
                    <input id="image" name="image" type="url" value="{{ old('image', $product['image'] ?? '') }}" placeholder="https://...">
                </div>
                <div class="field">
                    <label for="image_file">Upload new main photo</label>
                    <input id="image_file" name="image_file" type="file" accept="image/*">
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="additional_images">Additional image URLs (one per line)</label>
                    <textarea id="additional_images" name="additional_images" placeholder="https://...\nhttps://...">{{ old('additional_images', $currentGallery) }}</textarea>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="image_files">Upload additional photos (multiple)</label>
                    <input id="image_files" name="image_files[]" type="file" accept="image/*" multiple>
                </div>

                <!-- ===== Product Detail ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product detail</span>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="details">Feature details (one per line)</label>
                    <textarea id="details" name="details" placeholder="Feature 1\nFeature 2\nFeature 3">{{ old('details', implode("\n", $product['details'] ?? [])) }}</textarea>
                </div>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:24px;">
                <button type="submit" class="button">Save changes</button>
                <a href="{{ route('products') }}" class="button secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>
