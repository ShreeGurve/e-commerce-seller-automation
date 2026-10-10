<?php
namespace App\Http\Controllers;

use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        return view('categories.index', ['categories' => Category::withCount('products')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->whereNull('parent_id')->whereNull('deleted_at')],
            'hsn' => 'nullable|string|max:20', 'gst_rate' => 'nullable|numeric|between:0,100',
        ]);
        $cat = Category::create($data);
        ActivityLogger::log('category.created', $cat, null, $data);
        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('categories', 'name')->ignore($category->id)->whereNull('deleted_at')],
            'hsn' => 'nullable|string|max:20', 'gst_rate' => 'nullable|numeric|between:0,100',
        ]);
        $old = $category->only(array_keys($data));
        $category->update($data);
        ActivityLogger::log('category.updated', $category, $old, $data);
        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Cannot delete: category has products.');
        }
        $category->delete();
        ActivityLogger::log('category.deleted', $category);
        return back()->with('success', 'Category deleted.');
    }
}
