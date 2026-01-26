<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Library\SslCommerz\SslCommerzNotification;
use App\Models\Sale;
use App\Models\Product;
use App\Models\SaleItem;
use Illuminate\Support\Facades\DB;

class SslCommerzController extends Controller
{
    public function payViaAjax(Request $request)
    {
        $request->validate([
            'items' => 'required|array',
            'grand_total' => 'required',
            'payment_method' => 'required',
        ]);

        DB::beginTransaction();
        try {
            $invoiceNo = 'INV-' . time() . rand(10,99);

            $sale = Sale::create([
                'customer_id' => $request->customer_id,
                'invoice_no' => $invoiceNo,
                'date' => now(),
                'subtotal' => $request->sub_total,
                'discount' => $request->discount ?? 0,
                'grand_total' => $request->grand_total,
                'paid_amount' => 0,
                'due_amount' => $request->grand_total,
                'payment_method' => $request->payment_method,
                'payment_status' => 'pending',
                'transaction_id' => uniqid(),
                'created_by' => auth()->id() ?? 1,
            ]);

            foreach ($request->items as $item) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal']
                ]);

                $product = Product::find($item['product_id']);
                if($product) {
                    $product->decrement('stock_quantity', $item['quantity']);
                }
            }

            DB::commit();

            $post_data = array();
            $post_data['total_amount'] = $sale->grand_total;
            $post_data['currency'] = "BDT";
            $post_data['tran_id'] = $sale->transaction_id;
            $post_data['success_url'] = route('ssl.success');
            $post_data['fail_url'] = route('ssl.fail');
            $post_data['cancel_url'] = route('ssl.cancel');
            $post_data['cus_name'] = $sale->customer->name ?? "Walk-in Customer";
            $post_data['cus_email'] = $sale->customer->email ?? "customer@pos.com";
            $post_data['cus_add1'] = "Dhaka";
            $post_data['cus_add2'] = "";
            $post_data['cus_city'] = "";
            $post_data['cus_state'] = "";
            $post_data['cus_postcode'] = "";
            $post_data['cus_country'] = "Bangladesh";
            $post_data['cus_phone'] = $sale->customer->phone ?? "01700000000";
            $post_data['cus_fax'] = "";
            $post_data['ship_name'] = "Store Sale";
            $post_data['ship_add1'] = "Dhaka";
            $post_data['ship_add2'] = "Dhaka";
            $post_data['ship_city'] = "Dhaka";
            $post_data['ship_state'] = "Dhaka";
            $post_data['ship_postcode'] = "1000";
            $post_data['ship_country'] = "Bangladesh";

            $post_data['shipping_method'] = "NO";
            $post_data['product_name'] = "POS Items";
            $post_data['product_category'] = "Goods";
            $post_data['product_profile'] = "physical-goods";

            $sslc = new SslCommerzNotification();
            $payment_options = $sslc->makePayment($post_data, 'checkout', 'json');

            if (!is_array($payment_options)) {
                return response()->json(['status' => false, 'message' => 'Gateway Connection Failed'], 500);
            }

            if (isset($payment_options['status']) && $payment_options['status'] == 'SUCCESS') {
                return response()->json([
                    'status' => true,
                    'url' => $payment_options['GatewayPageURL'],
                    'logo' => $payment_options['storeLogo'] ?? null
                ]);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => $payment_options['failedreason'] ?? 'Payment initiation failed'
                ]);
            }

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function success(Request $request)
    {
        $tran_id = $request->input('tran_id');
        $amount = $request->input('amount');
        $currency = $request->input('currency');

        $sslc = new SslCommerzNotification();


        $validation = $sslc->orderValidate($request->all(), $tran_id, $amount, $currency);

        if ($validation) {
        $sale = Sale::where('transaction_id', $tran_id)->first();

        if($sale) {
            $sale->update([
                'payment_status' => 'paid',
                'paid_amount' => $amount,
                'due_amount' => 0,
            ]);

            return redirect('http://localhost:5173/pos?payment_success=true&sale_id=' . $sale->id);
        }
        }

        return redirect('http://localhost:5173/sales/failed');
    }

    public function fail(Request $request)
    {
        return redirect('http://localhost:5173/sales/failed');
    }

    public function cancel(Request $request)
    {
        return redirect('http://localhost:5173/sales/cancel');
    }
}
