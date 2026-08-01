<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500">Final Exam Entry</p>
            <h2 class="mt-1 text-xl font-semibold text-gray-800">{{ $course->code }} - {{ $course->name }}</h2>
            <p class="mt-1 text-sm text-gray-600">Enter first-trial and eligible second-trial scores for this course.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ $course->department?->college?->name ?? 'No college' }} / {{ $course->department?->name ?? 'No department' }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $course->code }} - {{ $course->name }}</h3>
                    </div>
                    <a href="{{ route('marks.final-exam.index', request()->except(['final_exam_page'])) }}" class="inline-flex w-fit rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to courses</a>
                </div>
            </section>

            <section class="grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-blue-800">Waiting first trial</p>
                    <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $finalExamStats['waiting_first_trial'] }}</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="text-sm text-red-800">Eligible second trial</p>
                    <p class="mt-2 text-2xl font-semibold text-red-950">{{ $finalExamStats['waiting_second_trial'] }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm text-emerald-800">Ready for review</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-950">{{ $finalExamStats['ready_for_review'] }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-base font-semibold text-gray-900">
                        Students Waiting for Final Exam Entry
                        <span class="ml-2 rounded-md bg-blue-100 px-2 py-0.5 text-xs text-blue-700">{{ $finalExamDrafts->total() }}</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Academic Scope</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Class</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Pre-final</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">First Trial</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Second Trial</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">Final Mark</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">Entry</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($finalExamDrafts as $mark)
                                @php
                                    $firstTrialTotal = is_null($mark->first_trial_final_exam) ? null : (float) $mark->prefinal_mark + (float) $mark->first_trial_final_exam;
                                    $secondTrialAllowed = ! is_null($firstTrialTotal) && $firstTrialTotal < config('academics.pass_mark', 50);
                                    $activeTrial = is_null($mark->first_trial_final_exam) ? 'first' : ($secondTrialAllowed ? 'second' : null);
                                    $activeScore = $activeTrial === 'first' ? $mark->first_trial_final_exam : ($activeTrial === 'second' ? $mark->second_trial_final_exam : null);
                                    $section = $mark->courseSection;
                                    $department = $section?->course?->department ?? $course->department ?? $mark->student?->department;
                                    $college = $department?->college;
                                @endphp
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ $mark->student->full_name ?? '-' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $mark->student->student_id ?? 'No ID' }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm text-gray-700">{{ $section?->semester?->academic_year ?? 'No year' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $college?->name ?? 'No college' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $department?->name ?? 'No department' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $section?->grade_level ?: 'Stage not specified' }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-medium text-gray-900">{{ $section ? ($section->semester?->name ?? 'Semester').' / Group '.$section->section_code : 'No class linked' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">Teacher: {{ $section?->teacher?->full_name ?? 'Not assigned' }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">{{ number_format((float) $mark->prefinal_mark, 2) }}</td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ is_null($mark->first_trial_final_exam) ? '-' : number_format((float) $mark->first_trial_final_exam, 2) }}</p>
                                        @if(! is_null($firstTrialTotal))
                                            <p class="mt-1 text-xs {{ $firstTrialTotal >= config('academics.pass_mark', 50) ? 'text-emerald-700' : 'text-red-700' }}">{{ $firstTrialTotal >= config('academics.pass_mark', 50) ? 'Passed' : 'Failed' }} first trial</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">{{ is_null($mark->second_trial_final_exam) ? '-' : number_format((float) $mark->second_trial_final_exam, 2) }}</td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900">{{ ($mark->final_mark ?? 0) > 0 ? number_format((float) $mark->final_mark, 2) : '-' }}</td>
                                    <td class="px-4 py-4">
                                        @if($activeTrial)
                                            <form method="POST" action="{{ route('marks.final-exam.store') }}" class="flex min-w-44 flex-col items-end gap-1" data-final-exam-autosave>
                                                @csrf
                                                <input type="hidden" name="mark_id" value="{{ $mark->id }}">
                                                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                                <input type="hidden" name="trial" value="{{ $activeTrial }}">
                                                <label class="text-xs font-semibold {{ $activeTrial === 'second' ? 'text-red-700' : 'text-blue-700' }}">{{ $activeTrial === 'second' ? 'Second trial active' : 'First trial' }}</label>
                                                <input type="number" name="score" min="0" max="{{ config('academics.final_exam_mark_max', 100) }}" step="0.01" required value="{{ is_null($activeScore) ? '' : number_format((float) $activeScore, 2, '.', '') }}" placeholder="{{ $activeTrial === 'second' ? 'Second score' : 'First score' }}" class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500" data-final-score-input>
                                            </form>
                                        @else
                                            <span class="text-xs font-semibold text-emerald-700">First trial passed</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500">No pre-final marks are waiting for final exam entry in this course.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($finalExamDrafts->hasPages())
                    <div class="border-t px-4 py-3">{{ $finalExamDrafts->links() }}</div>
                @endif
            </section>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-final-score-input]').forEach((input) => {
                let submitting = false;
                const submitScore = () => {
                    if (submitting || input.value === '' || ! input.checkValidity()) {
                        return;
                    }

                    submitting = true;
                    input.readOnly = true;
                    input.form.requestSubmit();
                };

                input.addEventListener('change', submitScore);
                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        submitScore();
                    }
                });
            });
        });
    </script>
</x-app-layout>
