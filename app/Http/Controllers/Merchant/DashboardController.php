<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $recentOrders = Auth::user()->merchantOrders()->latest()->take(10)->get();

        return view('merchant.dashboard', compact('recentOrders'));
    }
}
