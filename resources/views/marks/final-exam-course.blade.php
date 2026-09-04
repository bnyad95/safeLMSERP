<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Final Exam Entry') }}</p>
            <h2 class="mt-1 text-xl font-semibold text-gray-800 dark:text-gray-100">{{ $course->code }} - {{ $course->name }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $canEnterFinalExam ? __('Enter first-trial and eligible second-trial scores for this course.') : __('Correct mistaken published scores for this course.') }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-200">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $course->department?->college?->name ?? __('No college') }} / {{ $course->department?->name ?? __('No department') }}</p>
                        <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $course->code }} - {{ $course->name }}</h3>
                    </div>
                    <a href="{{ route('marks.final-exam.index', request()->except(['final_exam_page'])) }}" class="inline-flex w-fit rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Back to courses') }}</a>
                </div>
            </section>

            @if($canEnterFinalExam)
                <section class="grid gap-3 md:grid-cols-3">
                    <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
                        <p class="text-sm text-blue-800 dark:text-blue-200">{{ __('Waiting first trial') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-950 dark:text-blue-100">{{ $finalExamStats['waiting_first_trial'] }}</p>
                    </div>
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-800 dark:bg-red-900/20">
                        <p class="text-sm text-red-800 dark:text-red-200">{{ __('Eligible second trial') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-red-950 dark:text-red-100">{{ $finalExamStats['waiting_second_trial'] }}</p>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-800 dark:bg-emerald-900/20">
                        <p class="text-sm text-emerald-800 dark:text-emerald-200">{{ __('Ready for review') }}</p>
                        <p class="mt-2 text-2xl font-semibold text-emerald-950 dark:text-emerald-100">{{ $finalExamStats['ready_for_review'] }}</p>
                    </div>
                </section>
            @endif

            @if($canEnterFinalExam)
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Students Waiting for Final Exam Entry') }}
                        <span class="ml-2 rounded-md bg-blue-100 px-2 py-0.5 text-xs text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">{{ $finalExamDrafts->total() }}</span>
                    </h3>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Student') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Academic Scope') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Class') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Pre-final') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('First Trial') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Second Trial') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Final Mark') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Entry') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($finalExamDrafts as $mark)
                                @php
                                    $firstTrialTotal = is_null($mark->first_trial_final_exam) ? null : (float) $mark->prefinal_mark + (float) $mark->first_trial_final_exam;
                                    $secondTrialAllowed = ! is_null($firstTrialTotal) && $firstTrialTotal < config('academics.pass_mark', 50);
                                    $activeTrial = $mark->submission_status === 'rejected'
                                        ? (! is_null($mark->second_trial_final_exam) ? 'second' : 'first')
                                        : (is_null($mark->first_trial_final_exam) ? 'first' : ($secondTrialAllowed ? 'second' : null));
                                    $activeScore = $activeTrial === 'first' ? $mark->first_trial_final_exam : ($activeTrial === 'second' ? $mark->second_trial_final_exam : null);
                                    $section = $mark->courseSection;
                                    $department = $section?->course?->department ?? $course->department ?? $mark->student?->department;
                                    $college = $department?->college;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $mark->student->full_name ?? '-' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $mark->student->student_id ?? __('No ID') }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm text-gray-700 dark:text-gray-300">{{ $section?->semester?->academic_year ?? __('No year') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $college?->name ?? __('No college') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $department?->name ?? __('No department') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $section?->grade_level ?: __('Stage not specified') }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $section ? ($section->semester?->name ?? __('Semester')).' / '.__('Group :code', ['code' => $section->section_code]) : __('No class linked') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Teacher') }}: {{ $section?->teacher?->full_name ?? __('Not assigned') }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $mark->prefinal_mark, 2) }}</td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ is_null($mark->first_trial_final_exam) ? '-' : number_format((float) $mark->first_trial_final_exam, 2) }}</p>
                                        @if(! is_null($firstTrialTotal))
                                            <p class="mt-1 text-xs {{ $firstTrialTotal >= config('academics.pass_mark', 50) ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">{{ $firstTrialTotal >= config('academics.pass_mark', 50) ? __('Passed first trial') : __('Failed first trial') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ is_null($mark->second_trial_final_exam) ? '-' : number_format((float) $mark->second_trial_final_exam, 2) }}</td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ ($mark->final_mark ?? 0) > 0 ? number_format((float) $mark->final_mark, 2) : '-' }}</td>
                                    <td class="px-4 py-4">
                                        @if($activeTrial)
                                            <form method="POST" action="{{ route('marks.final-exam.store') }}" class="flex min-w-44 flex-col items-end gap-1" data-final-exam-autosave>
                                                @csrf
                                                <input type="hidden" name="mark_id" value="{{ $mark->id }}">
                                                <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                                <input type="hidden" name="trial" value="{{ $activeTrial }}">
                                                <label class="text-xs font-semibold {{ $activeTrial === 'second' ? 'text-red-700 dark:text-red-300' : 'text-blue-700 dark:text-blue-300' }}">{{ $activeTrial === 'second' ? __('Second trial active') : __('First trial') }}</label>
                                                <input type="number" name="score" min="0" max="{{ config('academics.final_exam_mark_max', 100) }}" step="0.01" required value="{{ is_null($activeScore) ? '' : number_format((float) $activeScore, 2, '.', '') }}" placeholder="{{ $activeTrial === 'second' ? __('Second score') : __('First score') }}" class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100" data-final-score-input>
                                                @if($mark->submission_status === 'rejected' && ! is_null($activeScore))
                                                    <input type="text" name="change_reason" required maxlength="1000" placeholder="{{ __('Correction reason') }}" class="w-44 rounded-md border-gray-300 text-xs shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                                @endif
                                            </form>
                                        @else
                                            <span class="text-xs font-semibold text-emerald-700 dark:text-emerald-300">{{ __('First trial passed') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No pre-final marks are waiting for final exam entry in this course.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($finalExamDrafts->hasPages())
                    <div class="border-t px-4 py-3 dark:border-gray-800">{{ $finalExamDrafts->links() }}</div>
                @endif
            </section>
            @endif

            @if($canCorrectFinalExam)
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-100 px-6 py-4 dark:border-gray-800">
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ __('Published Marks — Corrections') }}
                        <span class="ml-2 rounded-md bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-900/30 dark:text-amber-200">{{ $publishedMarks->total() }}</span>
                    </h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ __('Found a mistake after publishing? Enter the corrected score and a reason — it republishes immediately.') }}</p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-950">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Student') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Class') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Published Final Mark') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">{{ __('Correct Score') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse($publishedMarks as $mark)
                                @php
                                    $correctionTrial = is_null($mark->second_trial_final_exam) ? 'first' : 'second';
                                    $correctionScore = $correctionTrial === 'first' ? $mark->first_trial_final_exam : $mark->second_trial_final_exam;
                                    $section = $mark->courseSection;
                                @endphp
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/60">
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $mark->student->full_name ?? '-' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $mark->student->student_id ?? __('No ID') }}</p>
                                    </td>
                                    <td class="px-4 py-4">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $section ? ($section->semester?->name ?? __('Semester')).' / '.__('Group :code', ['code' => $section->section_code]) : __('No class linked') }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ __('Published :date', ['date' => $mark->published_at?->format('Y-m-d') ?? __('unknown date')]) }}</p>
                                    </td>
                                    <td class="px-4 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $mark->final_mark, 2) }}</td>
                                    <td class="px-4 py-4">
                                        <form method="POST" action="{{ route('marks.final-exam.store') }}" class="flex min-w-56 flex-col items-end gap-1.5">
                                            @csrf
                                            <input type="hidden" name="mark_id" value="{{ $mark->id }}">
                                            <input type="hidden" name="redirect_to" value="{{ request()->fullUrl() }}">
                                            <input type="hidden" name="trial" value="{{ $correctionTrial }}">
                                            <label class="text-xs font-semibold text-amber-700 dark:text-amber-300">{{ $correctionTrial === 'second' ? __('Second trial score') : __('First trial score') }}</label>
                                            <input type="number" name="score" min="0" max="{{ config('academics.final_exam_mark_max', 100) }}" step="0.01" required value="{{ is_null($correctionScore) ? '' : number_format((float) $correctionScore, 2, '.', '') }}" class="w-28 rounded-md border-gray-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                                            <textarea name="change_reason" required maxlength="1000" rows="2" placeholder="{{ __('Reason for correcting this published mark') }}" class="w-56 rounded-md border-gray-300 text-xs shadow-sm focus:border-amber-500 focus:ring-amber-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100"></textarea>
                                            <button type="submit" class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-700">{{ __('Save Correction') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">{{ __('No published marks in this course.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if($publishedMarks->hasPages())
                    <div class="border-t px-4 py-3 dark:border-gray-800">{{ $publishedMarks->links() }}</div>
                @endif
            </section>
            @endif
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
