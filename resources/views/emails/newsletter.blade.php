# {{ \App\Models\Setting::get('store_name') }} Newsletter

{{ $newsletterBody }}

---

You are receiving this email because you subscribed to the {{ \App\Models\Setting::get('store_name') }} newsletter.
[Visit our store]({{ route('home') }})
