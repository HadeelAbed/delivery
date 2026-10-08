<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function create()
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();
        $role = UserRole::from($data['role']);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'],
            'role' => $role->value,
            'status' => $role === UserRole::Customer
                ? UserStatus::Active->value
                : UserStatus::Pending->value,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect()->route($this->homeRoute($role));
    }

    private function homeRoute(UserRole $role): string
    {
        return match ($role) {
            UserRole::Customer => 'customer.home',
            UserRole::Merchant => 'merchant.dashboard',
            UserRole::Driver => 'driver.dashboard',
            UserRole::Admin => 'welcome',
        };
    }
}
