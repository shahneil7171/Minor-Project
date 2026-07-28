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
        input, textarea {
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
        input:focus, textarea:focus { border-color: rgba(56,189,248,0.9); box-shadow: 0 0 0 4px rgba(56,189,248,0.14); }
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

        <form method="POST" action="{{ route('products.update', ['product' => $slug]) }}">
            @csrf
            <div class="form-grid">
                <div class="field">
                    <label for="title">Product name</label>
                    <input id="title" name="title" type="text" value="{{ old('title', $product['title']) }}" required>
                </div>
                <div class="field">
                    <label for="subtitle">Subtitle</label>
                    <input id="subtitle" name="subtitle" type="text" value="{{ old('subtitle', $product['subtitle']) }}" placeholder="Short product tagline">
                </div>
                <div class="field">
                    <label for="price">Price</label>
                    <input id="price" name="price" type="text" value="{{ old('price', $product['price']) }}" required placeholder="e.g. 149 or $149">
                </div>
                <div class="field">
                    <label for="image">Image URL (optional)</label>
                    <input id="image" name="image" type="url" value="{{ old('image', $product['image']) }}" placeholder="https://...">
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" required>{{ old('description', $product['description']) }}</textarea>
                </div>
                <div class="field" style="grid-column:1 / -1;">
                    <label for="details">Details (one per line)</label>
                    <textarea id="details" name="details" placeholder="Feature 1\nFeature 2\nFeature 3">{{ old('details', implode("\n", $product['details'])) }}</textarea>
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
