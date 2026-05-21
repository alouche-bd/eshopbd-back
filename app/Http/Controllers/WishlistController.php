<?php

namespace App\Http\Controllers;

use App\Models\Wishlist;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class WishlistController extends Controller
{

    public function index()
    {
        try {
            $user = Auth::user();

            $wishlist = Wishlist::where('user_id', $user->id)->get();

            return response()->json(['wishlist' => $wishlist], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 404);
        }
    }

    public function show($id)
    {
        try {
            $wishlist = Wishlist::findOrFail($id);
            return response()->json(['wishlist' => $wishlist], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 404);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->only('name', 'products'), [
            'products' => "required",
            'name' => [
                'required',
                Rule::unique('wishlists', 'name')->where('user_id', Auth::user()->id)
            ]
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Required or incorrect fields', 'errors' => $validator->errors()], 500);
        }
        try {
            $user = Auth::user();
            $wishlist = new Wishlist();
            $wishlist->name = $request->input('name');
            $wishlist->products = $request->input('products');
            $wishlist->user_id = $user->id;
            $wishlist->save();

            return response()->json(['success' => 1, 'message' => 'Wishlist saved', 'wishlist' => $wishlist], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 400);
        }
    }

    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->only('name', 'products'), [
            'products' => "required",
            'name' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Required or incorrect fields', 'errors' => $validator->errors()], 500);
        }

        try {
            $wishlist = Wishlist::findOrFail($id);
            $wishlist->name = $request->input('name');
            $wishlist->products = $request->input('products');
            $wishlist->save();
            return response()->json(['success' => 1, 'message' => 'Wishlist saved', 'wishlist' => $wishlist], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 400);
        }
    }


    public function destroy($id)
    {
        try {
            $wishlist = Wishlist::findOrFail($id);
            $wishlist->delete();

            return response()->json(['success' => 1, 'message' => 'Wishlist cleared successfully'], 200);
        } catch (Exception $e) {
            return response()->json(['success' => 0, 'message' => $e->getMessage()], 400);
        }
    }
}
