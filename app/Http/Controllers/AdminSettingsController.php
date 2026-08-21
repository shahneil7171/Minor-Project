<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminSettingsController extends Controller
{
    /**
     * Store settings (System > Settings).
     *
     * Settings currently drive the admin panel branding; the storefront
     * keeps its existing markup so no customer-facing behaviour changes.
     */
    public function index()
    {
        $this->authorizeAdmin();

        $values = collect(array_keys(Setting::DEFAULTS))
            ->mapWithKeys(fn ($key) => [$key => old($key, Setting::get($key))]);

        return view('admin.system.settings', [
            'values'   => $values,
            'currency' => Setting::get('currency'),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin();

        abort_unless(auth()->user()->hasPermission('system.edit'), 403);

        $data = $request->validate([
            'store_name'  => ['required', 'string', 'max:100'],
            'store_email' => ['required', 'email', 'max:255'],
            'store_phone' => ['required', 'string', 'max:30'],
            'currency'    => ['required', 'string', 'max:10'],
            'store_logo'  => ['nullable', 'string', 'max:1000'],
        ]);

        if ($request->hasFile('logo_file')) {
            $request->validate(['logo_file' => ['image', 'max:2048']]);

            $dir = public_path('uploads/settings');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $file = $request->file('logo_file');
            $name = time() . '-' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $name);

            $data['store_logo'] = '/uploads/settings/' . $name;
        }

        foreach ($data as $key => $value) {
            Setting::put($key, $value !== null ? (string) $value : null);
        }

        return redirect()->route('admin.settings.index')->with('success', 'Settings saved successfully.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
