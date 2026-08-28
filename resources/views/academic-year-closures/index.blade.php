<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Academic Year Closing</h2>
                <p class="text-sm text-gray-600">Review results, complete rosters, archive old modules, and carry tuition balances forward.</p>
            </div>
            <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Back to Academic Setup
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Please confirm the closing checks.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('academic-year-closures.index') }}" class="grid gap-4 md:grid-cols-[minmax(220px,1fr)_minmax(220px,1fr)_auto] md:items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">University</label>
                        <select name="university_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            @forelse($universities as $university)
                                <option value="{{ $university->id }}" @selected($selectedUniversity?->id === $university->id)>{{ $university->name }}</option>
                            @empty
                                <option value="">No university in scope</option>
                            @endforelse
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Academic Year</label>
                        <select name="academic_year" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            @forelse($academicYears as $year)
                                <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }}</option>
                            @empty
                                <option value="">No academic years found</option>
                            @endforelse
                        </select>
                    </div>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Review Year</button>
                </form>
            </section>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @foreach([
                    ['label' => 'Semesters', 'value' => $summary['semester_count'], 'detail' => 'Periods in scope'],
                    ['label' => 'Modules', 'value' => $summary['section_count'], 'detail' => 'Class modules found'],
                    ['label' => 'Students', 'value' => $summary['student_count'], 'detail' => 'Enrolled students'],
                    ['label' => 'Published Marks', 'value' => $summary['published_marks'], 'detail' => 'Visible result rows'],
                    ['label' => 'Open Invoices', 'value' => $summary['open_finance_invoices'], 'detail' => 'Will be archived with the year'],
                ] as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-xs font-semibold uppercase text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ number_format($stat['value']) }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(320px,0.8fr)]">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Closing Readiness</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            @if($summary['is_closed'])
                                This year already has a closure record for the visible institution scope.
                            @elseif($summary['blockers_count'] > 0)
                                Resolve blockers before closing the academic year.
                            @else
                                Results are ready for closure. Finance balances will stay open for collection.
                            @endif
                        </p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($summary['blockers'] as $blocker)
                            <div class="flex items-start justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-red-800">{{ $blocker['label'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $blocker['detail'] }}</p>
                                </div>
                                <span class="rounded-md bg-red-50 px-3 py-1 text-sm font-semibold text-red-700">{{ number_format($blocker['count']) }}</span>
                            </div>
                        @empty
                            <div class="px-5 py-4 text-sm font-semibold text-emerald-700">No result blockers found.</div>
                        @endforelse

                        @foreach($summary['warnings'] as $warning)
                            <div class="flex items-start justify-between gap-4 px-5 py-4">
                                <div>
                                    <p class="text-sm font-semibold text-amber-800">{{ $warning['label'] }}</p>
                                    <p class="mt-1 text-sm text-gray-500">{{ $warning['detail'] }}</p>
                                </div>
                                <span class="rounded-md bg-amber-50 px-3 py-1 text-sm font-semibold text-amber-700">{{ number_format($warning['count']) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Close Year</h3>
                        <p class="mt-1 text-sm text-gray-500">Closing completes active rosters, clears waitlists, archives old modules, and records an audit snapshot.</p>
                    </div>
                    <div class="space-y-4 p-5">
                        <div class="rounded-md bg-gray-50 p-4 text-sm text-gray-700">
                            <p class="font-semibold text-gray-900">Scope</p>
                            <p class="mt-1">{{ $summary['universities']->join(', ') ?: 'No university in scope' }}</p>
                        </div>
                        @if($summary['open_finance_by_currency']->isNotEmpty())
                            <div class="rounded-md border border-amber-200 bg-amber-50 p-4">
                                <p class="text-sm font-semibold text-amber-900">Open finance archived with this year</p>
                                <div class="mt-2 space-y-1 text-sm text-amber-900">
                                    @foreach($summary['open_finance_by_currency'] as $total)
                                        <p>{{ money($total['total'], $total['currency']) }} {{ $total['currency'] }}</p>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($selectedYear && $canManageClosure)
                            <form method="POST" action="{{ route('academic-year-closures.store') }}" class="space-y-4">
                                @csrf
                                <input type="hidden" name="university_id" value="{{ $selectedUniversity->id }}">
                                <input type="hidden" name="academic_year" value="{{ $selectedYear }}">
                                <label class="flex gap-3 text-sm text-gray-700">
                                    <input type="checkbox" name="confirm_results" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>I confirm all result blockers are resolved and published marks are ready to lock.</span>
                                </label>
                                <label class="flex gap-3 text-sm text-gray-700">
                                    <input type="checkbox" name="confirm_finance" value="1" class="mt-1 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                    <span>I confirm unpaid tuition remains open for finance follow-up after closure.</span>
                                </label>
                                <button
                                    type="submit"
                                    @disabled($summary['blockers_count'] > 0 || $summary['semester_count'] === 0 || ($summary['is_closed'] && $summary['unarchived_section_count'] === 0))
                                    class="w-full rounded-md px-4 py-2 text-sm font-semibold text-white {{ $summary['blockers_count'] > 0 || $summary['semester_count'] === 0 || ($summary['is_closed'] && $summary['unarchived_section_count'] === 0) ? 'cursor-not-allowed bg-gray-400' : 'bg-red-700 hover:bg-red-800' }}"
                                    onclick="return confirm('Close academic year {{ $selectedYear }}? This completes current rosters and archives old modules.')"
                                >
                                    {{ $summary['is_closed'] ? ($summary['unarchived_section_count'] > 0 ? 'Archive Remaining Data' : 'Academic Year Closed') : 'Close Academic Year' }}
                                </button>
                            </form>
                        @elseif(! $canManageClosure)
                            <p class="rounded-md bg-gray-50 p-4 text-sm text-gray-600">You can review and resolve records inside your organization scope, but final closing requires university-level academic authority.</p>
                        @endif
                    </div>
                </section>
            </div>

            @php($readiness = $summary['progression_readiness'])
            @if($readiness['sections_without_stage_count'] > 0)
                <section class="overflow-hidden rounded-lg border border-red-200 bg-white shadow-sm">
                    <div class="border-b border-red-100 bg-red-50 px-5 py-4">
                        <h3 class="text-base font-semibold text-red-900">Modules Requiring a Stage</h3>
                        <p class="mt-1 text-sm text-red-700">These are structural blockers and cannot be bypassed with a student exception.</p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($readiness['sections_without_stage'] as $section)
                            <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $section->course->code ?? 'Module' }} / Group {{ $section->section_code }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $section->semester->name ?? 'Semester' }} {{ $section->semester->academic_year ?? '' }}</p>
                                </div>
                                <a href="{{ route('course-sections.show', $section) }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Assign Stage</a>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900">Student Progression Review</h3>
                            <p class="mt-1 text-sm text-gray-500">Every enrolled student must receive a normal progression decision or an approved exception.</p>
                        </div>
                        <div class="flex flex-wrap gap-2 text-xs font-semibold">
                            <span class="rounded-md bg-red-50 px-3 py-1 text-red-700">{{ number_format($readiness['unresolved_student_count']) }} unresolved</span>
                            <span class="rounded-md bg-emerald-50 px-3 py-1 text-emerald-700">{{ number_format($readiness['approved_exception_count']) }} exceptions</span>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-gray-100">
                    @forelse($readiness['unresolved_students'] as $item)
                        <div class="px-5 py-3" x-data="{ open: false }">
                            <button type="button" @click="open = ! open" class="flex w-full flex-col gap-1 text-left sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-baseline gap-2">
                                    <span class="font-semibold text-gray-900">{{ $item['student']->full_name }}</span>
                                    <span class="text-sm text-gray-500">{{ $item['student']->student_id }} / {{ $item['student']->department->name ?? 'No department' }}</span>
                                </div>
                                <span class="text-sm text-red-700">
                                    {{ $item['issues']->count() }} {{ Str::plural('issue', $item['issues']->count()) }}
                                    <span class="ml-2 text-xs font-semibold text-blue-700" x-show="! open">Show details</span>
                                    <span class="ml-2 text-xs font-semibold text-blue-700" x-show="open" x-cloak>Hide details</span>
                                </span>
                            </button>

                            <div class="mt-3 space-y-3" x-show="open" x-cloak>
                                <p class="text-sm text-red-700">{{ $item['issues']->implode(' · ') }}</p>

                                @if($canResolveClosure)
                                    <div class="grid gap-3 xl:grid-cols-2">
                                        <form method="POST" action="{{ route('academic-year-closures.students.stage', $item['student']) }}" class="grid gap-2 rounded-md bg-gray-50 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                                            @csrf
                                            @method('PATCH')
                                            <input type="hidden" name="university_id" value="{{ $selectedUniversity?->id }}">
                                            <input type="hidden" name="academic_year" value="{{ $selectedYear }}">
                                            <div>
                                                <label class="block text-xs font-semibold uppercase text-gray-500">Current Stage</label>
                                                <select name="stage_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm" required>
                                                    <option value="">Select stage</option>
                                                    @foreach($item['stage_options'] as $stage)
                                                        <option value="{{ $stage->id }}" @selected(($item['inferred_stage']?->id ?? null) === $stage->id)>{{ $stage->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <button class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Assign Stage</button>
                                        </form>

                                        <form method="POST" action="{{ route('academic-year-closures.exceptions.store') }}" class="grid gap-2 rounded-md bg-amber-50 p-3 sm:grid-cols-[160px_minmax(0,1fr)_auto] sm:items-end">
                                            @csrf
                                            <input type="hidden" name="university_id" value="{{ $selectedUniversity?->id }}">
                                            <input type="hidden" name="academic_year" value="{{ $selectedYear }}">
                                            <input type="hidden" name="student_id" value="{{ $item['student']->id }}">
                                            <div>
                                                <label class="block text-xs font-semibold uppercase text-amber-800">Exception</label>
                                                <select name="status" class="mt-1 block w-full rounded-md border-amber-300 text-sm shadow-sm" required>
                                                    <option value="incomplete">Incomplete</option>
                                                    <option value="deferred">Deferred</option>
                                                    <option value="withdrawn">Withdrawn</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs font-semibold uppercase text-amber-800">Required Reason</label>
                                                <input name="reason" minlength="10" maxlength="2000" class="mt-1 block w-full rounded-md border-amber-300 text-sm shadow-sm" placeholder="Document the approved reason" required>
                                            </div>
                                            <button class="rounded-md bg-amber-700 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-800">Approve</button>
                                        </form>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="px-5 py-8 text-center text-sm font-semibold text-emerald-700">Every enrolled student has a valid progression path or approved exception.</div>
                    @endforelse
                </div>

                @if($readiness['unresolved_students_truncated'])
                    <div class="border-t border-gray-100 bg-gray-50 px-5 py-3 text-sm text-gray-600">Showing the first 100 unresolved students. Resolve these records and review the year again for the remaining students.</div>
                @endif
            </section>

            @if($readiness['approved_exceptions']->isNotEmpty())
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Approved Progression Exceptions</h3>
                        <p class="mt-1 text-sm text-gray-500">These decisions are audited and applied when the academic year closes.</p>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($readiness['approved_exceptions'] as $exception)
                            <div class="flex flex-col gap-3 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $exception->student->full_name ?? 'Student' }} / {{ str($exception->status)->title() }}</p>
                                    <p class="mt-1 text-sm text-gray-600">{{ $exception->reason }}</p>
                                    <p class="mt-1 text-xs text-gray-500">Approved by {{ $exception->approvedBy->name ?? 'Unknown user' }} on {{ $exception->approved_at?->format('Y-m-d H:i') }}</p>
                                </div>
                                @if($canResolveClosure)
                                    <form method="POST" action="{{ route('academic-year-closures.exceptions.destroy', $exception) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="rounded-md border border-red-300 px-3 py-2 text-sm font-semibold text-red-700 hover:bg-red-50" onclick="return confirm('Remove this exception?')">Remove Exception</button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Modules In Scope</h3>
                    <p class="mt-1 text-sm text-gray-500">Recent modules that are included in this academic year. Closed years keep these modules archived for history.</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Module</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">College / Department</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Semester</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Students</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Marks</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white">
                            @forelse($summary['recent_sections'] as $section)
                                <tr>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ $section->course->code ?? 'Course' }} / Group {{ $section->section_code }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">
                                        <p>{{ $section->course->department->college->name ?? 'No college' }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $section->course->department->name ?? 'No department' }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $section->semester->name ?? 'Semester' }} {{ $section->semester->academic_year ?? '' }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $section->active_enrollments_count }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $section->marks_count }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">
                                        {{ $section->trashed() ? 'Archived' : ucfirst($section->status) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500">No modules found for this academic year.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            @if($summary['closures']->isNotEmpty())
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Closure History</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @foreach($summary['closures'] as $closure)
                            <div class="flex flex-col gap-1 px-5 py-4 text-sm text-gray-600 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="font-semibold text-gray-900">{{ $closure->university->name ?? 'University' }} / {{ $closure->academic_year }}</p>
                                    <p class="mt-1">Closed by {{ $closure->closedBy->name ?? 'Unknown user' }}</p>
                                </div>
                                <p>{{ $closure->closed_at?->format('Y-m-d H:i') }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
