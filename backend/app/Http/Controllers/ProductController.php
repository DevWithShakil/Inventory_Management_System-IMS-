<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        try {
            $products = Product::with(['category', 'brand', 'unit'])->latest()->get();

            return response()->json([
                'status' => true,
                'message' => 'Products retrieved successfully',
                'data' => $products
            ], 200);

        } catch (\Exception $e) {
            Log::error('Product Fetch Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Something went wrong while fetching products.'
            ], 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'unit_id' => 'required|exists:units,id',
            'brand_id' => 'nullable|exists:brands,id',
            'cost_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'alert_quantity' => 'nullable|integer',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
            }

            $sku = 'PRD-' . strtoupper(Str::random(8));

            $product = Product::create([
                'name' => $request->name,
                'slug' => Str::slug($request->name) . '-' . Str::random(4),
                'sku' => $sku,
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'unit_id' => $request->unit_id,
                'cost_price' => $request->cost_price,
                'selling_price' => $request->selling_price,
                'stock_quantity' => 0,
                'alert_quantity' => $request->alert_quantity ?? 5,
                'image' => $imagePath,
                'description' => $request->description
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            Log::error('Product Create Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Failed to create product.'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $product = Product::with(['category', 'brand', 'unit'])->findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Product retrieved successfully',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found.'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
public function update(Request $request, string $id)
{
    try {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Product not found'], 404);
        }
        $request->validate([
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'brand_id'       => 'nullable|exists:brands,id',
            'unit_id'        => 'required|exists:units,id',
            'purchase_price' => 'nullable|numeric',
            'selling_price'  => 'required|numeric',
            'stock_quantity' => 'nullable|integer',
            'sku'            => 'required|string|unique:products,sku,' . $id,
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        $data = [
            'name'           => $request->name,
            'slug'           => $request->name !== $product->name ? Str::slug($request->name) . '-' . Str::random(4) : $product->slug,
            'sku'            => $request->sku,
            'category_id'    => $request->category_id,
            'brand_id'       => $request->brand_id,
            'unit_id'        => $request->unit_id,
            'cost_price'     => $request->purchase_price ?? 0,

            'selling_price'  => $request->selling_price,
            'stock_quantity' => $request->stock_quantity ?? 0,
            'alert_quantity' => $request->alert_quantity ?? 5,
            'description'    => $request->description,
        ];

        if ($request->hasFile('image')) {
            if ($product->image && Storage::disk('public')->exists($product->image)) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        return response()->json([
            'status' => true,
            'message' => 'Product updated successfully',
            'data' => $product
        ]);

    } catch (\Exception $e) {
        \Log::error('Product Update Error: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Remove the specified resource from storage.
     */
public function destroy(string $id)
{
    try {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['status' => false, 'message' => 'Product not found'], 404);
        }

        $imagePath = $product->image;
        $product->delete();

        if ($imagePath && \Illuminate\Support\Facades\Storage::disk('public')->exists($imagePath)) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($imagePath);
        }

        return response()->json([
            'status' => true,
            'message' => 'Product deleted successfully'
        ]);

    } catch (\Illuminate\Database\QueryException $e) {

        if ($e->getCode() == "23000" || $e->getCode() == "23503") {
             return response()->json([
                'status' => false,
                'message' => 'Cannot delete: This product is linked to Sales or Purchases history.'
            ], 400);
        }

        \Log::error('DB Delete Error: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Database Error: ' . $e->getMessage()
        ], 500);

    } catch (\Exception $e) {
        \Log::error('General Delete Error: ' . $e->getMessage());
        return response()->json([
            'status' => false,
            'message' => 'Server Error: ' . $e->getMessage()
        ], 500);
    }
}
}
