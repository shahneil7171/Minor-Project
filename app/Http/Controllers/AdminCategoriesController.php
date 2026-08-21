<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminCategoriesController extends Controller
{
    public function index()
    {
        $this->authorizeAdmin();

        $categories = Category::with('parent')->ordered()->paginate(20);

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.categories.form', [
            'category'   => new Category(),
            'categories' => Category::parent()->ordered()->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        Category::create($this->validated($request));

        return redirect()->route('admin.categories.index')->with('success', 'Category created successfully.');
    }

    public function edit(Category $category)
    {
        $this->authorizeAdmin();

        return view('admin.categories.form', [
            'category'   => $category,
            'categories' => Category::parent()->where('id', '!=', $category->id)->ordered()->get(),
        ]);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorizeAdmin();

        $category->update($this->validated($request, $category->id));

        return redirect()->route('admin.categories.index')->with('success', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        $this->authorizeAdmin();

        if ($category->children()->exists()) {
            return redirect()->route('admin.categories.index')
                ->with('error', 'Move or delete the sub-categories first.');
        }

        $category->delete();

        return redirect()->route('admin.categories.index')->with('success', 'Category deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'parent_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active'  => ['nullable', 'boolean'],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug !== '' ? $slug : 'category';
        $slug = $base;
        $counter = 1;

        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return [
            'name'       => $data['name'],
            'slug'       => $slug,
            'parent_id'  => $data['parent_id'] ?? null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active'  => $request->boolean('is_active', true),
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
