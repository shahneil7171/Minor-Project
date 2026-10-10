@extends('admin.layouts.panel')

@section('title', 'Edit Customer: ' . $customer->name)

@section('content')
<style>
        /* Uses the shared KDP MART admin tokens + the panel layout's native
           <select> dark styling (Status + Account Type dropdowns stay readable
           and keyboard accessible). No nested <html>/<body>. */
        .customer-edit-page { width: 100%; max-width: 1400px; margin: 0 auto; }
        .customer-edit-page h1 { margin: 0 0 18px; font-size: 1.5rem; color: #fff; }
        .card { border-radius: 16px; padding: 22px; background: var(--ka-panel); border: 1px solid var(--ka-border); }
        .field { margin-bottom: 14px; min-width: 0; }
        .field label { display: block; margin-bottom: 6px; color: #cbd5e1; font-size: .85rem; font-weight: 700; }
        .field input, .field select { width: 100%; padding: 10px 12px; border-radius: 10px; border: 1px solid var(--ka-border); background: #0b1120; color: #f8fafc; font-size: .92rem; }
        .field input:focus, .field select:focus { border-color: #60a5fa; outline: none; box-shadow: 0 0 0 3px rgba(96,165,250,0.15); }
        .field .error { color: #fca5a5; font-size: .8rem; margin-top: 4px; }
        .btn { display: inline-flex; align-items: center; justify-content: center; padding: 10px 16px; border-radius: 10px; font-weight: 700; color: white; border: none; cursor: pointer; text-decoration: none; }
        .btn-green { background: linear-gradient(135deg, #10b981, #059669); }
        .btn-gray { background: linear-gradient(135deg, #475569, #334155); }
        .hint { color: #94a3b8; font-size: .8rem; margin-top: 4px; }
    </style>

    <div class="customer-edit-page">
        <h1>Edit customer — {{ $customer->name }}</h1>

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
                <div class="field">
                    <label>Account Type</label>
                    <select name="account_type">
                        @foreach (\App\Models\User::ACCOUNT_TYPES as $typeOption)
                            @if ($typeOption === 'admin' && ! auth()->user()->isAdmin())
                                @continue
                            @endif
                            <option value="{{ $typeOption }}" {{ old('account_type', $customer->account_type) === $typeOption ? 'selected' : '' }}>
                                {{ \App\Models\User::ACCOUNT_TYPE_LABELS[$typeOption] ?? ucfirst($typeOption) }}
                            </option>
                        @endforeach
                    </select>
                    <div class="hint">
                        Role changes take effect on the customer's next request.
                        @if (! auth()->user()->isAdmin()) Only administrators can assign the Admin role. @endif
                    </div>
                    @error('account_type')<div class="error">{{ $message }}</div>@enderror
                </div>
                <div style="display:flex;gap:12px;">
                    <button class="btn btn-green" type="submit">Save changes</button>
                    <a class="btn btn-gray" href="{{ route('admin.customers.show', $customer) }}">Back to customer</a>
                </div>
            </form>
        </div>
    </div>
@endsection
