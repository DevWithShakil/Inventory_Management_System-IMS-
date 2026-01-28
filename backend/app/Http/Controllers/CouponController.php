<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponController extends Controller
{
    // ১. সব কুপন দেখার জন্য (List)
   public function index()
{
    // customer রিলেশনসহ লেটেস্ট কুপন আনুন
    $coupons = Coupon::with('customer')->latest()->get();

    return response()->json([
        'status' => true,
        'data' => $coupons
    ]);
}

    // ২. নতুন কুপন তৈরি করার জন্য (Create) - এই মেথডটিই মিসিং ছিল
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|string|unique:coupons,code',
            'type' => 'required|in:fixed,percent',
            'value' => 'required|numeric|min:0',
            'min_purchase' => 'nullable|numeric|min:0',
            'expires_at' => 'nullable|date',
            'usage_limit' => 'nullable|integer|min:1',
            'customer_id' => 'nullable|exists:customers,id'
        ]);

        $coupon = Coupon::create([
            'code' => strtoupper($request->code), // কোড সবসময় বড় হাতের হবে
            'type' => $request->type,
            'value' => $request->value,
            'min_purchase' => $request->min_purchase ?? 0,
            'expires_at' => $request->expires_at,
            'usage_limit' => $request->usage_limit,
            'customer_id' => $request->customer_id,
            'status' => true
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Coupon created successfully',
            'data' => $coupon
        ], 201);
    }

    // ৩. কুপন ডিলিট করার জন্য (Delete)
    public function destroy($id)
    {
        $coupon = Coupon::find($id);
        if (!$coupon) {
            return response()->json(['status' => false, 'message' => 'Coupon not found'], 404);
        }

        $coupon->delete();

        return response()->json([
            'status' => true,
            'message' => 'Coupon deleted successfully'
        ]);
    }

    // ৪. POS এ কুপন চেক করার জন্য (Check Validity)
    public function checkCoupon(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'cart_total' => 'required|numeric',
            'customer_id' => 'nullable|integer'
        ]);

        $coupon = Coupon::where('code', $request->code)->first();

        // কুপন আছে কি না?
        if (!$coupon) {
            return response()->json(['status' => false, 'message' => 'Invalid coupon code.'], 404);
        }

        // কুপন ভ্যালিড (Status, Expiry, Limit) কি না?
        if (!$coupon->isValid()) {
            return response()->json(['status' => false, 'message' => 'Coupon expired or usage limit reached.'], 400);
        }

        // নির্দিষ্ট কাস্টমারের জন্য কি না?
        if ($coupon->customer_id && $coupon->customer_id != $request->customer_id) {
            return response()->json(['status' => false, 'message' => 'This coupon is not valid for this customer.'], 400);
        }

        // মিনিমাম পারচেজ ভ্যালু চেক
        if ($request->cart_total < $coupon->min_purchase) {
            return response()->json([
                'status' => false,
                'message' => "Minimum purchase of ৳{$coupon->min_purchase} required."
            ], 400);
        }

        // ডিসকাউন্ট ক্যালকুলেশন
        $discountAmount = 0;
        if ($coupon->type == 'fixed') {
            $discountAmount = $coupon->value;
        } else {
            $discountAmount = ($request->cart_total * $coupon->value) / 100;
        }

        return response()->json([
            'status' => true,
            'message' => 'Coupon applied successfully!',
            'data' => [
                'code' => $coupon->code,
                'discount' => $discountAmount,
                'type' => $coupon->type,
                'value' => $coupon->value
            ]
        ]);
    }
}
