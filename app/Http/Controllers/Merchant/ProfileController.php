<?php

namespace App\Http\Controllers\Merchant;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\Merchant;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function edit()
    {
        $profile = Auth::user()->merchantProfile ?? new Merchant(['user_id' => Auth::id()]);

        return view('merchant.profile', compact('profile'));
    }

    public function update(UpdateProfileRequest $request)
    {
        $user = Auth::user();
        $profile = $user->merchantProfile ?? new Merchant(['user_id' => $user->id]);

        $profile->fill($request->validated());
        $profile->save();

        return back()->with('status', 'Profile updated.');
    }
}
