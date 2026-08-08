@if($canCreateInvoice)
    <section class="min-w-0 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
        <h3 class="text-lg font-semibold text-gray-900">Generate Tuition Charge</h3>
        <p class="mt-1 text-sm text-gray-500">Compute an invoice from this student's active enrollments for a semester (credits &times; the department's per-credit rate). Already-charged enrollments are skipped automatically.</p>

        @if($tuitionChargeSemesterOptions->isEmpty())
            <p class="mt-4 text-sm text-gray-500">No active or upcoming semester is available for this student's university.</p>
        @else
            <form method="POST" action="{{ route('finance.students.tuition-charges.store', $selectedStudent) }}" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700">Semester</label>
                    <select name="semester_id" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        @foreach($tuitionChargeSemesterOptions as $semester)
                            <option value="{{ $semester->id }}">{{ $semester->name }} {{ $semester->academicYear?->name }} ({{ ucfirst($semester->academicYear?->status) }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700">Currency</label>
                    <select name="currency" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="IQD">IQD</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700">Transaction Date</label>
                    <input type="date" name="transaction_date" value="{{ now()->toDateString() }}" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                </div>
                <div class="min-w-0">
                    <label class="block text-sm font-medium text-gray-700">Due Date</label>
                    <input type="date" name="due_date" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    <p class="mt-1 text-xs text-gray-400">Defaults to the semester's end date.</p>
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="block text-sm font-medium text-gray-700">Notes</label>
                    <input type="text" name="notes" maxlength="2000" class="mt-1 block w-full min-w-0 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                </div>
                <div class="sm:col-span-2 lg:col-span-4">
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Generate Charge
                    </button>
                </div>
            </form>
        @endif
    </section>
@endif
