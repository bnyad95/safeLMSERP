<x-guest-layout>
    <div class="mb-6">
        <p class="text-sm font-semibold text-blue-700 dark:text-indigo-300">One-time setup</p>
        <h1 class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">Install SafeLMS ERP</h1>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
            Connect the prepared cPanel database and create the first Super Administrator.
        </p>
    </div>

    @if($errors->has('installer'))
        <div class="mb-5 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300">
            {{ $errors->first('installer') }}
        </div>
    @endif

    <form method="POST" action="{{ route('installer.install') }}" class="space-y-5">
        @csrf

        <div>
            <x-input-label for="installation_code" value="Installation code" />
            <x-text-input
                id="installation_code"
                class="mt-1 block w-full"
                type="password"
                name="installation_code"
                required
                autofocus
                autocomplete="off"
            />
            <x-input-error :messages="$errors->get('installation_code')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="name" value="Administrator name" />
            <x-text-input id="name" class="mt-1 block w-full" type="text" name="name" :value="old('name')" required autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="email" value="Administrator email" />
            <x-text-input id="email" class="mt-1 block w-full" type="email" name="email" :value="old('email')" required autocomplete="email" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password" value="Administrator password" />
            <x-text-input id="password" class="mt-1 block w-full" type="password" name="password" required autocomplete="new-password" />
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">At least 12 characters with uppercase, lowercase, and numbers.</p>
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" class="mt-1 block w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
        </div>

        <x-primary-button class="w-full justify-center">
            Install and create administrator
        </x-primary-button>
    </form>
</x-guest-layout>
