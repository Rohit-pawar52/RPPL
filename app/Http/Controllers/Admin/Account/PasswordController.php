<?php

namespace App\Http\Controllers\Admin\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Account\UpdatePasswordRequest;
use App\Support\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * The signed-in user changes their own password. Open to every active account, including one whose
 * role cannot enter the panel (see routes/admin.php), so there is no Gate or Policy here: the user
 * can only ever change their own password.
 */
class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('admin.account.password');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        // A new remember token ends any "remember me" cookie issued under the old password.
        $user->forceFill([
            'password' => Hash::make($request->validated('password')),
            'remember_token' => Str::random(60),
        ])->save();

        // Whoever else is signed in as this user is signed out - the point of changing a password that
        // may be known to others. This browser stays signed in, on a fresh session id.
        UserSessions::forget($user);
        $request->session()->regenerate();

        return redirect()
            ->route('admin.account.password.edit')
            ->with('success', 'Your password has been changed.');
    }
}
