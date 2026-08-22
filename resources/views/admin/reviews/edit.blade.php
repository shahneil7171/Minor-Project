@extends('admin.layouts.panel')

@section('title', 'Edit Review')

@section('content')
    <style>
        * { box-sizing: border-box; }
        .rv-container { width: 95%; max-width: 860px; margin: 0 auto; }
        .rv-card { background: #111827; border: 1px solid #26304a; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
        .rv-card h2 { margin: 0 0 18px; font-size: 1.15rem; color: #fff; }
        .rv-meta { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; }
        .rv-meta div { background: #0b1120; border: 1px solid #26304a; border-radius: 10px; padding: 12px 14px; }
        .rv-meta span { display: block; font-size: .72rem; text-transform: uppercase; letter-spacing: .1em; color: #64748b; font-weight: 700; margin-bottom: 4px; }
        .rv-meta strong { color: #e5e7eb; font-size: .95rem; }
        .rv-meta a { color: #93c5fd; text-decoration: none; }
        .rv-meta a:hover { text-decoration: underline; }
        label { display: block; font-weight: 700; font-size: .85rem; margin: 16px 0 6px; color: #cbd5e1; }
        select, textarea {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #26304a;
            background: #0b1120;
            color: #e5e7eb;
            font-family: inherit;
            font-size: .92rem;
        }
        textarea { min-height: 130px; resize: vertical; line-height: 1.55; }
        .error-text { color: #fca5a5; font-size: .8rem; margin-top: 5px; }
        .rv-actions { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-top: 22px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 18px; border-radius: 8px; border: none; cursor: pointer; font-weight: 700; font-size: .9rem; text-decoration: none; color: #fff; font-family: inherit; }
        .btn-save { background: #2563eb; }
        .btn-back { background: #1e293b; }
    </style>

    <div class="rv-container">
        <h1 style="margin: 0 0 20px;">Edit Review #{{ $review->id }}</h1>

        @if ($errors->any())
            <div class="ka-flash error">
                <ul style="margin:0; padding-left:18px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Product + reviewer context (read-only) --}}
        <div class="rv-card">
            <h2>Review details</h2>
            <div class="rv-meta">
                <div>
                    <span>Product</span>
                    <strong>
                        @php
                            $product = app(\App\Services\ProductCatalogService::class)->find($review->product_slug);
                        @endphp
                        {{ $product['title'] ?? $review->product_slug }}
                    </strong>
                </div>
                <div>
                    <span>Customer / Reviewer</span>
                    <strong>{{ $review->user?->name ?? 'Unknown Customer' }}</strong>
                </div>
                <div>
                    <span>Submitted</span>
                    <strong>{{ $review->created_at?->format('d M Y, h:i A') ?? '—' }}</strong>
                </div>
                <div>
                    <span>Current status</span>
                    <strong>{{ ucfirst($review->status) }}</strong>
                </div>
            </div>
        </div>

        <div class="rv-card">
            <h2>Edit review</h2>
            <form method="POST" action="{{ route('admin.reviews.update', $review) }}">
                @csrf
                @method('PUT')

                <label for="rating">Rating</label>
                <select id="rating" name="rating">
                    @for ($i = 1; $i <= 5; $i++)
                        <option value="{{ $i }}" @selected((int) old('rating', $review->rating) === $i)>
                            {{ str_repeat('★', $i) }} ({{ $i }})
                        </option>
                    @endfor
                </select>
                @error('rating')<div class="error-text">{{ $message }}</div>@enderror

                <label for="comment">Review text</label>
                <textarea id="comment" name="comment" maxlength="1000">{{ old('comment', $review->comment) }}</textarea>
                @error('comment')<div class="error-text">{{ $message }}</div>@enderror

                <label for="status">Approval / status</label>
                <select id="status" name="status">
                    @php $currentStatus = old('status', $review->status); @endphp
                    <option value="pending"  @selected($currentStatus === 'pending')>Pending</option>
                    <option value="approved" @selected($currentStatus === 'approved')>Approved</option>
                    <option value="rejected" @selected($currentStatus === 'rejected')>Rejected</option>
                </select>
                @error('status')<div class="error-text">{{ $message }}</div>@enderror

                <div class="rv-actions">
                    <button type="submit" class="btn btn-save">Update review</button>
                    <a href="{{ route('admin.reviews.index') }}" class="btn btn-back">Back to reviews</a>
                </div>
            </form>
        </div>
    </div>
@endsection
