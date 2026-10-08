<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('admin.access');

        $filters = $request->only(['role', 'status']);

        $query = User::query()->with('merchantProfile')->latest();

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $users = $query->paginate(20)->withQueryString();

        return view('admin.users', [
            'users' => $users,
            'filters' => $filters,
            'roles' => UserRole::cases(),
            'statuses' => ['pending', 'active', 'rejected', 'deactivated'],
        ]);
    }
}
