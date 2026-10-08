<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;

class BrowseController extends Controller
{
    public function index()
    {
        $merchants = User::merchant()
            ->where('status', 'active')
            ->with('merchantProfile')
            ->get();

        return view('customer.browse', compact('merchants'));
    }

    public function show(User $merchant)
    {
        abort_unless($merchant->role->value === 'merchant' && $merchant->isApproved(), 404);

        $categories = $merchant->merchantProfile?->categories()
            ->where('is_active', true)
            ->with(['products' => fn ($q) => $q->where('is_available', true)])
            ->get() ?? collect();

        return view('customer.merchant', compact('merchant', 'categories'));
    }
}
