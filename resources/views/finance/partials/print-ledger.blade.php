<x-modal name="print-ledger" max-width="4xl">
    <div class="flex h-[85vh] flex-col p-6">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Student Ledger</h2>
            <div class="flex items-center gap-2">
                <button type="button" x-on:click="$refs.ledgerPrintFrame.contentWindow.print()" class="rounded-md bg-gray-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-blue-600 dark:hover:bg-blue-500">
                    Print
                </button>
                <button type="button" x-on:click="$dispatch('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">&times;</button>
            </div>
        </div>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Matches the ledger filters currently applied on this page.</p>
        <div class="mt-4 min-h-0 flex-1 overflow-auto rounded-md border border-gray-200 bg-gray-200 dark:border-gray-700 dark:bg-gray-950">
            <iframe
                x-ref="ledgerPrintFrame"
                x-bind:src="ledgerPrintLoaded ? @js(route('finance.students.ledger-print', array_merge(['student' => $selectedStudent, 'embed' => 1], array_filter($filters)))) : ''"
                loading="lazy"
                class="h-full w-full min-w-[297mm]"
            ></iframe>
        </div>
    </div>
</x-modal>
