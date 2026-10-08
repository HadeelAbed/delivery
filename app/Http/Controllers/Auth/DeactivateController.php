<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DeactivateController extends Controller
{
    /**
     * Deactivate the authenticated user's own account (REQ-18 data deactivation).
     *
     * Sets status=deactivated, revokes API tokens, writes an audit row,
     * then signs the user out of the current session.
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $user->update(['status' => UserStatus::Deactivated->value]);

        $user->tokens()->delete();

        AuditLog::record($user, 'deactivate_account', $user);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account has been deactivated.');
    }
}
