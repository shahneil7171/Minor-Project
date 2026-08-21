@extends('admin.layouts.panel')

@section('title', 'Edit Customer: ' . $customer->name)

@section('content')
<style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 720px; margin: 0 auto; }
        .header { margin-bottom: 22px; }
        .card { border-radius: 20px; padding: 22px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .flash { padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; background: rgba(16,185,129,0.14); border: 1px solid rgba(16,185,129,0.4); color: #d1fae5; font-weight: 600; }
        .flash.error { background: rgba(239,68,68,0.14); border-color: rgba(239,68,68,0.4); color: #fecaca; }
        .field { margin-bottom: 14px; }
        .field label { display: block; margin-bottom: 6px; color: #cbd5e1; font-size: 0.85rem; }
        .field input, .field select { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid rgba(255,255,255,0.18); background: rgba(255,255,255,0.06); color: #f8fafc; }
        .field .error { color: #fca5a5; font-size: 0.8rem; margin-top: 4px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; font-weight: 700; color: white; border: none; cursor: pointer; text-decoration: none; }
        .btn-green { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-gray { background: linear-gradient(135deg, #475569, #334155); }
        .hint { color: #94a3b8; font-size: 0.8rem; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header"><h1 style="margin:0;">Edit customer — {{ $customer->name }}</h1></div>

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="flash error">{{ session('error') }}</div>
        @endif

        <div class="card">
            <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
                @csrf
                @method('PUT')
                <div class="field">
                    <label>Name</label>
                    <input type="text" name="name" value="{{ old('name', $customer->name) }}" required maxlength="255">
                    @error('name')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Email</label>
                    <input type="email" name="email" value="{{ old('email', $customer->email) }}" required maxlength="255">
                    @error('email')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', $customer->phone) }}" maxlength="20">
                    @error('phone')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div class="field">
                    <label>Status</label>
                    <select name="status">
                        @foreach (\App\Models\User::STATUSES as $statusOption)
                            <option value="{{ $statusOption }}" {{ old('status', $customer->status) === $statusOption ? 'selected' : '' }}>
                                {{ \App\Models\User::STATUS_LABELS[$statusOption] }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">Inactive or blocked customers cannot sign in until re-activated.</div>
                    @error('status')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div style="display:flex;gap:12px;">
                    <button class="btn btn-green" type="submit">Save changes</button>
                    <a class="btn btn-gray" href="{{ route('admin.customers.show', $customer) }}">Back to customer</a>
                </div>
            </form>
        </div>
    </div>
@endsection
