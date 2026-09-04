<x-modal name="pending-deletions" max-width="2xl">
    <div class="p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Pending Account Deletions') }}</h2>
            <button type="button" x-on:click="$dispatch('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">&times;</button>
        </div>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Self-requested account deletions waiting for review. Approving archives the account; rejecting keeps it active.') }}</p>

        <div class="mt-4 max-h-[60vh] space-y-3 overflow-y-auto">
            @forelse($pendingDeletionAccounts as $account)
                <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $account->name }}</p>
                            <p class="truncate text-xs text-gray-500 dark:text-gray-400">{{ $account->email }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @forelse($account->roles as $role)
                                    <span class="rounded-md bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">{{ $role->display_name }}</span>
                                @empty
                                    <span class="text-xs text-gray-400">{{ __('No role assigned') }}</span>
                                @endforelse
                            </div>
                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ __('Requested') }} {{ $account->deletion_requested_at->format('Y-m-d H:i') }}</p>
                        </div>
                        @if($abilities['archive'])
                            <div class="flex shrink-0 gap-2">
                                <form action="{{ route('users.deletion.reject', $account) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Reject') }}</button>
                                </form>
                                <form action="{{ route('users.deletion.approve', $account) }}" method="POST" onsubmit="return confirm('{{ __('Approve this deletion request? The account will be archived.') }}')">
                                    @csrf
                                    <button type="submit" class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700">{{ __('Approve') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No pending deletion requests.') }}</p>
            @endforelse
        </div>

        <div class="mt-6 flex justify-end">
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Close') }}</button>
        </div>
    </div>
</x-modal>
