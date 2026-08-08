<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Tuition Rates</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Define the per-credit tuition rate for each department, academic year, and currency. Closed years remain read-only.</p>
            </div>
            <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                Back to Bologna Definition
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                    <p class="font-semibold">Please review the tuition rate values.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <form action="{{ route('bologna-definition.tuition-rates.store') }}" method="POST" class="space-y-5 p-5">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Department</th>
                                    @forelse($academicYears as $academicYear)
                                        <th colspan="{{ count($currencies) }}" class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">
                                            {{ $academicYear->name }}
                                            <span class="block normal-case text-gray-400 dark:text-gray-500">{{ ucfirst($academicYear->status) }}</span>
                                        </th>
                                    @empty
                                        <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Academic Year</th>
                                    @endforelse
                                </tr>
                                @if($academicYears->isNotEmpty())
                                    <tr>
                                        <th class="px-5 py-1"></th>
                                        @foreach($academicYears as $academicYear)
                                            @foreach($currencies as $currency)
                                                <th class="px-5 py-1 text-left text-[11px] font-semibold uppercase text-gray-400 dark:text-gray-500">{{ $currency }}</th>
                                            @endforeach
                                        @endforeach
                                    </tr>
                                @endif
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse($departments as $department)
                                    @php
                                        $departmentRates = $rates->get($department->id, collect())->keyBy(fn ($rate) => $rate->academic_year_id.'|'.$rate->currency);
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $department->name }}
                                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $department->college?->name }} &middot; {{ $department->university?->name }}</span>
                                        </td>
                                        @forelse($academicYears as $academicYear)
                                            @foreach($currencies as $currency)
                                                @php
                                                    $existing = $departmentRates->get($academicYear->id.'|'.$currency);
                                                    $oldValue = old("rates.{$department->id}.{$academicYear->id}.{$currency}");
                                                    $value = $oldValue ?? $existing?->rate_per_credit;
                                                    $readOnly = ! $canManageAcademicSetup || $academicYear->isLocked();
                                                @endphp
                                                <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                                    <input
                                                        type="number"
                                                        step="0.01"
                                                        min="0.01"
                                                        max="999999.99"
                                                        name="rates[{{ $department->id }}][{{ $academicYear->id }}][{{ $currency }}]"
                                                        value="{{ $value }}"
                                                        placeholder="Not set"
                                                        class="w-32 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800"
                                                        @disabled($readOnly)
                                                    >
                                                </td>
                                            @endforeach
                                        @empty
                                            <td class="px-5 py-4 text-sm text-gray-500 dark:text-gray-400">No active or upcoming academic year in scope.</td>
                                        @endforelse
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 1 + $academicYears->count() * count($currencies) }}" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No departments are available in your scope.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</a>
                        @if($canManageAcademicSetup)
                            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @disabled($departments->isEmpty() || $academicYears->isEmpty())>
                                Save Tuition Rates
                            </button>
                        @endif
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
