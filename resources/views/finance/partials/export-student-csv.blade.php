<x-modal name="export-student-csv" max-width="sm">
    <div class="p-6">
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ __('Export Student CSV') }}</h2>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __("Download this student's finance transactions as a CSV file.") }}</p>
        <div class="mt-6 flex justify-end gap-2">
            <button type="button" x-on:click="$dispatch('close')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                {{ __('Cancel') }}
            </button>
            <a href="{{ route('finance.export', ['student_id' => $selectedStudent->id]) }}" class="inline-flex items-center justify-center rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-blue-600 dark:hover:bg-blue-500">
                {{ __('Download CSV') }}
            </a>
        </div>
    </div>
</x-modal>
