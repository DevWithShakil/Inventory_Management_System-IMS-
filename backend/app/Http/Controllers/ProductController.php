<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use App\Models\Supplier;      // 🔥 Added
use App\Models\Purchase;      // 🔥 Added
use App\Models\PurchaseItem;  // 🔥 Added
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

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
            'name'           => 'required|string|max:255',
            'category_id'    => 'required|exists:categories,id',
            'unit_id'        => 'required|exists:units,id',
            'brand_id'       => 'nullable|exists:brands,id',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price'  => 'required|numeric|min:0',
            'stock_quantity' => 'nullable|integer|min:0',
            'alert_quantity' => 'nullable|integer',
            'sku'            => 'required|string|unique:products,sku',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $imagePath = $request->file('image')->store('products', 'public');
            }

            $product = Product::create([
                'name'           => $request->name,
                'slug'           => Str::slug($request->name) . '-' . Str::random(4),
                'sku'            => $request->sku,
                'category_id'    => $request->category_id,
                'brand_id'       => $request->brand_id,
                'unit_id'        => $request->unit_id,
                'cost_price'     => $request->purchase_price,
                'selling_price'  => $request->selling_price,
                'stock_quantity' => $request->stock_quantity ?? 0,
                'alert_quantity' => $request->alert_quantity ?? 5,
                'image'          => $imagePath,
                'description'    => $request->description
            ]);

            return response()->json([
                'status'  => true,
                'message' => 'Product created successfully',
                'data'    => $product
            ], 201);

        } catch (\Exception $e) {
            Log::error('Product Create Error: ' . $e->getMessage());
            return response()->json([
                'status'  => false,
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
            Log::error('Product Update Error: ' . $e->getMessage());
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

            if ($imagePath && Storage::disk('public')->exists($imagePath)) {
                Storage::disk('public')->delete($imagePath);
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

            Log::error('DB Delete Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Database Error: ' . $e->getMessage()
            ], 500);

        } catch (\Exception $e) {
            Log::error('General Delete Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Server Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search products for POS
     */
    public function searchProduct(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json([]);
        }

        $products = Product::where('name', 'LIKE', "%{$query}%")
                    ->orWhere('sku', 'LIKE', "%{$query}%")
                    ->select('id', 'name', 'image', 'stock_quantity', 'selling_price', 'sku')
                    ->limit(5)
                    ->get();

        return response()->json($products);
    }

    /**
     * Import products via CSV with Purchase History
     */
    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:csv,txt',
        ]);

        $file = $request->file('file');

        if (($handle = fopen($file->getPathname(), 'r')) !== false) {

            fgetcsv($handle); // Skip Header

            DB::beginTransaction();

            try {
                // ১. সাপ্লায়ার তৈরি বা খুঁজে বের করা
                $supplier = Supplier::firstOrCreate(
                    ['name' => 'Bulk Import History'],
                    [
                        'phone' => '0000000000',
                        'address' => 'System Generated',
                        'status' => true
                    ]
                );

                // ২. Purchase রেকর্ড তৈরি (Schema অনুযায়ী ফিক্সড)
                $purchase = Purchase::create([
                    'date'         => now()->format('Y-m-d'),
                    'reference_no' => 'CSV-' . time(),
                    'supplier_id'  => $supplier->id,
                    'status'       => 'completed',
                    'subtotal'     => 0,
                    'grand_total'  => 0,
                    'tax'          => 0,
                    'discount'     => 0,
                    'created_by'   => auth()->id() ?? 1, // 🔥 বাধ্যতামূলক: যে ইউজার লগিন আছে, অথবা ID 1
                ]);

                $grandTotal = 0;

                while (($row = fgetcsv($handle)) !== false) {
                    if (count($row) < 8) continue;

                    $name           = $row[0];
                    $sku            = $row[1];
                    $categoryName   = $row[2];
                    $brandName      = $row[3];
                    $unitName       = $row[4];
                    $costPrice      = (float) $row[5];
                    $sellingPrice   = (float) $row[6];
                    $stock          = (int) $row[7];
                    $alertQty       = $row[8] ?? 5;

                    // Category
                    $category = Category::firstOrCreate(
                        ['name' => $categoryName],
                        ['slug' => Str::slug($categoryName), 'status' => true]
                    );

                    // Brand
                    $brand = null;
                    if ($brandName) {
                        $brand = Brand::firstOrCreate(
                            ['name' => $brandName],
                            ['slug' => Str::slug($brandName), 'status' => true]
                        );
                    }

                    // Unit
                    $unit = Unit::firstOrCreate(
                        ['name' => $unitName ?? 'pcs'],
                        ['short_name' => substr($unitName ?? 'pcs', 0, 10)]
                    );

                    // ৩. Product Update/Create
                    $product = Product::updateOrCreate(
                        ['sku' => $sku],
                        [
                            'name'           => $name,
                            'slug'           => Str::slug($name) . '-' . Str::random(4),
                            'category_id'    => $category->id,
                            'brand_id'       => $brand ? $brand->id : null,
                            'unit_id'        => $unit->id,
                            'cost_price'     => $costPrice,
                            'selling_price'  => $sellingPrice,
                            'stock_quantity' => $stock,
                            'alert_quantity' => $alertQty,
                        ]
                    );

                    // ৪. Purchase Item এন্ট্রি
                    if ($stock > 0) {
                        $lineTotal = $costPrice * $stock;
                        $grandTotal += $lineTotal;

                        PurchaseItem::create([
                            'purchase_id' => $purchase->id,
                            'product_id'  => $product->id,
                            'quantity'    => $stock,
                            'unit_cost'   => $costPrice,
                            'subtotal'    => $lineTotal // Schema অনুযায়ী নাম 'subtotal'
                        ]);
                    }
                }

                // ৫. Purchase এর টোটাল আপডেট করা
                $purchase->update([
                    'subtotal'    => $grandTotal,
                    'grand_total' => $grandTotal,
                ]);

                DB::commit();
                fclose($handle);

                return response()->json([
                    'status' => true,
                    'message' => 'Products imported & Purchase history created!'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                Log::error('Import Error: ' . $e->getMessage());
                return response()->json([
                    'status' => false,
                    'message' => 'Error: ' . $e->getMessage()
                ], 500);
            }
        }

        return response()->json(['status' => false, 'message' => 'Failed to open file'], 500);
    }
}
