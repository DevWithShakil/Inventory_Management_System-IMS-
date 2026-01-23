<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;


class SupplierController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
            'status' => true,
            'message' => 'Suppliers retrieved successfully',
            'data' => Supplier::latest()->get()
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
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
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while creating supplier.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Supplier retrieved successfully',
                'data' => $supplier
            ]);
        } catch (\Exception $e) {
            Log::error('Supplier Fetch Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found.'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $supplier = Supplier::findOrFail($id);
            if (!$supplier) return response()->json([
                'status' => false,
                'message' => 'Supplier not found'
            ], 404);

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255|unique:suppliers,email,' . $id,
                'phone' => 'required|string|max:20|unique:suppliers,phone,' . $id,
            ]);

            $supplier->update($request->all());
            return response()->json([
                'status' => true,
                'message' => 'Supplier updated successfully',
                'data' => $supplier
            ]);
        } catch (\Exception $e) {
            Log::error('Supplier Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found.'
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $supplier = Supplier::find($id);
            if (!$supplier) return response()->json([
                'status' => false,
                'message' => 'Supplier not found'
            ], 404);

            $supplier->delete();
            return response()->json([
                'status' => true,
                'message' => 'Supplier deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Supplier Deletion Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Supplier not found.'
            ], 404);
        }
    }
}
