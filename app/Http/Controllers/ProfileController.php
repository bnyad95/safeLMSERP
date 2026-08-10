<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $student = null;

        if ($request->user()->hasRole('student')) {
            $student = Student::with(['university', 'department', 'guardians', 'documents'])
                ->where('user_id', $request->user()->id)
                ->first()
                ?: Student::with(['university', 'department', 'guardians', 'documents'])
                    ->where('email', $request->user()->email)
                    ->first();
        }

        return view('profile.edit', [
            'user' => $request->user(),
            'student' => $student,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        if ($user->hasAnyRole(['student', 'teacher'])) {
            throw ValidationException::withMessages([
                'password' => 'Student and teacher accounts cannot be deleted here. Ask an administrator to archive this account.',
            ])->errorBag('userDeletion');
        }

        if ($user->isLastSuperAdministrator()) {
            throw ValidationException::withMessages([
                'password' => 'You are the last super administrator. Promote another account to super administrator before deleting this one.',
            ])->errorBag('userDeletion');
        }

        if ($user->hasPendingDeletionRequest()) {
            return Redirect::route('profile.edit')->with('status', 'account-deletion-already-requested');
        }

        $user->forceFill(['deletion_requested_at' => now()])->save();

        return Redirect::route('profile.edit')->with('status', 'account-deletion-requested');
    }
}
