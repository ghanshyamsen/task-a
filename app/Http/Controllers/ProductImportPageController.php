<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class ProductImportPageController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard');
    }
}