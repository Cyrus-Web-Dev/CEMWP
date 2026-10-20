<?php

namespace App\Modules\Warehouse\Http\Controllers;

use App\Modules\Warehouse\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class CategoryController extends WarehouseController
{
    public function __construct()
    {
        $this->middleware('permission:products.view')->only(['index']);
        $this->middleware('permission:categories.manage')->except(['index']);
    }

    public function index()
    {
        return Inertia::render('Warehouse/Categories/Index', [
            'categories' => Category::query()->withCount('products')->with('parent:id,name')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:categories,id'],
        ]);
        $category = Category::create($data + ['slug' => $this->uniqueSlug($data['name'], 'categories')]);
        ActivityLogger::log('category.created', "Kategori baru: {$category->name}", $category);

        return back()->with('success', 'Kategori ditambahkan.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'parent_id' => ['nullable', 'exists:categories,id', Rule::notIn([$category->id])],
        ]);
        $category->update($data + ['slug' => $this->uniqueSlug($data['name'], 'categories', $category->id)]);
        ActivityLogger::log('category.updated', "Kategori diperbarui: {$category->name}", $category);

        return back()->with('success', 'Kategori diperbarui.');
    }

    public function destroy(Category $category)
    {
        $category->delete(); // produk di dalamnya menjadi "tanpa kategori" (FK null on delete)
        ActivityLogger::log('category.deleted', "Kategori dihapus: {$category->name}", $category);

        return back()->with('success', 'Kategori dihapus. Produk di dalamnya menjadi tanpa kategori.');
    }
}
