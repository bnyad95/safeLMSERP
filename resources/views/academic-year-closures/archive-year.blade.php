<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Academic Year Archive</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $academicYear }} archived modules grouped by college, department, stage, and semester.</p>
            </div>
            <a href="{{ route('academic-year-closures.archive') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">
                Back to Archive
            </a>
        </div>
    </x-slot>

    <div id="archive-year-page" data-archive-year-page="1" class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Academic Year</p>
                    <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ $academicYear }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Modules</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($summaryStats['modules']) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Enrollments</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($summaryStats['enrollments']) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Marks</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($summaryStats['marks']) }}</p>
                </div>
            </section>

            @if($usingSnapshotFallback)
                <section class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-100">
                    The original semester/module records for this year are no longer available. Showing saved closure totals and legacy published marks that are not linked to a module.
                </section>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <form method="GET" action="{{ route('academic-year-closures.archive.show') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-8 xl:items-end">
                    <input type="hidden" name="academic_year" value="{{ $academicYear }}">
                    <input type="hidden" name="students" value="1">
                    <div class="xl:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search results</label>
                        <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="Student, ID, module, group" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100 dark:placeholder-gray-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                        <select name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All colleges</option>
                            @foreach($colleges as $college)
                                <option value="{{ $college->id }}" @selected((string) $filters['college_id'] === (string) $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All departments</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" @selected((string) $filters['department_id'] === (string) $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label>
                        <select name="stage" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All stages</option>
                            @foreach($stages as $stage)
                                <option value="{{ $stage }}" @selected($filters['stage'] === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                        <select name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All semesters</option>
                            @foreach($semesters as $semester)
                                <option value="{{ $semester->id }}" @selected((string) $filters['semester_id'] === (string) $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Result</label>
                        <select name="result_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="">All results</option>
                            <option value="passed" @selected($filters['result_status'] === 'passed')>Passed</option>
                            <option value="failed" @selected($filters['result_status'] === 'failed')>Failed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Sort results</label>
                        <select name="sort" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-100">
                            <option value="final_desc" @selected($filters['sort'] === 'final_desc')>Highest average first</option>
                            <option value="final_asc" @selected($filters['sort'] === 'final_asc')>Lowest average first</option>
                            <option value="student_asc" @selected($filters['sort'] === 'student_asc')>Student A-Z</option>
                            <option value="student_desc" @selected($filters['sort'] === 'student_desc')>Student Z-A</option>
                            <option value="course_asc" @selected($filters['sort'] === 'course_asc')>Module A-Z</option>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 dark:bg-indigo-600 dark:hover:bg-indigo-500">Apply</button>
                        <a href="{{ route('academic-year-closures.archive.show', ['academic_year' => $academicYear]) }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">Reset</a>
                        <a href="{{ route('academic-year-closures.archive.export', request()->query()) }}" class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 dark:border-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200 dark:hover:bg-emerald-900/50">Export</a>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Passed and Failed Students</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">One row per student. Apply filters when you need the detailed marks.</p>
                        </div>
                        @if($loadStudents)
                            <div class="flex flex-wrap gap-2">
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ number_format($studentResultStats['students']) }} students</span>
                                <span class="rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200">{{ number_format($studentResultStats['passed']) }} passed students</span>
                                <span class="rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 dark:bg-red-900/30 dark:text-red-200">{{ number_format($studentResultStats['failed']) }} failed students</span>
                            </div>
                        @endif
                    </div>
                </div>
                @if($loadStudents)
                    <div class="overflow-x-auto">
                        <table class="min-w-[980px] divide-y divide-gray-100 dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-950">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Student</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">College / Department</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Modules</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Average</th>
                                    <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Failed</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Result</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse($studentResultRows as $studentResult)
                                        @php $passed = $studentResult['result'] === 'Passed'; @endphp
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/70">
                                            <td class="px-5 py-4">
                                                <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $studentResult['student_name'] }}</p>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $studentResult['student_number'] ?? '-' }}</p>
                                            </td>
                                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                                <p>{{ $studentResult['college'] }}</p>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $studentResult['department'] }}</p>
                                            </td>
                                            <td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">
                                                <p class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($studentResult['modules_count']) }} modules</p>
                                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($studentResult['passed_modules']) }} passed / {{ number_format($studentResult['failed_modules']) }} failed</p>
                                                <details class="mt-2">
                                                    <summary class="cursor-pointer text-xs font-semibold text-indigo-700 dark:text-indigo-300">View modules</summary>
                                                    <div class="mt-2 space-y-2">
                                                        @foreach($studentResult['modules'] as $module)
                                                            <div class="rounded-md bg-gray-50 p-2 text-xs text-gray-600 dark:bg-gray-950 dark:text-gray-300">
                                                                <div class="flex items-start justify-between gap-3">
                                                                    <span>
                                                                        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $module['course_name'] ?? 'Archived module' }}</span>
                                                                        <span class="text-gray-500 dark:text-gray-400">({{ $module['course_code'] ?? '-' }})</span>
                                                                    </span>
                                                                    <span class="font-semibold {{ ($module['result'] ?? '') === 'Passed' ? 'text-emerald-700 dark:text-emerald-300' : 'text-red-700 dark:text-red-300' }}">{{ number_format((float) ($module['final_mark'] ?? 0), 2) }}</span>
                                                                </div>
                                                                <p class="mt-1 text-gray-500 dark:text-gray-400">{{ $module['stage'] ?? 'No stage' }} / {{ trim(($module['semester'] ?? '').' '.($module['academic_year'] ?? '')) ?: $academicYear }} / Group {{ $module['group'] ?? '-' }}</p>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            </td>
                                            <td class="px-5 py-4 text-right text-sm font-semibold text-gray-900 dark:text-gray-100">{{ number_format((float) $studentResult['average_mark'], 2) }}</td>
                                            <td class="px-5 py-4 text-right text-sm font-semibold {{ $studentResult['failed_modules'] > 0 ? 'text-red-700 dark:text-red-300' : 'text-emerald-700 dark:text-emerald-300' }}">{{ number_format($studentResult['failed_modules']) }}</td>
                                            <td class="px-5 py-4">
                                                <span class="rounded-md px-2 py-1 text-xs font-semibold {{ $passed ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-200' : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-200' }}">{{ $passed ? 'Passed' : 'Failed' }}</span>
                                            </td>
                                        </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-5 py-10 text-center text-sm text-gray-500 dark:text-gray-400">No published results match these filters.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    @if($resultRows->count() >= 500)
                        <div class="border-t border-gray-200 px-5 py-3 text-sm text-gray-500 dark:border-gray-800 dark:text-gray-400">Module-level result export is limited to the first 500 matching rows. Use filters to narrow the list.</div>
                    @endif
                @else
                    <div class="px-5 py-8 text-sm text-gray-600 dark:text-gray-300">
                        Student results are not loaded on initial open to keep archive pages fast on large datasets. Apply filters to load scoped passed/failed rows.
                    </div>
                @endif
            </section>

            @if(collect($snapshotCoverage)->sum('value') > 0)
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Archive Snapshot</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Preserved records captured when the academic year was closed.</p>
                    </div>
                    <div class="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-5">
                        @foreach($snapshotCoverage as $item)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950">
                                <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $item['label'] }}</p>
                                <p class="mt-2 text-xl font-semibold text-gray-900 dark:text-gray-100">{{ number_format($item['value']) }}</p>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            @if($sections->isEmpty() && $snapshotModules->isNotEmpty())
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Snapshot Modules</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Modules preserved from the closing snapshot.</p>
                    </div>
                    <div class="grid gap-3 p-5 lg:grid-cols-2">
                        @foreach($snapshotModules as $module)
                            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-950">
                                @php
                                    $moduleDetails = $module['details']['module'] ?? [];
                                    $courseDetails = $module['details']['course'] ?? [];
                                    $teacherDetails = $module['details']['teacher'] ?? [];
                                    $semesterDetails = $module['details']['semester'] ?? [];
                                    $archivedAt = ! empty($moduleDetails['archived_at']) ? \Illuminate\Support\Carbon::parse($moduleDetails['archived_at'])->format('Y-m-d') : null;
                                @endphp
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <p class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">{{ $module['college'] ?? 'No college' }} / {{ $module['department'] ?? 'No department' }}</p>
                                        <h4 class="mt-2 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $module['course_name'] ?? 'Archived module' }}</h4>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $module['course_code'] ?? 'Course' }} / Group {{ $module['group'] ?? '-' }}</p>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $module['stage'] ?? 'No stage' }} / {{ trim(($module['semester'] ?? '').' '.($module['academic_year'] ?? '')) ?: $academicYear }}</p>
                                    </div>
                                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $module['status'] ?? 'Archived' }}</span>
                                </div>
                                <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Students</dt>
                                        <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ number_format($module['counts']['enrollments'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Marks</dt>
                                        <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ number_format($module['counts']['marks'] ?? 0) }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Assessments</dt>
                                        <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ number_format($module['counts']['assessments'] ?? 0) }}</dd>
                                    </div>
                                </dl>
                                <dl class="mt-4 grid gap-3 border-t border-gray-100 pt-4 text-sm dark:border-gray-800 sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Teacher</dt>
                                        <dd class="mt-1 text-gray-900 dark:text-gray-100">
                                            {{ $teacherDetails['name'] ?? $module['teacher'] ?? 'No teacher assigned' }}
                                            @if(! empty($teacherDetails['staff_id']))
                                                <span class="text-gray-500 dark:text-gray-400">({{ $teacherDetails['staff_id'] }})</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Credits / Capacity</dt>
                                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $courseDetails['credits'] ?? '-' }} credits / {{ $moduleDetails['capacity'] ?? $module['capacity'] ?? '-' }} seats</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Semester Dates</dt>
                                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $semesterDetails['start_date'] ?? '-' }} to {{ $semesterDetails['end_date'] ?? '-' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Archived</dt>
                                        <dd class="mt-1 text-gray-900 dark:text-gray-100">{{ $archivedAt ?? 'Stored snapshot' }}</dd>
                                    </div>
                                </dl>
                                @if(! empty($moduleDetails['notes']))
                                    <p class="mt-4 rounded-md bg-gray-50 p-3 text-sm text-gray-600 dark:bg-gray-900 dark:text-gray-300">{{ $moduleDetails['notes'] }}</p>
                                @endif
                            </article>
                        @endforeach
                    </div>
                </section>
            @endif

            @php
                $baseFilters = [
                    'academic_year' => $academicYear,
                    'q' => $filters['q'] ?: null,
                    'semester_id' => $filters['semester_id'] ?: null,
                    'result_status' => $filters['result_status'] ?: null,
                    'sort' => $filters['sort'] ?: null,
                ];
                $archiveUrl = function (array $extra = []) use ($baseFilters) {
                    return route('academic-year-closures.archive.show', array_filter(array_merge($baseFilters, $extra), fn ($value) => ! is_null($value) && $value !== ''));
                };

                $selectedCollegeId = $filters['college_id'] ? (int) $filters['college_id'] : null;
                $selectedDepartmentId = $filters['department_id'] ? (int) $filters['department_id'] : null;
                $selectedStage = $filters['stage'] ?: null;

                $selectedCollege = $selectedCollegeId ? $colleges->firstWhere('id', $selectedCollegeId) : null;
                $selectedDepartment = $selectedDepartmentId ? $departments->firstWhere('id', $selectedDepartmentId) : null;

                $collegeGroups = $sections->groupBy(fn (\App\Models\CourseSection $section) => $section->course?->department?->college?->name ?? 'No college');
                $departmentGroups = $sections->groupBy(fn (\App\Models\CourseSection $section) => $section->course?->department?->name ?? 'No department');
                $stageGroups = $sections->groupBy(fn (\App\Models\CourseSection $section) => $section->grade_level ?: 'No stage');
                $semesterGroups = $sections->groupBy(fn (\App\Models\CourseSection $section) => trim(($section->semester?->name ?? 'Semester').' '.($section->semester?->academic_year ?? '')));

                $isCollegeStep = is_null($selectedCollegeId);
                $isDepartmentStep = ! is_null($selectedCollegeId) && is_null($selectedDepartmentId);
                $isStageStep = ! is_null($selectedDepartmentId) && is_null($selectedStage);
                $isClassroomStep = ! is_null($selectedStage);
            @endphp

            <section class="space-y-4">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <a href="{{ $archiveUrl() }}" data-archive-drilldown-link="1" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1 font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-gray-800">Colleges</a>

                        @if($selectedCollege)
                            <span class="text-gray-400">/</span>
                            <a href="{{ $archiveUrl(['college_id' => $selectedCollege->id]) }}" data-archive-drilldown-link="1" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1 font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-gray-800">{{ $selectedCollege->name }}</a>
                        @endif

                        @if($selectedDepartment)
                            <span class="text-gray-400">/</span>
                            <a href="{{ $archiveUrl(['college_id' => $selectedCollege?->id, 'department_id' => $selectedDepartment->id]) }}" data-archive-drilldown-link="1" class="rounded-md border border-gray-200 bg-gray-50 px-3 py-1 font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-950 dark:text-gray-300 dark:hover:bg-gray-800">{{ $selectedDepartment->name }}</a>
                        @endif

                        @if($selectedStage)
                            <span class="text-gray-400">/</span>
                            <span class="rounded-md border border-indigo-200 bg-indigo-50 px-3 py-1 font-semibold text-indigo-700 dark:border-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-200">{{ $selectedStage }}</span>
                        @endif
                    </div>
                </div>

                @if($isCollegeStep)
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($collegeGroups as $collegeName => $collegeSections)
                            @php $collegeId = $collegeSections->first()?->course?->department?->college?->id; @endphp
                            <a href="{{ $archiveUrl(['college_id' => $collegeId]) }}" data-archive-drilldown-link="1" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-600">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">College</p>
                                <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $collegeName }}</h3>
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $collegeSections->pluck('course.department.name')->filter()->unique()->count() }} departments</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $collegeSections->count() }} classrooms</p>
                            </a>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 md:col-span-2 xl:col-span-3">
                                No archived modules match these filters.
                            </div>
                        @endforelse
                    </div>
                @elseif($isDepartmentStep)
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($departmentGroups as $departmentName => $departmentSections)
                            @php $departmentId = $departmentSections->first()?->course?->department?->id; @endphp
                            <a href="{{ $archiveUrl(['college_id' => $selectedCollegeId, 'department_id' => $departmentId]) }}" data-archive-drilldown-link="1" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-600">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Department</p>
                                <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $departmentName }}</h3>
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $departmentSections->pluck('grade_level')->filter()->unique()->count() }} stages</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $departmentSections->count() }} classrooms</p>
                            </a>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 md:col-span-2 xl:col-span-3">
                                No archived modules match these filters.
                            </div>
                        @endforelse
                    </div>
                @elseif($isStageStep)
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($stageGroups as $stageName => $stageSections)
                            <a href="{{ $archiveUrl(['college_id' => $selectedCollegeId, 'department_id' => $selectedDepartmentId, 'stage' => $stageName]) }}" data-archive-drilldown-link="1" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow dark:border-gray-800 dark:bg-gray-900 dark:hover:border-indigo-600">
                                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Stage</p>
                                <h3 class="mt-2 text-base font-semibold text-gray-900 dark:text-gray-100">{{ $stageName }}</h3>
                                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">{{ $stageSections->pluck('semester_id')->filter()->unique()->count() }} semesters</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $stageSections->count() }} classrooms</p>
                            </a>
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-10 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400 md:col-span-2 xl:col-span-3">
                                No archived modules match these filters.
                            </div>
                        @endforelse
                    </div>
                @elseif($isClassroomStep)
                    <article class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                        <header class="border-b border-gray-200 px-5 py-4 dark:border-gray-800">
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Classrooms</p>
                            <h3 class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $selectedStage }}</h3>
                        </header>

                        <div class="space-y-3 p-5">
                            @foreach($semesterGroups as $semesterName => $modules)
                                <div>
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $semesterName }}</p>
                                    <div class="mt-2 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        @foreach($modules as $section)
                                            <article class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div>
                                                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Classroom</p>
                                                        <h4 class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $section->course->name ?? 'Archived module' }}</h4>
                                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $section->course->code ?? 'Course' }} / Group {{ $section->section_code }}</p>
                                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Teacher: {{ $section->teacher->full_name ?? 'Not assigned' }}</p>
                                                    </div>
                                                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">{{ $section->trashed() ? 'Archived' : 'Closed' }}</span>
                                                </div>

                                                <dl class="mt-4 grid grid-cols-3 gap-3 text-sm">
                                                    <div>
                                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Students</dt>
                                                        <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $section->enrollment_count }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Marks</dt>
                                                        <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $section->marks_count }}</dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-xs font-semibold uppercase text-gray-500 dark:text-gray-400">Assessments</dt>
                                                        <dd class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $section->assessment_items_count }}</dd>
                                                    </div>
                                                </dl>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </article>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
