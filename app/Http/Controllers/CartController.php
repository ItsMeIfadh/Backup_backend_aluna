<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    public function index()
    {
        $cartItems = Cart::where('user_id', auth()->id())
            ->with('product.discounts', 'product.ratings', 'product.category', 'product.user')
            ->get();

        return response()->json([
            'cart' => $cartItems->map(function ($item) {
                $product = $item->product;

                return [
                    'cart_id' => $item->id,
                    'product' => [
                        'id' => $product->id,
                        'title' => $product->title,
                        'description' => $product->description,
                        'price' => $product->price,
                        'user_id' => $product->user_id,
                        'creator_name' => $product->user->username ?? null,
                        'category' => [
                            'id' => $product->category->id ?? null,
                            'name' => $product->category->name ?? null,
                        ],
                        'discounts' => $product->discounts->map(function ($discount) {
                            return [
                                'code' => $discount->code,
                                'percentage' => $discount->percentage,
                                'expires_at' => $discount->expires_at,
                            ];
                        }),
                        'final_price' => $product->discounts->isNotEmpty()
                            ? $product->price - ($product->price * ($product->discounts->first()->percentage / 100))
                            : $product->price,
                        'average_rating' => $product->ratings->avg('rating') ? round($product->ratings->avg('rating'), 1) : null,
                        'images_path' => $product->images_path,
                        'images_url' => $product->images_url,
                        'created_at' => $product->created_at->format('d-m-Y H:i'),
                        'updated_at' => $product->updated_at->format('d-m-Y H:i'),
                    ],
                    'quantity' => $item->quantity
                ];
            })
        ]);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'nullable|integer|min:1'
        ]);



        $cartItem = Cart::firstOrCreate(
            ['user_id' => $user->id, 'product_id' => $request->product_id, 'quantity' => $request->get('quantity', 1)] // default quantity to 1
        );

        $cartItem->load(['user', 'product']);

        return response()->json([
            'message' => 'Product added to cart',
            'cart' => $cartItem
        ]);
    }

    public function destroy($id)
    {
        $cartItem = Cart::where('id', $id)->where('user_id', auth()->id())->firstOrFail();
        $cartItem->delete();

        return response()->json(['message' => 'Product removed from cart']);
    }
}
