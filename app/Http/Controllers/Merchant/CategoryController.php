<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Auth::user()->merchantProfile?->categories ?? collect();

        return view('merchant.categories', compact('categories'));
    }

    public function store(CategoryRequest $request)
    {
        Auth::user()->merchantProfile?->categories()->create($request->validated());

        return back()->with('status', 'Category created.');
    }

    public function update(CategoryRequest $request, Category $category)
    {
        $this->authorize('manage', $category);
        $category->update($request->validated());

        return back()->with('status', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        $this->authorize('manage', $category);
        $category->delete();

        return back()->with('status', 'Category deleted.');
    }
}
