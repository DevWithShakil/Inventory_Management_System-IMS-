<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;
use App\Models\Transaction; // 🔥 Transaction মডেল ইম্পোর্ট করা হয়েছে
use Illuminate\Support\Facades\Log;

class SupplierController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'message' => 'Suppliers retrieved successfully',
            'data' => Supplier::latest()->get()
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:suppliers,phone',
            'email' => 'nullable|email|max:255|unique:suppliers,email',
            'shop_name' => 'nullable|string|max:255',
        ]);

        try {
            $supplier = Supplier::create($request->all());
            return response()->json([
                'status' => true,
                'message' => 'Supplier created successfully',
                'data' => $supplier
            ], 201);
        } catch (\Exception $e) {
            Log::error('Supplier Creation Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Something went wrong.'], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            return response()->json(['status' => true, 'data' => $supplier]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Supplier not found.'], 404);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255|unique:suppliers,email,' . $id,
                'phone' => 'required|string|max:20|unique:suppliers,phone,' . $id,
                'shop_name' => 'nullable|string|max:255',
            ]);

            $supplier->update($request->all());
            return response()->json(['status' => true, 'message' => 'Supplier updated successfully', 'data' => $supplier]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Supplier not found.'], 404);
        }
    }

    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::find($id);
            if (!$supplier) return response()->json(['status' => false, 'message' => 'Supplier not found'], 404);
            $supplier->delete();
            return response()->json(['status' => true, 'message' => 'Supplier deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Failed to delete.'], 404);
        }
    }

    // 🔥🔥 History Method Added Here
    public function history($id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            $history = Transaction::where('supplier_id', $id)
                ->orderBy('date', 'desc')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => [
                    'supplier' => $supplier,
                    'sales' => $history
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch history'
            ], 500);
        }
    }
}
