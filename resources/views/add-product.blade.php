<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product | KDP MART</title>
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
                <h1>Add a new product</h1>
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

        <form method="POST" action="{{ route('products.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-grid">
                @php
                    $parentCategories = collect($categories ?? [])->whereNull('parent_id');
                @endphp

                <!-- ===== Product Information ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product information</span>
                </div>
                <div class="field">
                    <label for="title">Product name <span style="color:#f87171;">*</span></label>
                    <input id="title" name="title" type="text" value="{{ old('title') }}" required>
                </div>
                <div class="field">
                    <label for="sku">Model / SKU</label>
                    <input id="sku" name="sku" type="text" value="{{ old('sku') }}" placeholder="e.g. KDP-001">
                </div>
                <div class="field">
                    <label for="subtitle">Subtitle</label>
                    <input id="subtitle" name="subtitle" type="text" value="{{ old('subtitle') }}" placeholder="Short product tagline">
                </div>
                <div class="field">
                    <label for="brand">Brand / Manufacturer</label>
                    <input id="brand" name="brand" type="text" value="{{ old('brand') }}" placeholder="e.g. Apple, Samsung">
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="description">Description <span style="color:#f87171;">*</span></label>
                    <textarea id="description" name="description" required>{{ old('description') }}</textarea>
                </div>
                <!-- ===== Product Data ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product data</span>
                </div>
                <div class="field">
                    <label for="price">Price (USD) <span style="color:#f87171;">*</span></label>
                    <input id="price" name="price" type="number" step="0.01" min="0" value="{{ old('price') }}" required placeholder="e.g. 149.00">
                </div>
                <div class="field">
                    <label for="special_price">Special price (USD)</label>
                    <input id="special_price" name="special_price" type="number" step="0.01" min="0" value="{{ old('special_price') }}" placeholder="Lower sale price (optional)">
                </div>
                <div class="field">
                    <label for="quantity">Quantity <span style="color:#f87171;">*</span></label>
                    <input id="quantity" name="quantity" type="number" min="0" value="{{ old('quantity', 0) }}" required>
                </div>
                <div class="field">
                    <label for="stock_status">Stock status</label>
                    <select id="stock_status" name="stock_status">
                        <option value="in-stock" @selected(old('stock_status', 'in-stock') === 'in-stock')>In Stock</option>
                        <option value="pre-order" @selected(old('stock_status') === 'pre-order')>Pre-Order</option>
                        <option value="out-of-stock" @selected(old('stock_status') === 'out-of-stock')>Out of Stock</option>
                    </select>
                </div>
                <div class="field">
                    <label for="tax">Tax (%)</label>
                    <input id="tax" name="tax" type="number" step="0.01" min="0" max="100" value="{{ old('tax', 0) }}" placeholder="e.g. 18">
                </div>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status">
                        <option value="1" @selected(old('status', '1') === '1')>Enabled</option>
                        <option value="0" @selected(old('status') === '0')>Disabled</option>
                    </select>
                </div>

                <!-- ===== Product Links ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product links (category)</span>
                </div>
                <div class="field">
                    <label for="category">Category <span style="color:#f87171;">*</span></label>
                    {{-- Main categories come straight from the database `categories`
                         table (active top-level rows only — disabled categories are
                         never offered for NEW products) — nothing is hard-coded
                         here. The submitted value is the category's database id so
                         the real relationship is saved with the product. --}}
                    <select id="category" name="category" required>
                        <option value="">Select a category</option>
                        @foreach($parentCategories as $parent)
                            <option value="{{ $parent->id }}" @selected(old('category') == $parent->id)>
                                {{ $parent->name }}{{ $parent->is_active ? '' : ' (disabled)' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label for="subcategory">Subcategory</label>
                    {{-- Every option carries its parent id (data-parent); the script
                         below shows ONLY the selected category's subcategories
                         (e.g. Electronics -> Mobiles, Laptops, ...). The server
                         re-validates the pair before saving. --}}
                    <select id="subcategory" name="subcategory">
                        <option value="">— None (main category only) —</option>
                        @foreach($parentCategories as $parent)
                            @foreach($parent->children as $child)
                                <option value="{{ $child->id }}" data-parent="{{ $parent->id }}" @selected(old('subcategory') == $child->id)>
                                    {{ $child->name }}{{ $child->is_active ? '' : ' (disabled)' }}
                                </option>
                            @endforeach
                        @endforeach
                    </select>
                </div>

                <!-- ===== SEO ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">SEO</span>
                </div>
                <div class="field">
                    <label for="slug">SEO slug</label>
                    <input id="slug" name="slug" type="text" value="{{ old('slug') }}" placeholder="auto-generated from name (leave blank)">
                </div>
                <div class="field">
                    <label for="tags">Tags (comma separated)</label>
                    <input id="tags" name="tags" type="text" value="{{ old('tags') }}" placeholder="e.g. wireless, audio, anc">
                </div>

                <!-- ===== Images ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product images</span>
                </div>
                <div class="field">
                    <label for="image">Main image URL (optional &mdash; leave empty to use the default image)</label>
                    {{-- type="text" for the same reason as the edit form: the value may
                         legitimately be a project-local /uploads/... path, which an
                         <input type="url"> would reject. The server validates it. --}}
                    <input id="image" name="image" type="text" inputmode="url" autocomplete="off"
                           value="{{ old('image') }}"
                           placeholder="{{ \App\Services\ProductImageService::referenceHint() }}">
                    <small style="color:#94a3b8;">{{ \App\Services\ProductImageService::referenceHint() }}</small>
                </div>
                <div class="field">
                    <label for="image_file">Upload main photo (optional &mdash; an upload replaces the URL above)</label>
                    <input id="image_file" name="image_file" type="file" accept="image/*">
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="additional_images">Additional images, one per line</label>
                    <textarea id="additional_images" name="additional_images" placeholder="https://...&#10;/uploads/products/photo.webp&#10;https://...">{{ old('additional_images') }}</textarea>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="image_files">Upload additional photos (multiple &mdash; these are appended to the list above)</label>
                    <input id="image_files" name="image_files[]" type="file" accept="image/*" multiple>
                </div>

                <!-- ===== Product Detail ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product detail</span>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="details">Feature details (one per line)</label>
                    <textarea id="details" name="details" placeholder="Feature 1\nFeature 2\nFeature 3">{{ old('details') }}</textarea>
                </div>

                @php
                    // OPTIONS ARE ALWAYS GROUPED BY NAME, so re-submitting the form
                    // after a validation error never splits one option into
                    // several single-value options.
                    $initialOptions = \App\Services\ProductVariantService::groupOptions(old('options') ?? []);
                    $initialVariants = [];
                    if (old('options')) {
                        $vd = array_values(old('variants')['data'] ?? []);
                        $vp = array_values(old('variants')['price'] ?? []);
                        $vs = array_values(old('variants')['stock'] ?? []);
                        $vsku = array_values(old('variants')['sku'] ?? []);
                        foreach ($vd as $i => $json) {
                            $initialVariants[] = [
                                'values' => json_decode((string) $json, true) ?: [],
                                'price' => $vp[$i] ?? '',
                                'stock' => $vs[$i] ?? '',
                                'sku' => $vsku[$i] ?? '',
                            ];
                        }
                    }
                @endphp

                <!-- ===== Product Options & Variants ===== -->
                <div class="field" style="grid-column:1 / -1;">
                    <span style="color:#7dd3fc; font-weight:800; letter-spacing:0.12em; font-size:0.8rem; text-transform:uppercase;">Product Options &amp; Variants</span>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <p style="margin:0 0 12px; color:#94a3b8; font-size:0.9rem; line-height:1.6;">Optional. Add options such as <strong>Size</strong>, <strong>Color</strong>, <strong>Storage</strong>, <strong>RAM</strong>, <strong>Material</strong> etc. Each option holds a name and its own list of values, then click <strong>Generate variants</strong> to set a price, stock and SKU for every combination. Leave this empty for a simple product with no options.</p>

                    {{-- Marks that the options section was submitted, so the server can
                         tell "no options" apart from "options left untouched". --}}
                    <input type="hidden" name="options[__present]" value="1" />
                    <div id="optionsContainer" style="display:flex; flex-direction:column; gap:12px; margin-bottom:14px;"></div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:6px;">
                        <button type="button" class="button secondary" onclick="addOption()" style="padding:12px 16px;">+ Add option</button>
                        <button type="button" class="button" onclick="generateVariants()" style="padding:12px 16px;">Generate variants</button>
                    </div>

                    <p id="variantsStaleNote" style="display:none; margin:10px 0 0; padding:10px 14px; border-radius:8px; background:rgba(245,158,11,0.14); border:1px solid rgba(245,158,11,0.45); color:#fcd34d; font-size:0.9rem; font-weight:600;">The options changed. Click <strong>Generate variants</strong> again to refresh the combinations.</p>

                    <div id="variantsContainer" style="margin-top:16px;"></div>
                </div>
            </div>

            <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:24px;">
                <button type="submit" class="button">Save product</button>
                <a href="{{ route('products') }}" class="button secondary">Cancel</a>
            </div>
        </form>
    </div>
    <script>
        // ---- Product Options & Variants editor ----
        // ONE OPTION GROUP = one option NAME + a list of VALUES.
        // Values are edited one by one ("+ Add value") instead of sharing one
        // comma-joined field, so a single option can hold several values and the
        // generator below can build the real Cartesian product
        // (2 x 2 = 4 combinations, 2 x 2 x 2 = 8) rather than always 1.
        let state = {
            options: @json($initialOptions),
            variants: @json($initialVariants),
            generatedFor: null
        };

        function esc(v) {
            return String(v).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        function optionSignature(options) {
            return JSON.stringify((options || []).map(function (opt) {
                return [String(opt.name || '').trim(), (opt.values || []).map(function (v) { return String(v).trim(); })];
            }));
        }
        function attrsFromSelection(sel) {
            const sorted = {};
            Object.keys(sel).sort().forEach(function (k) { sorted[k] = sel[k]; });
            return sorted;
        }
        function sameSelection(a, b) {
            const x = attrsFromSelection(a), y = attrsFromSelection(b);
            const kx = Object.keys(x), ky = Object.keys(y);
            if (kx.length !== ky.length) return false;
            return kx.every(function (k) { return ky.indexOf(k) !== -1 && x[k] === y[k]; });
        }
        function currentBasePrice() {
            const el = document.getElementById('price');
            return el ? el.value : '';
        }
        function currentBaseQty() {
            const el = document.getElementById('quantity');
            return el ? el.value : '';
        }
        function groupNodes() {
            return Array.prototype.slice.call(document.querySelectorAll('#optionsContainer .option-group'));
        }
        function groupIndex(node) {
            return groupNodes().indexOf(node);
        }

        // Read every option group from the DOM. Each group contributes ONE
        // option carrying ALL of its value inputs - a value is never promoted
        // into an option of its own.
        function readOptions() {
            return groupNodes().map(function (group) {
                const nameEl = group.querySelector('.opt-name');
                const name = nameEl ? nameEl.value.trim() : '';
                const values = Array.prototype.map.call(
                    group.querySelectorAll('.opt-value-input'),
                    function (input) { return input.value.trim(); }
                ).filter(Boolean);
                return { name: name, values: values };
            });
        }

        // Keep the parallel options[values][] array the server reads in sync
        // with the value inputs the seller actually sees.
        function syncOptionPayload(options) {
            const hidden = document.querySelectorAll('#optionsContainer .opt-values');
            Array.prototype.forEach.call(hidden, function (input, i) {
                const opt = options[i];
                input.value = (opt && opt.values && opt.values.length) ? opt.values.join(', ') : '';
            });
        }

        function cartesian(options) {
            let result = [{}];
            options.forEach(function (opt) {
                const next = [];
                result.forEach(function (partial) {
                    opt.values.forEach(function (val) {
                        const copy = Object.assign({}, partial);
                        copy[opt.name] = val;
                        next.push(copy);
                    });
                });
                result = next;
            });
            return result;
        }

        function renderOptions() {
            const container = document.getElementById('optionsContainer');
            container.innerHTML = '';
            state.options.forEach(function (opt, idx) {
                const values = opt.values || [];
                let valueHtml = '';
                values.forEach(function (v) {
                    valueHtml += '<span class="opt-value" style="display:inline-flex; align-items:center; gap:6px; margin:0 8px 8px 0;">' +
                        '<input type="text" class="opt-value-input" value="' + esc(v) + '" placeholder="e.g. 512GB" style="width:160px; padding:9px 12px;">' +
                        '<button type="button" class="button secondary" onclick="removeOptionValue(this)" aria-label="Remove value" style="padding:8px 12px;">&times;</button>' +
                        '</span>';
                });

                const group = document.createElement('div');
                group.className = 'option-group';
                group.style.cssText = 'padding:16px; border-radius:14px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12);';
                group.innerHTML =
                    '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; gap:10px; flex-wrap:wrap;">' +
                        '<strong class="option-group-title" style="color:#7dd3fc;">Option ' + (idx + 1) + '</strong>' +
                        '<button type="button" class="button secondary" onclick="removeOption(this)" style="padding:8px 12px;">Remove option</button>' +
                    '</div>' +
                    '<label>Name</label>' +
                    '<input type="text" class="opt-name" name="options[name][]" placeholder="Option name (e.g. Storage)" value="' + esc(opt.name) + '">' +
                    '<input type="hidden" class="opt-values" name="options[values][]" value="' + esc(values.join(', ')) + '">' +
                    '<label style="margin-top:14px;">Values</label>' +
                    '<div class="opt-value-list" style="display:flex; flex-wrap:wrap; align-items:center; margin-top:6px;">' +
                        (valueHtml || '<span style="color:#94a3b8; font-size:0.9rem;">No values yet &mdash; use &ldquo;+ Add value&rdquo;.</span>') +
                    '</div>' +
                    '<button type="button" class="button secondary" onclick="addOptionValue(this)" style="padding:8px 12px;">+ Add value</button>';
                container.appendChild(group);
            });

            syncOptionPayload(state.options);
            markVariantsStale();
        }

        // "+ Add option" always creates a NEW option GROUP (Option 1, Option 2,
        // ...), never another value row for an existing option.
        function addOption() {
            state.options = readOptions();
            state.options.push({ name: '', values: [''] });
            renderOptions();
        }
        function removeOption(control) {
            const group = control.closest('.option-group');
            if (!group) return;
            const idx = groupIndex(group);
            state.options = readOptions();
            state.options.splice(idx, 1);
            renderOptions();
        }
        function addOptionValue(control) {
            const group = control.closest('.option-group');
            if (!group) return;
            const idx = groupIndex(group);
            state.options = readOptions();
            if (!state.options[idx]) return;
            state.options[idx].values.push('');
            renderOptions();
        }
        function removeOptionValue(control) {
            const chip = control.closest('.opt-value');
            const group = control.closest('.option-group');
            if (!chip || !group) return;
            const valueIdx = Array.prototype.slice.call(group.querySelectorAll('.opt-value')).indexOf(chip);
            const idx = groupIndex(group);
            state.options = readOptions();
            if (!state.options[idx]) return;
            state.options[idx].values.splice(valueIdx, 1);
            renderOptions();
        }

        function markVariantsStale() {
            const note = document.getElementById('variantsStaleNote');
            if (!note) return;
            const filled = readOptions().filter(function (opt) {
                return opt.name !== '' || opt.values.length > 0;
            });
            const stale = state.variants.length > 0 && optionSignature(filled) !== state.generatedFor;
            note.style.display = stale ? 'block' : 'none';
        }

        function generateVariants() {
            // Ignore rows the seller left completely blank, but validate any
            // partially filled one so nothing is ever silently dropped.
            const options = readOptions().filter(function (opt) {
                return opt.name !== '' || opt.values.length > 0;
            });

            if (options.length === 0) {
                alert('No product options have been added. Add at least one option before generating variants.');
                return;
            }

            let valid = true;
            options.forEach(function (opt) {
                if (opt.name === '') { alert('Every option needs a name (e.g. Size, Color).'); valid = false; }
                else if (opt.values.length === 0) { alert('Option "' + opt.name + '" needs at least one value.'); valid = false; }
            });
            if (!valid) return;

            const basePrice = currentBasePrice();
            const baseQty = currentBaseQty();

            // Cartesian product of EVERY option's values: 2 x 2 = 4,
            // 2 x 2 x 2 = 8. Existing combinations keep their price/stock/SKU.
            const combinations = cartesian(options);

            state.options = options;
            state.variants = combinations.map(function (sel) {
                const existing = state.variants.find(function (v) { return sameSelection(v.values || {}, sel); });
                const keep = function (value) {
                    return existing && value !== '' && value !== null && value !== undefined ? value : null;
                };
                return {
                    values: sel,
                    price: keep(existing ? existing.price : '') === null ? basePrice : existing.price,
                    stock: keep(existing ? existing.stock : '') === null ? baseQty : existing.stock,
                    sku: existing ? (existing.sku || '') : ''
                };
            });
            state.generatedFor = optionSignature(options);

            renderOptions();
            renderVariants();
        }

        function renderVariants() {
            const container = document.getElementById('variantsContainer');
            if (!state.variants.length) { container.innerHTML = ''; return; }
            let html = '<div style="padding:16px; border-radius:16px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.12);">';
            html += '<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; gap:10px; flex-wrap:wrap;">';
            html += '<strong style="color:#fff;">Variants (' + state.variants.length + ')</strong>';
            html += '<button type="button" class="button secondary" onclick="clearVariants()" style="padding:8px 12px;">Clear variants</button></div>';
            html += '<div style="overflow-x:auto;">';
            html += '<table style="width:100%; border-collapse:collapse; font-size:0.9rem; min-width:560px;">';
            html += '<thead><tr style="color:#94a3b8; text-align:left;">' +
                '<th style="padding:6px 8px;">Combination</th>' +
                '<th style="padding:6px 8px;">Price</th>' +
                '<th style="padding:6px 8px;">Stock</th>' +
                '<th style="padding:6px 8px;">SKU</th>' +
                '<th></th></tr></thead><tbody>';
            state.variants.forEach(function (v, idx) {
                const label = Object.keys(v.values).map(function (k) { return k + ': ' + v.values[k]; }).join(' | ');
                html += '<tr>';
                html += '<td style="padding:6px 8px;"><input type="hidden" name="variants[data][]" value="' + esc(JSON.stringify(v.values)) + '">' + esc(label) + '</td>';
                html += '<td style="padding:6px 8px;"><input type="number" step="0.01" min="0" name="variants[price][]" value="' + esc(v.price) + '" placeholder="Price" style="min-width:90px;"></td>';
                html += '<td style="padding:6px 8px;"><input type="number" min="0" name="variants[stock][]" value="' + esc(v.stock) + '" placeholder="Stock" style="min-width:80px;"></td>';
                html += '<td style="padding:6px 8px;"><input type="text" name="variants[sku][]" value="' + esc(v.sku) + '" placeholder="SKU" style="min-width:110px;"></td>';
                html += '<td style="padding:6px 8px;"><button type="button" class="button secondary" onclick="removeVariant(' + idx + ')" style="padding:6px 10px;">Remove</button></td>';
                html += '</tr>';
            });
            html += '</tbody></table></div></div>';
            container.innerHTML = html;
        }
        function removeVariant(idx) {
            state.variants.splice(idx, 1);
            renderVariants();
        }
        function clearVariants() {
            state.variants = [];
            renderVariants();
        }
        document.addEventListener('DOMContentLoaded', function () {
            // A product loaded from the database starts in sync, so the
            // "options changed" hint only appears once something is edited.
            state.generatedFor = optionSignature(state.options);
            renderOptions();
            renderVariants();

            // The posted payload must always mirror what is on screen.
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function () {
                    syncOptionPayload(readOptions());
                });
            }
        });

        // Dependent category -> subcategory selects (data comes from the
        // database; this only hides/shows options — the server validates
        // the actual combination before saving).
        (function () {
            const categorySelect = document.getElementById('category');
            const subcategorySelect = document.getElementById('subcategory');
            if (!categorySelect || !subcategorySelect) return;

            function applySubcategoryFilter() {
                const parentId = categorySelect.value;
                subcategorySelect.querySelectorAll('option[data-parent]').forEach(function (option) {
                    const belongs = parentId !== '' && option.getAttribute('data-parent') === parentId;
                    option.hidden = !belongs;
                    option.disabled = !belongs;
                });
                const selected = subcategorySelect.selectedOptions[0];
                if (selected && selected.disabled) {
                    subcategorySelect.value = '';
                }
            }

            categorySelect.addEventListener('change', function () {
                subcategorySelect.value = '';
                applySubcategoryFilter();
            });

            applySubcategoryFilter();
        })();
    </script>
</body>
</html>
