<?php

namespace App\Http\Controllers;
use App\Models\Unit;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
            try {
                return response()->json([
                'status' => true,
                'message' => 'Units retrieved successfully',
                'data' => Unit::latest()->get()
            ]);
            } catch (\Exception $e) {
                return response()->json(['status' => false, 'message' => 'Error fetching units'], 500);
            }

    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'short_name' => 'required|string|max:10',
        ]);

        try {
           $unit = Unit::create([
                'name' => $request->name,
                'short_name' => $request->short_name,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Unit created successfully',
                'data' => $unit
            ], 201);

        } catch (\Exception $e) {
            Log::error('Unit Create Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Failed to create unit'], 500);
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
            $unit = Unit::find($id);
            if (!$unit) return response()->json([
                'status' => false,
                'message' => 'Unit not found'
            ], 404);

            $unit->delete();
            return response()->json([
                'status' => true,
                'message' => 'Unit deleted successfully'
                ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete unit'
            ], 500);
        }
    }
}
