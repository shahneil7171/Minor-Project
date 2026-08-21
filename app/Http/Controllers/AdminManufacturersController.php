<?php

namespace App\Http\Controllers;

use App\Models\Manufacturer;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminManufacturersController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $manufacturers = Manufacturer::orderBy('name')->paginate(15);

        return view('admin.manufacturers.index', compact('manufacturers'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.manufacturers.form', ['manufacturer' => new Manufacturer()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);
        $data['logo'] = $this->resolveLogo($request, $data['logo'] ?? null);

        Manufacturer::create($data);

        return redirect()->route('admin.manufacturers.index')->with('success', 'Manufacturer created successfully.');
    }

    public function edit(Manufacturer $manufacturer)
    {
        $this->authorizeAdmin();

        return view('admin.manufacturers.form', compact('manufacturer'));
    }

    public function update(Request $request, Manufacturer $manufacturer)
    {
        $this->authorizeAdmin();

        $data = $this->validated($request, $manufacturer->id);
        $data['logo'] = $this->resolveLogo($request, $data['logo'] ?? null) ?? $manufacturer->logo;

        $manufacturer->update($data);

        return redirect()->route('admin.manufacturers.index')->with('success', 'Manufacturer updated successfully.');
    }

    public function destroy(Manufacturer $manufacturer)
    {
        $this->authorizeAdmin();

        $manufacturer->delete();

        return redirect()->route('admin.manufacturers.index')->with('success', 'Manufacturer deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:manufacturers,name' . ($ignoreId ? ",{$ignoreId}" : '')],
            'logo'        => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status'      => ['nullable', 'boolean'],
        ]) + ['status' => $request->boolean('status')];
    }

    private function resolveLogo(Request $request, ?string $url): ?string
    {
        if ($request->hasFile('logo_file')) {
            $request->validate(['logo_file' => ['image', 'max:2048']]);

            $dir = public_path('uploads/manufacturers');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $file = $request->file('logo_file');
            $name = time() . '-' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $name);

            return '/uploads/manufacturers/' . $name;
        }

        return $url !== null && trim($url) !== '' ? trim($url) : null;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
