<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: #050a1a; color: #f8fafc; }
        .page { max-width: 780px; margin: 0 auto; padding: 48px 20px 80px; }
        .back { display: inline-flex; align-items: center; gap: 8px; color: #93c5fd; text-decoration: none; font-weight: 700; margin-bottom: 26px; }
        .back:hover { text-decoration: underline; }
        h1 { margin: 0 0 8px; font-size: clamp(1.8rem, 4vw, 2.6rem); letter-spacing: -0.02em; }
        h1 em { font-style: normal; background: linear-gradient(90deg, #60a5fa, #f43f5e); -webkit-background-clip: text; background-clip: text; color: transparent; }
        .lead { margin: 0 0 30px; color: #94a3b8; line-height: 1.6; }
        .card { border-radius: 18px; background: rgba(255,255,255,0.045); border: 1px solid rgba(255,255,255,0.1); padding: 28px; }
        label { display: block; font-weight: 700; font-size: .85rem; margin: 16px 0 6px; color: #cbd5e1; }
        input, textarea {
            width: 100%;
            padding: 11px 14px;
            border-radius: 10px;
            border: 1px solid rgba(255,255,255,0.16);
            background: rgba(255,255,255,0.06);
            color: #f8fafc;
            font-family: inherit;
            font-size: .95rem;
        }
        input::placeholder, textarea::placeholder { color: #64748b; }
        input:focus, textarea:focus { outline: none; border-color: #3b82f6; }
        textarea { min-height: 150px; resize: vertical; }
        .error-text { color: #fca5a5; font-size: .8rem; margin-top: 5px; }
        .send { display: inline-flex; align-items: center; padding: 11px 24px; border-radius: 999px; border: none; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: #fff; text-decoration: none; font-weight: 700; font-size: .875rem; cursor: pointer; font-family: inherit; margin-top: 20px; }
        .send:hover { opacity: .92; }
        .flash { position: fixed; top: 76px; left: 50%; transform: translateX(-50%); z-index: 60; max-width: 92vw; padding: 12px 22px; border-radius: 12px; font-weight: 600; background: rgba(5,10,26,0.9); border: 1px solid rgba(96,165,250,0.4); color: #bfdbfe; box-shadow: 0 12px 30px rgba(0,0,0,0.4); }
        .info { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 26px; }
        .info div { background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.12); border-radius: 14px; padding: 16px; font-size: .9rem; color: #cbd5e1; }
        .info strong { display: block; color: #fff; margin-bottom: 4px; }
    </style>
</head>
<body>
    <div class="page">
        <a class="back" href="{{ route('home') }}">← Back to store</a>
        <h1>Get in <em>touch</em></h1>
        <p class="lead">Questions about an order, a product or anything else? Send us a message and we'll reply to your email.</p>

        @if (session('success'))
            <div class="flash">{{ session('success') }}</div>
        @endif

        <div class="info">
            <div><strong>📮 Support</strong>support@kdpmart.example</div>
            <div><strong>🕒 Hours</strong>Mon – Sat, 9am – 6pm IST</div>
            <div><strong>💬 Response time</strong>Usually within one business day</div>
        </div>

        <div class="card">
            <form method="POST" action="{{ route('contact.submit') }}" novalidate>
                @csrf

                <label for="name">Your name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" placeholder="Jane Doe" required maxlength="255">
                @error('name')<div class="error-text">{{ $message }}</div>@enderror

                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" required maxlength="255">
                @error('email')<div class="error-text">{{ $message }}</div>@enderror

                <label for="subject">Subject (optional)</label>
                <input id="subject" name="subject" type="text" value="{{ old('subject') }}" placeholder="Order question, product enquiry…" maxlength="255">
                @error('subject')<div class="error-text">{{ $message }}</div>@enderror

                <label for="message">Message</label>
                <textarea id="message" name="message" placeholder="How can we help? (minimum 10 characters)" required minlength="10" maxlength="2000">{{ old('message') }}</textarea>
                @error('message')<div class="error-text">{{ $message }}</div>@enderror

                <button type="submit" class="send">Send message →</button>
            </form>
        </div>
    </div>

    <script>
        setTimeout(function () {
            var flash = document.querySelector('.flash');
            if (flash) { flash.style.display = 'none'; }
        }, 5000);
    </script>
</body>
</html>
