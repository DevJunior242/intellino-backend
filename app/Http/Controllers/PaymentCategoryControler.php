<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PaymentCategory;

class PaymentCategoryControler extends Controller
{

    public function index()
    {
        return response()->json(PaymentCategory::all());
    }
}
