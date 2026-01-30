<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;


class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with(['category', 'creator'])->latest();

        if ($request->search) {
            $query->where('reference_no', 'like', "%{$request->search}%")
                  ->orWhere('description', 'like', "%{$request->search}%");
        }

        if ($request->start_date && $request->end_date) {
            $query->whereBetween('date', [$request->start_date, $request->end_date]);
        }

        if ($request->category_id) {
            $query->where('expense_category_id', $request->category_id);
        }

        $expenses = $query->paginate(10);

        return response()->json(['status' => true, 'data' => $expenses]);
    }

   public function store(Request $request)
    {
        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'reference_no' => 'nullable|string',
            'description' => 'nullable|string',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        try {
            $imagePath = null;
            if ($request->hasFile('attachment')) {
                $imagePath = $request->file('attachment')->store('expenses', 'public');
            }

            $expense = Expense::create([
                'expense_category_id' => $request->expense_category_id,
                'date' => $request->date,
                'amount' => $request->amount,
                'reference_no' => $request->reference_no,
                'description' => $request->description,
                'attachment' => $imagePath,
                'created_by' => auth()->id() ?? 1
            ]);

            return response()->json(['status' => true, 'message' => 'Expense added successfully', 'data' => $expense]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

public function update(Request $request, $id)
    {
        $expense = Expense::find($id);
        if (!$expense) return response()->json(['status' => false, 'message' => 'Not found'], 404);

        $request->validate([
            'expense_category_id' => 'required|exists:expense_categories,id',
            'date' => 'required|date',
            'amount' => 'required|numeric|min:0',
            'reference_no' => 'nullable|string',
            'description' => 'nullable|string',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
        ]);

        try {
            $data = $request->except(['attachment']);

            if ($request->hasFile('attachment')) {
                if ($expense->attachment && Storage::disk('public')->exists($expense->attachment)) {
                    Storage::disk('public')->delete($expense->attachment);
                }
                $data['attachment'] = $request->file('attachment')->store('expenses', 'public');
            }

            $expense->update($data);

            return response()->json(['status' => true, 'message' => 'Expense updated', 'data' => $expense]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $expense = Expense::find($id);
        if ($expense) {
            if ($expense->attachment && Storage::disk('public')->exists($expense->attachment)) {
                Storage::disk('public')->delete($expense->attachment);
            }
            $expense->delete();
            return response()->json(['status' => true, 'message' => 'Deleted successfully']);
        }
        return response()->json(['status' => false, 'message' => 'Not found'], 404);
    }

    public function categories()
    {
        $categories = ExpenseCategory::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $categories
        ]);
    }
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:expense_categories,name'
        ]);

        try {
            $category = ExpenseCategory::create([
                'name' => $request->name,
                'status' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Category created successfully',
                'data' => $category
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function updateCategory(Request $request, $id) {
        $category = ExpenseCategory::find($id);
        if(!$category) return response()->json(['status' => false, 'message' => 'Category not found'], 404);

        $request->validate(['name' => 'required|string|unique:expense_categories,name,'.$id]);
        $category->update(['name' => $request->name]);

        return response()->json(['status' => true, 'message' => 'Category updated']);
    }

    public function destroyCategory($id) {
        $category = ExpenseCategory::find($id);
        if(!$category) return response()->json(['status' => false, 'message' => 'Category not found'], 404);

        if($category->expenses()->count() > 0) {
            return response()->json(['status' => false, 'message' => 'Cannot delete! This category has expenses.'], 400);
        }

        $category->delete();
        return response()->json(['status' => true, 'message' => 'Category deleted']);
    }
}
