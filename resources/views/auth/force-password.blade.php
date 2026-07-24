<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Set Your Password</h2>
            <p class="text-sm text-gray-600">Replace the temporary password before continuing.</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @include('profile.partials.update-password-form', [
                    'title' => 'Create your own password',
                    'description' => 'Enter the temporary password you received, then choose a new password for your account.',
                    'buttonLabel' => 'Continue',
                ])
            </div>
        </div>
    </div>
</x-app-layout>
