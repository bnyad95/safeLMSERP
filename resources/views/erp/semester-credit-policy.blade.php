<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Semester Credit Policy</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Define semester credit load, progression requirements, and graduation requirements for each institution.</p>
            </div>
            <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                Back to Bologna Definition
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950/30 dark:text-emerald-200">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200">
                    <p class="font-semibold">Please review the semester credit values.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <form action="{{ route('bologna-definition.semester-credit-policy.store') }}" method="POST" class="space-y-5 p-5">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Institution</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Type</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">ECTS/Credits Per Semester</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Credits Required To Progress</th>
                                    <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Credits Required To Graduate</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                                @forelse($universities as $university)
                                    @php
                                        $policy = $policies->get($university->id);
                                        $oldPolicy = old("policies.{$university->id}", []);
                                        $semesterCredits = $oldPolicy['semester_credits'] ?? $policy?->semester_credits ?? 30;
                                        $passingCredits = $oldPolicy['passing_credits'] ?? $policy?->passing_credits ?? 18;
                                        $graduationCredits = $oldPolicy['graduation_credits'] ?? $policy?->graduation_credits ?? ($semesterCredits * $university->expectedStageCount() * $university->expectedSemesterCount());
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $university->name }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $university->institution_type === 'institute' ? 'Institute' : 'University' }}</td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                            <input
                                                type="number"
                                                min="1"
                                                max="120"
                                                name="policies[{{ $university->id }}][semester_credits]"
                                                value="{{ $semesterCredits }}"
                                                class="w-36 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800"
                                                @disabled(! $canManageAcademicSetup)
                                                required
                                            >
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                            <input
                                                type="number"
                                                min="1"
                                                max="120"
                                                name="policies[{{ $university->id }}][passing_credits]"
                                                value="{{ $passingCredits }}"
                                                class="w-36 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800"
                                                @disabled(! $canManageAcademicSetup)
                                                required
                                            >
                                        </td>
                                        <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                            <input
                                                type="number"
                                                min="1"
                                                max="2000"
                                                name="policies[{{ $university->id }}][graduation_credits]"
                                                value="{{ $graduationCredits }}"
                                                class="w-36 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:disabled:bg-gray-800"
                                                @disabled(! $canManageAcademicSetup)
                                                required
                                            >
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                            No institutions are available in your scope.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</a>
                        @if($canManageAcademicSetup)
                            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700" @disabled($universities->isEmpty())>
                                Save Semester Credit Policy
                            </button>
                        @endif
                    </div>
                </form>
            </section>
        </div>
    </div>
</x-app-layout>
