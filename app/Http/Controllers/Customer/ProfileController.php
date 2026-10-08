<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Merchant;
use App\Models\Rating;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function edit()
    {
        $user = Auth::user();

        return view('customer.profile-edit', compact('user'));
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user->update($validated);

        return back()->with('status', 'Profile updated successfully.');
    }

    public function favorites()
    {
        $user = Auth::user();
        $favorites = $user->merchantFavorites()->latest()->paginate(12);

        return view('customer.profile-favorites', compact('favorites'));
    }

    public function toggleFavorite(Request $request, Merchant $merchant)
    {
        $user = Auth::user();

        if ($user->merchantFavorites()->where('merchant_id', $merchant->id)->exists()) {
            $user->merchantFavorites()->where('merchant_id', $merchant->id)->delete();
            return response()->json(['status' => 'removed', 'message' => 'Merchant removed from favorites']);
        } else {
            $user->merchantFavorites()->create(['merchant_id' => $merchant->id]);
            return response()->json(['status' => 'added', 'message' => 'Merchant added to favorites']);
        }
    }
}