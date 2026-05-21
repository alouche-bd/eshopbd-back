<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Wishlist;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index()
    {
        try {
            $user = Auth::user();

            $order = Order::where('user_id', $user->id)->orderBy('created_at', 'desc')->get();

            return response()->json(['order' => $order], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 404);
        }
    }

    public function show($id)
    {
        try {
            $order = Order::with('product')->find($id);;
            return response()->json(['order' => $order], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $userId = $user->id;
            $order = new Order();
            $order->finalClientCode = $request->input('finalClientCode');
            $order->finalClient = $request->input('finalClient');
            $order->shippingAddress = $request->input('shippingAddress');
            $order->user_id = $userId;
            $order->save();

            $productsWeb = $request->input('products');

            foreach ($productsWeb as $item) {
                $productWeb = new Product();
                $productWeb->order_id = $order->id;
                $productWeb->reference = $item['reference'];
                $productWeb->cartQuantity = $item['cartQuantity'];
                $productWeb->lot = $item['lot'];
                $productWeb->comment = $item['comment'] ?? null;

                $productWeb->save();
            }


            return response()->json(['success' => 1, 'message' => 'Order saved', 'order' => $order], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 400);
        }
    }
}
