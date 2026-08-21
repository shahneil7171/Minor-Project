@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Compose Newsletter')

@section('content')
    <div class="page-head">
        <h2>Compose Newsletter</h2>
        <a class="btn gray" href="{{ route('admin.newsletter.index') }}">← Back</a>
    </div>

    <div class="card" style="max-width:720px;">
        <p style="color:var(--ka-muted); margin-top:0;">This will be emailed to <strong style="color:#fff;">{{ $activeCount }}</strong> active subscriber(s).</p>

        <form method="POST" action="{{ route('admin.newsletter.send') }}" onsubmit="return confirm('Send this newsletter to {{ $activeCount }} subscriber(s)?');">
            @csrf

            <div class="field">
                <label>Subject</label>
                <input type="text" name="subject" required maxlength="255" placeholder="Summer Sale starts today! 🎉">
                @error('subject')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="field">
                <label>Message</label>
                <textarea name="body" rows="9" required maxlength="20000" placeholder="Write your newsletter content here…"></textarea>
                @error('body')<div class="hint" style="color:#fca5a5;">{{ $message }}</div>@enderror
            </div>

            <div class="form-actions">
                <button type="submit" class="btn green">Send Newsletter</button>
            </div>
        </form>
    </div>
@endsection
