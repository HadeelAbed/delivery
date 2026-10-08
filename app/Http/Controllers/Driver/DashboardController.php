<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Services\DriverStatsService;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index(DriverStatsService $stats)
    {
        $driver = Auth::user();

        return view('driver.dashboard', array_merge(
            ['isOnline' => $driver->is_online],
            ['driverStats' => $stats->stats($driver)],
        ));
    }
}
