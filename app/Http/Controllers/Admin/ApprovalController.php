<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\AccountApproved;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ApprovalController extends Controller
{
    public function index()
    {
        Gate::authorize('admin.access');

        $merchants = User::merchant()->pending()->with('merchantProfile')->get();
        $drivers = User::driver()->pending()->with('driverProfile')->get();

        return view('admin.approvals', compact('merchants', 'drivers'));
    }

    public function approve(User $user)
    {
        Gate::authorize('admin.access');

        $user->update(['status' => UserStatus::Active->value]);

        AuditLog::record(Auth::user(), 'approve_'.$user->role->value, $user);

        $user->notify(new AccountApproved);

        return back()->with('status', 'Account approved.');
    }

    public function reject(User $user, RejectRequest $request)
    {
        Gate::authorize('admin.access');

        $reason = $request->validated()['reason'];

        $user->update(['status' => UserStatus::Rejected->value]);

        $profile = $user->role === UserRole::Merchant
            ? $user->merchantProfile
            : $user->driverProfile;

        $profile?->update(['rejection_reason' => $reason]);

        AuditLog::record(Auth::user(), 'reject_'.$user->role->value, $user, [], $reason);

        return back()->with('status', 'Account rejected.');
    }
}
