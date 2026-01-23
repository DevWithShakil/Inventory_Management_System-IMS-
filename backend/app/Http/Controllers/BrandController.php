<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Brand;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;


class BrandController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
       return response()->json([
            'status' => true,
            'data' => Brand::latest()->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:brands,name',
        ]);

        try {
            $brand = Brand::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name),
                'status' => true
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Brand created successfully',
                'data' => $brand
            ], 201);

        } catch (\Exception $e) {
            Log::error('Brand Create Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create brand.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $brand = Brand::find($id);
            if (!$brand) return response()->json([
                'status' => false,
                'message' => 'Brand not found'
            ], 404);

            $brand->delete();
            return response()->json([
                'status' => true,
                'message' => 'Brand deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Brand Delete Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete brand.'
            ], 500);
        }
    }
}
