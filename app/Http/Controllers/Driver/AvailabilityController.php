<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AvailabilityController extends Controller
{
    public function toggle()
    {
        $user = Auth::user();
        $user->is_online = ! $user->is_online;
        $user->save();

        return back()->with('status', $user->is_online ? 'You are now online.' : 'You are now offline.');
    }
}
