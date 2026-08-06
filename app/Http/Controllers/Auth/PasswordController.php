<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordController extends Controller
{
    public function forceEdit(): View
    {
        return view('auth.force-password');
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $wasForced = (bool) $request->user()->must_change_password;

        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
            'must_change_password' => false,
        ]);
        $request->session()->put('auth.password_fingerprint', hash('sha256', $request->user()->getAuthPassword()));
        $request->session()->put('auth.password_fingerprint_user_id', $request->user()->getAuthIdentifier());

        if ($wasForced) {
            return redirect()->route('dashboard')->with('status', 'password-updated');
        }

        return back()->with('status', 'password-updated');
    }
}
