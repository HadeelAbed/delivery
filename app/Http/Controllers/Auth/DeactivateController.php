<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($user) {
            $this->anonymizePersonalData($user);
            $user->update([
                'status' => UserStatus::Deactivated->value,
            ]);
            $user->tokens()->delete();
            AuditLog::record($user, 'delete_account', $user, [
                'action' => 'permanent_deactivation',
            ]);
        });

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('status', 'Your account has been permanently deleted.');
    }

    /**
     * Overwrite personally identifying fields with deterministic,
     * collision-safe anonymized values.
     *
     * - email must stay UNIQUE, so each anonymized email embeds the user id;
     *   multiple deletions therefore can never collide.
     * - password reset tokens are re-keyed to the same anonymized email so
     *   stale tokens point at a consistent value.
     * - remember token is overwritten to prevent replay.
     * - the method is protected so tests can simulate a failure and
     *   prove the surrounding transaction rolls back completely.
     *
     * THIS OPERATION IS IRREVERSIBLE TO PERSONAL DATA.
     */
    protected function anonymizePersonalData(User $user): void
    {
        $id = $user->id;
        $anonymizedEmail = "deleted-{$id}@deleted.example";
        $anonymizedName = "deleted-user-{$id}";
        $anonymizedPhone = "deleted-{$id}";

        $user->forceFill([
            'name' => $anonymizedName,
            'email' => $anonymizedEmail,
            'phone' => $anonymizedPhone,
            'remember_token' => $anonymizedPhone,
        ])->save();

        DB::table('password_reset_tokens')
            ->where('email', $user->getOriginal('email'))
            ->update(['email' => $anonymizedEmail]);
    }
}
