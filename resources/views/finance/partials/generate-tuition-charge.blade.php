@if($canCreateInvoice)
    <x-modal name="generate-tuition-charge" max-width="lg" focusable>
        <div class="p-6">
            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">Generate Tuition Charge</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Compute an invoice from this student's active enrollments for a semester (credits &times; the department's per-credit rate), or enter an amount to charge manually. Already-charged enrollments are skipped automatically.</p>

            @if($tuitionChargeSemesterOptions->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No active or upcoming semester is available for this student's university.</p>
                <div class="mt-6 flex justify-end">
                    <button type="button" x-on:click="$dispatch('close')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Close</button>
                </div>
            @else
                <form method="POST" action="{{ route('finance.students.tuition-charges.store', $selectedStudent) }}" class="mt-4 grid gap-4 sm:grid-cols-2">
                    @csrf
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                        <select name="semester_id" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                            @foreach($tuitionChargeSemesterOptions as $semester)
                                <option value="{{ $semester->id }}">{{ $semester->name }} {{ $semester->academicYear?->name }} ({{ ucfirst($semester->academicYear?->status) }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Currency</label>
                        <select name="currency" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                            <option value="IQD">IQD</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Transaction Date</label>
                        <input type="date" name="transaction_date" value="{{ now()->toDateString() }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" required>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Due Date</label>
                        <input type="date" name="due_date" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Defaults to the semester's end date.</p>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Amount (optional)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" placeholder="Auto-calculate" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                        <p class="mt-1 text-xs text-gray-400 dark:text-gray-500">Leave blank to auto-calculate from credits &times; rate.</p>
                    </div>
                    <div class="min-w-0 sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                        <input type="text" name="notes" maxlength="2000" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                    </div>
                    <div class="flex justify-end gap-2 sm:col-span-2">
                        <button type="button" x-on:click="$dispatch('close')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</button>
                        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                            Generate Charge
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </x-modal>
@endif
