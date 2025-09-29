<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Contracts\View\View;

class ProductBrowserController extends Controller
{
    public function index(): View
    {
        $products = Product::with(['primaryImage', 'images' => function ($query): void {
            $query->orderBy('variant');
        }])->latest()->paginate(20);

        return view('products.index', [
            'products' => $products,
        ]);
    }
}