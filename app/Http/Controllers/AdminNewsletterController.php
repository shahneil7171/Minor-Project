<?php

namespace App\Http\Controllers;

use App\Mail\NewsletterMail;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class AdminNewsletterController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $subscribers = NewsletterSubscriber::orderByDesc('created_at')->paginate(20);
        $activeCount = NewsletterSubscriber::active()->count();

        return view('admin.newsletter.index', compact('subscribers', 'activeCount'));
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:newsletter_subscribers,email'],
        ]);

        NewsletterSubscriber::create([
            'email' => strtolower($data['email']),
            'status' => true,
            'subscribed_at' => now(),
        ]);

        return redirect()->route('admin.newsletter.index')->with('success', 'Subscriber added successfully.');
    }

    public function toggle(NewsletterSubscriber $subscriber)
    {
        $this->authorizeAdmin();

        $subscriber->update(['status' => ! $subscriber->status]);

        return redirect()->route('admin.newsletter.index')
            ->with('success', 'Subscriber ' . ($subscriber->status ? 'resubscribed' : 'unsubscribed') . '.');
    }

    public function destroy(NewsletterSubscriber $subscriber)
    {
        $this->authorizeAdmin();

        $subscriber->delete();

        return redirect()->route('admin.newsletter.index')->with('success', 'Subscriber removed successfully.');
    }

    public function compose()
    {
        $this->authorizeAdmin();

        $activeCount = NewsletterSubscriber::active()->count();

        return view('admin.newsletter.compose', compact('activeCount'));
    }

    public function send(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:255'],
            'body'    => ['required', 'string', 'max:20000'],
        ]);

        $subscribers = NewsletterSubscriber::active()->get();

        foreach ($subscribers as $subscriber) {
            Mail::to($subscriber->email)->send(
                new NewsletterMail($data['subject'], $data['body'])
            );
        }

        return redirect()->route('admin.newsletter.index')
            ->with('success', 'Newsletter sent to ' . $subscribers->count() . ' subscriber(s).');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
