<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;


class CustomerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return response()->json([
        'status' => 'success',
        'data'=> Customer::latest()->get()
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20|unique:customers,phone',

        ]);

        try {
            $customer = Customer::create($request->all());
            return response()->json([
                'status' => 'success',
                'message' => 'Customer created successfully',
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            Log::error('Error creating customer: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error creating customer',
                'data' => $e->getMessage()
            ]);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        try {
            $customer = Customer::findOrFail($id);

            return response()->json([
                'status' => true,
                'message' => 'Customer retrieved successfully',
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            Log::error('Customer Fetch Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.'
            ], 404);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        try {
            $customer = Customer::findOrFail($id);
            if (!$customer) return response()->json([
                'status' => false,
                'message' => 'Customer not found'
            ], 404);

            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255|unique:customers,email,' . $id,
                'phone' => 'required|string|max:20|unique:customers,phone,' . $id,
            ]);

            $customer->update($request->all());
            return response()->json([
                'status' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            Log::error('Customer Update Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.'
            ], 404);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
       try {
            $customer = Customer::find($id);
            if (!$customer) return response()->json([
                'status' => false,
                'message' => 'Customer not found'
            ], 404);

            $customer->delete();
            return response()->json([
                'status' => true,
                'message' => 'Customer deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Customer Deletion Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Customer not found.'
            ], 404);
        }
    }
}
