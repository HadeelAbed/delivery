<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminAnalyticsService;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index(AdminAnalyticsService $analytics)
    {
        Gate::authorize('admin.access');

        return view('admin.dashboard', [
            'stats' => $analytics->overview(),
        ]);
    }
}
