<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentCategory;
use App\Http\Requests\StorePaymentCategoryRequest;

class PaymentCategoryController extends Controller
{
    public function index()
    {
        return response()->json(PaymentCategory::all());
    }

    public function store(StorePaymentCategoryRequest $request)
    {
        $category = PaymentCategory::create($request->validated());
        return response()->json(['success' => true, 'category' => $category], 201);
    }
}
