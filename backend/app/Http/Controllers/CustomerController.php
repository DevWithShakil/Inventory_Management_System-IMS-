<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'data' => Customer::latest()->get()
        ]);
    }

    public function store(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'phone' => 'required|string|max:20|unique:customers,phone',
        'email' => 'nullable|email|max:255',
    ]);

    try {
        $data = $request->all();
        $data['points'] = 0;
        $data['total_spent'] = 0;

        $customer = Customer::create($data);

        return response()->json([
            'status' => true,
            'message' => 'Customer created successfully',
            'data' => $customer
        ], 201);

    } catch (\Exception $e) {
        \Log::error('Error creating customer: ' . $e->getMessage());

        return response()->json([
            'status' => false,
            'message' => 'Failed to save customer. ' . $e->getMessage(),
            'error' => $e->getMessage()
        ], 500);
    }
}

    public function show(string $id)
    {
        try {
            $customer = Customer::findOrFail($id);
            return response()->json([
                'status' => true,
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Customer not found'], 404);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $customer = Customer::findOrFail($id);

            $request->validate([
                'name' => 'required|string|max:255',
                'phone' => 'required|string|max:20|unique:customers,phone,' . $id,
                'email' => 'nullable|email|max:255',
            ]);

            $customer->update($request->all());

            return response()->json([
                'status' => true,
                'message' => 'Customer updated successfully',
                'data' => $customer
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Update failed'], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $customer = Customer::findOrFail($id);
            $customer->delete();

            return response()->json([
                'status' => true,
                'message' => 'Customer deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Delete failed'], 500);
        }
    }
}
