<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
      public function store(Request $request)
    {
        $request->validate([
            'title' => 'required',
            'message' => 'required',
        ]);
        $data = [
            'user_id' => auth()->user()->id,
            'title' => $request->title,
            'message' => $request->message,
        ];
        $contact = Contact::create($data);
        return response()->json([
            'status' => 'success',
            'message' => 'Contact added successfully',
            'data' => $contact,
        ]);
    }
}
