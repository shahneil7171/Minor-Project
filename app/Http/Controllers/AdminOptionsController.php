<?php

namespace App\Http\Controllers;

use App\Models\ProductOption;
use Illuminate\Http\Request;

class AdminOptionsController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $options = ProductOption::orderBy('sort_order')->orderBy('name')->paginate(15);

        return view('admin.options.index', compact('options'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.options.form', ['option' => new ProductOption()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        ProductOption::create($this->validated($request));

        return redirect()->route('admin.options.index')->with('success', 'Option created successfully.');
    }

    public function edit(ProductOption $option)
    {
        $this->authorizeAdmin();

        return view('admin.options.form', ['option' => $option]);
    }

    public function update(Request $request, ProductOption $option)
    {
        $this->authorizeAdmin();

        $option->update($this->validated($request, $option->id));

        return redirect()->route('admin.options.index')->with('success', 'Option updated successfully.');
    }

    public function destroy(ProductOption $option)
    {
        $this->authorizeAdmin();

        $option->delete();

        return redirect()->route('admin.options.index')->with('success', 'Option deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:100', 'unique:product_options,name' . ($ignoreId ? ",{$ignoreId}" : '')],
            'values' => ['required', 'string', 'max:2000'],
        ]);

        $data['values'] = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', $data['values']))));
        $data['sort_order'] = (int) $request->input('sort_order', 0);
        $data['status'] = $request->boolean('status', true);

        return $data;
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
