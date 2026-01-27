<?php

namespace App\Imports;

use App\Models\Product;
use App\Models\Category;
use App\Models\Brand;
use App\Models\Unit;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ProductsImport implements ToModel, WithHeadingRow
{
    public function model(array $row)
    {
        // 1. Category (Find or Create)
        $category = Category::firstOrCreate(['name' => $row['category']]);

        // 2. Brand (Find or Create)
        $brand = $row['brand'] ? Brand::firstOrCreate(['name' => $row['brand']]) : null;

        // 3. Unit (Find or Create)
        $unit = Unit::firstOrCreate(['name' => $row['unit'] ?? 'pcs']);

        // 4. Create Product
        return new Product([
            'name'           => $row['name'],
            'sku'            => $row['sku'],
            'category_id'    => $category->id,
            'brand_id'       => $brand ? $brand->id : null,
            'unit_id'        => $unit->id,
            'cost_price'     => $row['cost_price'],
            'selling_price'  => $row['selling_price'],
            'stock_quantity' => $row['stock'],
            'alert_quantity' => $row['alert_quantity'] ?? 5,
            'image'          => null,
        ]);
    }
}
