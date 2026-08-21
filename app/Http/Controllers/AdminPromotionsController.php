<?php

namespace App\Http\Controllers;

use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminPromotionsController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $promotions = Promotion::orderBy('sort_order')->orderByDesc('created_at')->paginate(15);

        return view('admin.promotions.index', compact('promotions'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.promotions.form', ['promotion' => new Promotion()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        Promotion::create($this->validated($request));

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion created successfully.');
    }

    public function edit(Promotion $promotion)
    {
        $this->authorizeAdmin();

        return view('admin.promotions.form', compact('promotion'));
    }

    public function update(Request $request, Promotion $promotion)
    {
        $this->authorizeAdmin();

        $promotion->update($this->validated($request));

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion updated successfully.');
    }

    public function destroy(Promotion $promotion)
    {
        $this->authorizeAdmin();

        $promotion->delete();

        return redirect()->route('admin.promotions.index')->with('success', 'Promotion deleted successfully.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title'      => ['required', 'string', 'max:255'],
            'image'      => ['nullable', 'string', 'max:1000'],
            'link'       => ['nullable', 'string', 'max:1000'],
            'start_date' => ['nullable', 'date'],
            'end_date'   => ['nullable', 'date', 'after_or_equal:start_date'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['status'] = $request->boolean('status', true);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        if ($request->hasFile('image_file')) {
            $request->validate(['image_file' => ['image', 'max:4096']]);

            $dir = public_path('uploads/promotions');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $file = $request->file('image_file');
            $name = time() . '-' . Str::random(6) . '.' . $file->getClientOriginalExtension();
            $file->move($dir, $name);

            $data['image'] = '/uploads/promotions/' . $name;
        }

        return $data;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
