<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function create()
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended($this->homeUri(Auth::user()));
        }

        throw ValidationException::withMessages([
            'email' => __('These credentials do not match our records.'),
        ]);
    }

    private function homeUri($user): string
    {
        return match ($user->role) {
            UserRole::Customer => route('customer.home'),
            UserRole::Merchant => route('merchant.dashboard'),
            UserRole::Driver => route('driver.dashboard'),
            UserRole::Admin => route('admin.approvals'),
        };
    }
}
