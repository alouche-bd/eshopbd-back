<?php

namespace App\Http\Controllers;

use App\Mail\OrderConfirmMailer;
use App\Mail\OrderConfirmMailerLab;
use App\Mail\OrderConfirmNoPriceMailer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class OrderconfirmController extends Controller
{
    public function confirmOrder(Request $request): \Illuminate\Http\JsonResponse
    {
        try {

            $order = $request->input("order");
            $lab = $request->input("lab");
            $finalClientEmail = $request->input("finalClientEmail");
            $userEmail = $request->input("email");

            if ($finalClientEmail) {
                Mail::to($finalClientEmail)
                    ->send(new OrderConfirmMailerLab($order, $lab));

                Mail::to($userEmail)
                    ->send(new OrderConfirmNoPriceMailer($order));
                return response()->json(["message" => "Message sent successfully."]);
            } else {
                Mail::to($userEmail)
                    ->send(new OrderConfirmMailer($order));
                return response()->json(["message" => "Message sent successfully."]);
            }
        } catch (Exception $e) {
            return response()->json(['message' => $e->getMessage()]);
        }
    }

}
