<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Tuition Rates</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Open an academic year to set each department's tuition pricing — per-credit or a flat amount. Closed years remain read-only.</p>
            </div>
            <a href="{{ $backRoute }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                {{ $backLabel }}
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
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

            @forelse($academicYears as $academicYear)
                @php
                    $yearDepartments = $departments->where('university_id', $academicYear->university_id)->values();
                    $pricedCount = $yearDepartments->filter(function ($department) use ($rates, $academicYear) {
                        return $rates->get($department->id, collect())->contains(fn ($rate) => (int) $rate->academic_year_id === (int) $academicYear->id);
                    })->count();
                    $readOnly = ! $canManageAcademicSetup || $academicYear->isLocked();
                    $reopenModal = $errors->any() && collect(old('rates', []))->contains(
                        fn ($years) => is_array($years) && array_key_exists((string) $academicYear->id, $years)
                    );
                @endphp
                <section class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between dark:border-gray-800 dark:bg-gray-900">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">{{ $academicYear->name }}</h3>
                            <span class="rounded-md px-2 py-0.5 text-xs font-semibold {{ $academicYear->status === 'active' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-300' }}">{{ ucfirst($academicYear->status) }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $academicYear->university?->name }}</p>
                        @if($yearDepartments->isNotEmpty())
                            <p class="mt-1 text-sm font-medium {{ $pricedCount === $yearDepartments->count() ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400' }}">
                                {{ $pricedCount }} of {{ $yearDepartments->count() }} department(s) priced
                            </p>
                        @else
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No departments in this university yet.</p>
                        @endif
                    </div>
                    @if($yearDepartments->isNotEmpty())
                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'tuition-rates-{{ $academicYear->id }}')" class="inline-flex w-full justify-center rounded-md border border-gray-900 bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-50 sm:w-auto dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100 dark:hover:bg-gray-800">
                            {{ $readOnly ? 'View Rates' : 'Enter Rates' }}
                        </button>

                        <x-modal name="tuition-rates-{{ $academicYear->id }}" max-width="4xl" :show="$reopenModal">
                            <div class="p-6">
                                <div class="flex items-center justify-between">
                                    <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">{{ $academicYear->university?->name }} &middot; {{ $academicYear->name }}</h2>
                                    <button type="button" x-on:click="$dispatch('close')" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200">&times;</button>
                                </div>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Set each department's pricing model and tuition amount, by currency. Per-credit charges scale with enrolled course credits. Flat amounts are the full program tuition (8 semesters for a university, 4 for an institute) — students on the automatic semester plan are billed an even share of it each semester, and students on the full-payment plan are billed the whole amount once.</p>

                                <form action="{{ route('bologna-definition.tuition-rates.store') }}" method="POST" class="mt-4" x-on:submit="stripMoneyCommas($el)">
                                    @csrf
                                    <div class="max-h-[60vh] overflow-y-auto overflow-x-auto rounded-md border border-gray-200 dark:border-gray-700">
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                                            <thead class="bg-gray-50 dark:bg-gray-950">
                                                <tr>
                                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Department</th>
                                                    <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Pricing Model</th>
                                                    @foreach($currencies as $currency)
                                                        <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $currency }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                                @foreach($yearDepartments as $department)
                                                    @php
                                                        $departmentRates = $rates->get($department->id, collect())->keyBy(fn ($rate) => $rate->academic_year_id.'|'.$rate->currency);
                                                        $existingPricingType = $departmentRates->first(fn ($rate) => (int) $rate->academic_year_id === (int) $academicYear->id)?->pricing_type;
                                                        $initialPricingType = old("pricing_type.{$department->id}.{$academicYear->id}", $existingPricingType ?? 'per_credit');
                                                    @endphp
                                                    <tr x-data="{ pricingType: @js($initialPricingType) }">
                                                        <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                            {{ $department->name }}
                                                            <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $department->college?->name }}</span>
                                                        </td>
                                                        <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                            <select
                                                                name="pricing_type[{{ $department->id }}][{{ $academicYear->id }}]"
                                                                x-model="pricingType"
                                                                class="w-52 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800"
                                                                @disabled($readOnly)
                                                            >
                                                                <option value="per_credit" @selected($initialPricingType === 'per_credit')>Per-credit</option>
                                                                <option value="flat" @selected($initialPricingType === 'flat')>Flat, not credit-based</option>
                                                            </select>
                                                        </td>
                                                        @foreach($currencies as $currency)
                                                            @php
                                                                $existing = $departmentRates->get($academicYear->id.'|'.$currency);
                                                                $oldValue = old("rates.{$department->id}.{$academicYear->id}.{$currency}");
                                                                $value = $oldValue ?? $existing?->rate_per_credit ?? $existing?->flat_amount;
                                                                $displayValue = $value !== null && $value !== ''
                                                                    ? rtrim(rtrim(number_format((float) $value, 2, '.', ','), '0'), '.')
                                                                    : '';
                                                            @endphp
                                                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">
                                                                <input
                                                                    type="text"
                                                                    inputmode="decimal"
                                                                    name="rates[{{ $department->id }}][{{ $academicYear->id }}][{{ $currency }}]"
                                                                    value="{{ $displayValue }}"
                                                                    x-on:input="formatMoneyInput($event)"
                                                                    :placeholder="pricingType === 'flat' ? 'Full program amount' : 'Rate per credit'"
                                                                    class="money-input w-40 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800"
                                                                    @disabled($readOnly)
                                                                >
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="mt-4 flex justify-end gap-2">
                                        <button type="button" x-on:click="$dispatch('close')" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                                            {{ $readOnly ? 'Close' : 'Cancel' }}
                                        </button>
                                        @unless($readOnly)
                                            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                                                Save Tuition Rates
                                            </button>
                                        @endunless
                                    </div>
                                </form>
                            </div>
                        </x-modal>
                    @endif
                </section>
            @empty
                <div class="rounded-lg border border-gray-200 bg-white p-6 text-center text-sm text-gray-500 shadow-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
                    No active or upcoming academic year is in your scope yet.
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
