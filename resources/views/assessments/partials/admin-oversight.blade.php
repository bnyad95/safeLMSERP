@php
    $selectedCollege = $adminHierarchy['selected_college'];
    $selectedDepartment = $adminHierarchy['selected_department'];
    $selectedGrade = $adminHierarchy['selected_grade'];
    $selectedSection = $adminHierarchy['selected_section'];
    $collegeUrl = fn ($college) => route('assessments.index', ['college_id' => $college->id]);
    $departmentUrl = fn ($department) => route('assessments.index', ['college_id' => $selectedCollege?->id, 'department_id' => $department->id]);
    $gradeUrl = fn ($grade) => route('assessments.index', ['college_id' => $selectedCollege?->id, 'department_id' => $selectedDepartment?->id, 'stage' => $grade['key']]);
    $classUrl = fn ($section) => route('assessments.index', ['college_id' => $selectedCollege?->id, 'department_id' => $selectedDepartment?->id, 'stage' => $selectedGrade['key'] ?? null, 'section_id' => $section->id]);
    $hierarchyParams = array_filter([
        'college_id' => $selectedCollege?->id,
        'department_id' => $selectedDepartment?->id,
        'stage' => $selectedGrade['key'] ?? null,
        'section_id' => $selectedSection?->id,
    ], fn ($value) => ! blank($value));
    $filterParams = collect($adminFilters)->filter(fn ($value) => ! blank($value))->all();
    $hasAdminFilters = count($filterParams) > 0;
    $resetUrl = route('assessments.index', $hierarchyParams);
    $exportUrl = route('assessments.export', request()->query());
    $assessmentDrillUrl = function ($item) use ($filterParams) {
        $section = $item->courseSection;

        return route('assessments.index', array_merge($filterParams, [
            'college_id' => $section->course?->department?->college_id,
            'department_id' => $section->course?->department_id,
            'stage' => $section->grade_level ?: '__unassigned__',
            'section_id' => $section->id,
            'assessment_id' => $item->id,
        ]));
    };

    $assessmentCrumbs = collect([
        ['label' => 'Assessment Analytics', 'href' => route('assessments.index')],
        $selectedCollege ? ['label' => $selectedCollege->name, 'href' => $collegeUrl($selectedCollege)] : null,
        $selectedDepartment ? ['label' => $selectedDepartment->name, 'href' => $departmentUrl($selectedDepartment)] : null,
        $selectedGrade ? ['label' => $selectedGrade['label'], 'href' => $gradeUrl($selectedGrade)] : null,
        $selectedSection ? ['label' => $selectedSection->course->code.' Group '.$selectedSection->section_code, 'href' => null] : null,
    ])->filter()->values();
    $assessmentCrumbs = $assessmentCrumbs->map(fn ($crumb, $index) => $index === $assessmentCrumbs->count() - 1
        ? [...$crumb, 'href' => null]
        : $crumb);
@endphp

<nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Assessment hierarchy">
    @foreach($assessmentCrumbs as $crumb)
        @if(! $loop->first)<span class="text-gray-400">/</span>@endif
        @if($crumb['href'])
            <a href="{{ $crumb['href'] }}" class="font-semibold text-blue-700 hover:underline">{{ $crumb['label'] }}</a>
        @else
            <span class="font-semibold text-gray-700">{{ $crumb['label'] }}</span>
        @endif
    @endforeach
</nav>

@php
    $statusTotal = max($oversightStats['total'], 1);
    $submissionRate = $oversightStats['submission_rate'];
    $gradingRate = $oversightStats['grading_rate'];
    $largestType = max((int) $oversightAnalytics['types']->max('total'), 1);
@endphp

@php
    $advancedAssessmentFilterActive = collect([
        $adminFilters['semester_id'], $adminFilters['teacher_id'], $adminFilters['type'],
        $adminFilters['due_from'], $adminFilters['due_until'],
    ])->filter(fn ($value) => ! blank($value))->isNotEmpty();
@endphp

<section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm" x-data="{ showMore: {{ $advancedAssessmentFilterActive ? 'true' : 'false' }} }">
    <form method="GET" action="{{ route('assessments.index') }}" class="grid gap-4 lg:grid-cols-6">
        @foreach($hierarchyParams as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach

        <div class="lg:col-span-2">
            <label for="assessment-search" class="block text-sm font-medium text-gray-700">Search</label>
            <input id="assessment-search" name="q" value="{{ $adminFilters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Title or type">
        </div>

        <div>
            <label for="assessment-course" class="block text-sm font-medium text-gray-700">Course</label>
            <select id="assessment-course" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All courses</option>
                @foreach($adminFilterOptions['courses'] as $course)
                    <option value="{{ $course->id }}" @selected($adminFilters['course_id'] === $course->id)>{{ $course->code }} - {{ $course->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="assessment-status" class="block text-sm font-medium text-gray-700">Status</label>
            <select id="assessment-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All statuses</option>
                @foreach(['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed'] as $value => $label)
                    <option value="{{ $value }}" @selected($adminFilters['status'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>

        <div class="flex items-end justify-end lg:col-span-2">
            <button type="button" @click="showMore = ! showMore" class="text-sm font-semibold text-blue-700 hover:underline">
                <span x-show="! showMore">More filters</span>
                <span x-show="showMore" x-cloak>Fewer filters</span>
            </button>
        </div>

        <div class="lg:col-span-6" x-show="showMore" x-transition x-cloak>
            <div class="grid gap-4 border-t border-gray-100 pt-4 lg:grid-cols-5">
                <div>
                    <label for="assessment-semester" class="block text-sm font-medium text-gray-700">Semester</label>
                    <select id="assessment-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All semesters</option>
                        @foreach($adminFilterOptions['semesters'] as $semester)
                            <option value="{{ $semester->id }}" @selected($adminFilters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assessment-teacher" class="block text-sm font-medium text-gray-700">Teacher</label>
                    <select id="assessment-teacher" name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All teachers</option>
                        @foreach($adminFilterOptions['teachers'] as $teacher)
                            <option value="{{ $teacher->id }}" @selected($adminFilters['teacher_id'] === $teacher->id)>{{ $teacher->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assessment-type" class="block text-sm font-medium text-gray-700">Type</label>
                    <select id="assessment-type" name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All types</option>
                        @foreach($adminFilterOptions['types'] as $type)
                            <option value="{{ $type }}" @selected($adminFilters['type'] === $type)>{{ ucfirst($type) }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="assessment-due-from" class="block text-sm font-medium text-gray-700">Due from</label>
                    <input id="assessment-due-from" type="date" name="due_from" value="{{ $adminFilters['due_from'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>

                <div>
                    <label for="assessment-due-until" class="block text-sm font-medium text-gray-700">Due until</label>
                    <input id="assessment-due-until" type="date" name="due_until" value="{{ $adminFilters['due_until'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-3 lg:col-span-6">
            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
            <a href="{{ $resetUrl }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
            <a href="{{ $exportUrl }}" class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200 dark:hover:bg-emerald-900/50">Export CSV</a>
            @if($hasAdminFilters)
                <span class="text-sm text-gray-500">Filters applied</span>
            @endif
        </div>
    </form>
</section>

<section aria-label="Assessment analytics summary" class="space-y-4">
    @if($oversightStats['total'] === 0 && $hasAdminFilters)
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            No assessments match the current filters in this scope.
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
            <p class="text-sm text-gray-500">Total assessments</p>
            <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $oversightStats['total'] }}</p>
            <p class="mt-1 text-xs text-gray-500">Across the current academic scope</p>
        </div>
        <div class="rounded-lg border border-cyan-200 bg-cyan-50 p-4 dark:border-cyan-800 dark:bg-cyan-950/30">
            <p class="text-sm text-cyan-800 dark:text-cyan-300">Submission rate</p>
            <p class="mt-2 text-2xl font-semibold text-cyan-950 dark:text-cyan-100">{{ is_null($submissionRate) ? 'N/A' : number_format($submissionRate, 1).'%' }}</p>
            <p class="mt-1 text-xs text-cyan-800 dark:text-cyan-300">{{ $oversightStats['submitted'] }} submissions received</p>
        </div>
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
            <p class="text-sm text-emerald-800">Grading rate</p>
            <p class="mt-2 text-2xl font-semibold text-emerald-950">{{ is_null($gradingRate) ? 'N/A' : number_format($gradingRate, 1).'%' }}</p>
            <p class="mt-1 text-xs text-emerald-800">{{ $oversightStats['graded'] }} submissions graded</p>
        </div>
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
            <p class="text-sm text-amber-800">Needs attention</p>
            <p class="mt-2 text-2xl font-semibold text-amber-950">{{ $oversightStats['ungraded'] + $oversightStats['overdue'] }}</p>
            <p class="mt-1 text-xs text-amber-800">{{ $oversightStats['ungraded'] }} ungraded, {{ $oversightStats['overdue'] }} past due</p>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-5">
                <h3 class="font-semibold text-gray-900">Status distribution</h3>
                <p class="mt-1 text-sm text-gray-500">Publication state of assessments</p>
            </div>
            <div class="space-y-4">
                @foreach([
                    ['label' => 'Published', 'value' => $oversightStats['published'], 'bar' => 'bg-emerald-500'],
                    ['label' => 'Draft', 'value' => $oversightStats['draft'], 'bar' => 'bg-gray-500'],
                    ['label' => 'Closed', 'value' => $oversightStats['closed'], 'bar' => 'bg-blue-500'],
                ] as $status)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-sm"><span class="text-gray-600">{{ $status['label'] }}</span><span class="font-semibold text-gray-900">{{ $status['value'] }}</span></div>
                        <div class="h-2 overflow-hidden rounded bg-gray-100"><div class="h-full {{ $status['bar'] }}" style="width: {{ min(($status['value'] / $statusTotal) * 100, 100) }}%"></div></div>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-5">
                <h3 class="font-semibold text-gray-900">Submission pipeline</h3>
                <p class="mt-1 text-sm text-gray-500">Progress from submission to grading</p>
            </div>
            <div class="grid grid-cols-3 divide-x divide-gray-100 text-center">
                <div><p class="text-xl font-semibold text-gray-900">{{ $oversightStats['submitted'] }}</p><p class="mt-1 text-xs text-gray-500">Submitted</p></div>
                <div><p class="text-xl font-semibold text-emerald-700">{{ $oversightStats['graded'] }}</p><p class="mt-1 text-xs text-gray-500">Graded</p></div>
                <div><p class="text-xl font-semibold text-amber-700">{{ $oversightStats['ungraded'] }}</p><p class="mt-1 text-xs text-gray-500">Ungraded</p></div>
            </div>
            <div class="mt-6 space-y-4">
                <div><div class="mb-1.5 flex justify-between text-xs text-gray-600"><span>Submitted</span><span>{{ is_null($submissionRate) ? 'N/A' : number_format($submissionRate, 1).'%' }}</span></div><div class="h-2 overflow-hidden rounded bg-gray-100"><div class="h-full bg-cyan-500" style="width: {{ $submissionRate ?? 0 }}%"></div></div></div>
                <div><div class="mb-1.5 flex justify-between text-xs text-gray-600"><span>Graded</span><span>{{ is_null($gradingRate) ? 'N/A' : number_format($gradingRate, 1).'%' }}</span></div><div class="h-2 overflow-hidden rounded bg-gray-100"><div class="h-full bg-emerald-500" style="width: {{ $gradingRate ?? 0 }}%"></div></div></div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <div class="mb-5">
                <h3 class="font-semibold text-gray-900">Assessment mix</h3>
                <p class="mt-1 text-sm text-gray-500">Items by assessment type</p>
            </div>
            <div class="space-y-4">
                @forelse($oversightAnalytics['types'] as $type)
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-sm"><span class="capitalize text-gray-600">{{ $type->type }}</span><span class="font-semibold text-gray-900">{{ $type->total }}</span></div>
                        <div class="h-2 overflow-hidden rounded bg-gray-100"><div class="h-full bg-violet-500" style="width: {{ min(((int) $type->total / $largestType) * 100, 100) }}%"></div></div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-gray-500">No assessment types to analyze.</p>
                @endforelse
            </div>
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-2">
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h3 class="font-semibold text-gray-900">Needs attention</h3><p class="mt-1 text-sm text-gray-500">Overdue assessments and pending grading</p></div>
            <div class="divide-y divide-gray-100">
                @forelse($oversightAnalytics['risk_items'] as $item)
                    @php $isPastDue = $item->status === 'published' && $item->due_at?->isPast(); @endphp
                    <a href="{{ $assessmentDrillUrl($item) }}" class="flex flex-col gap-2 px-5 py-4 hover:bg-gray-50 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900">{{ $item->title }}</p><p class="mt-1 text-xs text-gray-500">{{ $item->courseSection->course->code ?? 'Course' }} - Group {{ $item->courseSection->section_code }}</p></div>
                        <div class="flex shrink-0 flex-wrap gap-2">
                            @if($isPastDue)<span class="rounded-md bg-red-50 px-2 py-1 text-xs font-semibold text-red-700">Past due</span>@endif
                            @if($item->ungraded_submissions_count)<span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-800">{{ $item->ungraded_submissions_count }} ungraded</span>@endif
                        </div>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-500">Nothing needs attention in this scope.</p>
                @endforelse
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 px-5 py-4"><h3 class="font-semibold text-gray-900">Upcoming deadlines</h3><p class="mt-1 text-sm text-gray-500">Published assessments due in the next seven days</p></div>
            <div class="divide-y divide-gray-100">
                @forelse($oversightAnalytics['upcoming'] as $item)
                    <a href="{{ $assessmentDrillUrl($item) }}" class="flex items-center justify-between gap-4 px-5 py-4 hover:bg-gray-50">
                        <div class="min-w-0"><p class="truncate text-sm font-semibold text-gray-900">{{ $item->title }}</p><p class="mt-1 text-xs text-gray-500">{{ $item->courseSection->course->code ?? 'Course' }} - Group {{ $item->courseSection->section_code }} - {{ $item->submissions_count }} submitted</p></div>
                        <time datetime="{{ $item->due_at?->toIso8601String() }}" class="shrink-0 text-right text-xs font-semibold text-blue-700">{{ $item->due_at?->format('M j') }}<span class="mt-0.5 block font-normal text-gray-500">{{ $item->due_at?->format('g:i A') }}</span></time>
                    </a>
                @empty
                    <p class="px-5 py-10 text-center text-sm text-gray-500">No deadlines in the next seven days.</p>
                @endforelse
            </div>
        </div>
    </div>
</section>

<div class="border-t border-gray-200 pt-6">
    <h3 class="text-lg font-semibold text-gray-900">Browse assessments</h3>
    <p class="mt-1 text-sm text-gray-500">Narrow the analytics by college, department, stage, and class.</p>
</div>

@if(! $selectedCollege)
    <section>
        <div class="mb-4"><h3 class="text-lg font-semibold text-gray-900">Colleges</h3><p class="mt-1 text-sm text-gray-500">Select a college to review its assessments.</p></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($adminHierarchy['colleges'] as $card)
                <a href="{{ $collegeUrl($card['college']) }}" class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase text-emerald-700">College</p><h4 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-emerald-800">{{ $card['college']->name }}</h4><p class="mt-1 text-sm text-gray-500">{{ $card['college']->code }}</p>
                    <p class="mt-5 border-t border-gray-100 pt-4 text-sm text-gray-600">{{ $card['department_count'] }} {{ Str::plural('department', $card['department_count']) }} - {{ $card['assessment_count'] }} {{ Str::plural('assessment', $card['assessment_count']) }}</p>
                </a>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">No colleges with classes are available in your academic scope.</div>
            @endforelse
        </div>
    </section>
@elseif(! $selectedDepartment)
    <section>
        <div class="mb-4"><h3 class="text-lg font-semibold text-gray-900">Departments</h3><p class="mt-1 text-sm text-gray-500">Select a department in {{ $selectedCollege->name }}.</p></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($adminHierarchy['departments'] as $card)
                <a href="{{ $departmentUrl($card['department']) }}" class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase text-blue-700">Department</p><h4 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-blue-800">{{ $card['department']->name }}</h4><p class="mt-1 text-sm text-gray-500">{{ $card['department']->code }}</p>
                    <p class="mt-5 border-t border-gray-100 pt-4 text-sm text-gray-600">{{ $card['grade_count'] }} {{ Str::plural('stage', $card['grade_count']) }} - {{ $card['assessment_count'] }} {{ Str::plural('assessment', $card['assessment_count']) }}</p>
                </a>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">No departments match the selected scope and filters.</div>
            @endforelse
        </div>
    </section>
@elseif(! $selectedGrade)
    <section>
        <div class="mb-4"><h3 class="text-lg font-semibold text-gray-900">Stages</h3><p class="mt-1 text-sm text-gray-500">Select a stage in {{ $selectedDepartment->name }}.</p></div>
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @forelse($adminHierarchy['grades'] as $card)
                <a href="{{ $gradeUrl($card) }}" class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md">
                    <p class="text-xs font-semibold uppercase text-amber-700">Stage</p><h4 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-amber-800">{{ $card['label'] }}</h4>
                    <div class="mt-5 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm"><div><p class="text-xs text-gray-500">Classes</p><p class="mt-1 font-semibold text-gray-900">{{ $card['class_count'] }}</p></div><div><p class="text-xs text-gray-500">Assessments</p><p class="mt-1 font-semibold text-gray-900">{{ $card['assessment_count'] }}</p></div></div>
                </a>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">No stages match the selected scope and filters.</div>
            @endforelse
        </div>
    </section>
@elseif(! $selectedSection)
    <section>
        <div class="mb-4"><h3 class="text-lg font-semibold text-gray-900">Classes</h3><p class="mt-1 text-sm text-gray-500">Select a class to inspect its assessments.</p></div>
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @forelse($adminHierarchy['classes'] as $section)
                <a href="{{ $classUrl($section) }}" class="group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                    <div class="min-h-28 bg-blue-700 p-5 text-white transition group-hover:bg-blue-800"><p class="text-lg font-semibold">{{ $section->course->name }}</p><p class="mt-1 text-sm text-blue-100">{{ $section->course->code }} - Group {{ $section->section_code }}</p></div>
                    <div class="grid grid-cols-2 divide-x divide-gray-100 px-4 py-4 text-sm"><div><p class="text-xs text-gray-500">Students</p><p class="mt-1 font-semibold text-gray-900">{{ $section->active_enrollments_count }}</p></div><div class="pl-4"><p class="text-xs text-gray-500">Assessments</p><p class="mt-1 font-semibold text-gray-900">{{ $section->filtered_assessment_items_count ?? $section->assessment_items_count }}</p></div></div>
                    <p class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500">Teacher: {{ $section->teacher->full_name ?? 'Not assigned' }}</p>
                </a>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">No classes match this stage and filter set.</div>
            @endforelse
        </div>
    </section>
@else
    <section>
        <div class="flex flex-col gap-4 border-b border-gray-200 pb-5 md:flex-row md:items-end md:justify-between">
            <div><p class="text-sm font-semibold text-blue-700">{{ $selectedGrade['label'] }}</p><h3 class="mt-1 text-lg font-semibold text-gray-900">{{ $selectedSection->course->name }} - Group {{ $selectedSection->section_code }}</h3><p class="mt-1 text-sm text-gray-500">Class assessment analytics and read-only assessment details.</p></div>
            <a href="{{ $gradeUrl($selectedGrade) }}" class="text-sm font-semibold text-blue-700 hover:underline">Back to classes</a>
        </div>

        <div class="mt-5 space-y-4">
            @forelse($assessmentItems as $item)
                <details id="assessment-{{ $item->id }}" class="group rounded-lg border border-gray-200 bg-white shadow-sm" @if($selectedAssessmentId === $item->id) open @endif>
                    <summary class="flex cursor-pointer list-none flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
                        <div><div class="flex flex-wrap items-center gap-2"><h4 class="text-base font-semibold text-gray-900">{{ $item->title }}</h4><span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-700">{{ $item->type }}</span><span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $item->weight_percent }}%</span></div><p class="mt-1 text-sm text-gray-500">{{ ucfirst($item->status) }} - {{ $item->submissions_count }} submitted - {{ $item->graded_submissions_count }} graded</p></div>
                        <span class="text-sm font-semibold text-blue-700"><span class="group-open:hidden">Open details</span><span class="hidden group-open:inline">Close details</span></span>
                    </summary>
                    <div class="border-t border-gray-100">@include('assessments.partials.item', ['item' => $item, 'compactHeader' => true, 'readOnlyOversight' => true])</div>
                </details>
            @empty
                <div class="rounded-lg border border-dashed border-gray-300 bg-white px-5 py-12 text-center text-sm text-gray-500">No assessments match this class and filter.</div>
            @endforelse
        </div>
        @if($assessmentItems->hasPages())<div class="mt-5">{{ $assessmentItems->links() }}</div>@endif
    </section>
@endif
