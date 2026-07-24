<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">Results Overview</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600">Monitor mark progress, approval status, publication readiness, and student result visibility across your academic scope.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <nav class="flex flex-wrap items-center gap-2 text-sm" aria-label="Results hierarchy">
                @foreach($hierarchy['breadcrumbs'] as $crumb)
                    @if(! $loop->first)<span class="text-gray-400">/</span>@endif
                    @if($crumb['href'])
                        <a href="{{ $crumb['href'] }}" class="font-semibold text-blue-700 hover:underline">{{ $crumb['label'] }}</a>
                    @else
                        <span class="font-semibold text-gray-700">{{ $crumb['label'] }}</span>
                    @endif
                @endforeach
            </nav>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('exams') }}" class="grid gap-4 lg:grid-cols-6">
                    <div class="lg:col-span-2">
                        <label for="results-search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input id="results-search" name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Student, ID, course, teacher">
                    </div>

                    <div>
                        <label for="results-college" class="block text-sm font-medium text-gray-700">College</label>
                        <select id="results-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All colleges</option>
                            @foreach($filterOptions['colleges'] as $college)
                                <option value="{{ $college->id }}" @selected($filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-department" class="block text-sm font-medium text-gray-700">Department</label>
                        <select id="results-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All departments</option>
                            @foreach($filterOptions['departments'] as $department)
                                <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-stage" class="block text-sm font-medium text-gray-700">Stage</label>
                        <select id="results-stage" name="stage" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All stages</option>
                            @foreach($filterOptions['stages'] as $stage)
                                <option value="{{ $stage['key'] }}" @selected((string) $filters['stage'] === (string) $stage['key'])>{{ $stage['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-semester" class="block text-sm font-medium text-gray-700">Semester</label>
                        <select id="results-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All semesters</option>
                            @foreach($filterOptions['semesters'] as $semester)
                                <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-course" class="block text-sm font-medium text-gray-700">Course</label>
                        <select id="results-course" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All courses</option>
                            @foreach($filterOptions['courses'] as $course)
                                <option value="{{ $course->id }}" @selected($filters['course_id'] === $course->id)>{{ $course->code }} - {{ $course->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-teacher" class="block text-sm font-medium text-gray-700">Teacher</label>
                        <select id="results-teacher" name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All teachers</option>
                            @foreach($filterOptions['teachers'] as $teacher)
                                <option value="{{ $teacher->id }}" @selected($filters['teacher_id'] === $teacher->id)>{{ $teacher->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-submission" class="block text-sm font-medium text-gray-700">Mark status</label>
                        <select id="results-submission" name="submission_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All statuses</option>
                            @foreach(['draft' => 'Draft', 'submitted' => 'Submitted', 'under_review' => 'Under Review', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['submission_status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-visibility" class="block text-sm font-medium text-gray-700">Visibility</label>
                        <select id="results-visibility" name="visibility_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All visibility</option>
                            @foreach(['draft' => 'Not published', 'published' => 'Published'] as $value => $label)
                                <option value="{{ $value }}" @selected($filters['visibility_status'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="results-result" class="block text-sm font-medium text-gray-700">Result</label>
                        <select id="results-result" name="result_status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All results</option>
                            <option value="passed" @selected($filters['result_status'] === 'passed')>Passed</option>
                            <option value="failed" @selected($filters['result_status'] === 'failed')>Failed</option>
                        </select>
                    </div>

                    <div>
                        <label for="results-sort" class="block text-sm font-medium text-gray-700">Sort</label>
                        <select id="results-sort" name="sort" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="recent" @selected($filters['sort'] === 'recent')>Recently updated</option>
                            <option value="final_desc" @selected($filters['sort'] === 'final_desc')>Highest mark</option>
                            <option value="final_asc" @selected($filters['sort'] === 'final_asc')>Lowest mark</option>
                        </select>
                    </div>

                    @if($filters['section_id'])
                        <input type="hidden" name="section_id" value="{{ $filters['section_id'] }}">
                    @endif

                    <div class="flex flex-wrap items-end gap-3 lg:col-span-4">
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                        <a href="{{ route('exams') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                        <a href="{{ route('exams.export', request()->query()) }}" class="rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">Export CSV</a>
                    </div>
                </form>
            </section>

            <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-5" aria-label="Results summary">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                        <p class="text-sm text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-1 text-xs text-gray-500">{{ $stat['detail'] }}</p>
                        @if($stat['label'] === 'Pending Review' && $canOpenMarkQueue)
                            <a href="{{ route('marks.submission-queue') }}" class="mt-3 inline-flex text-xs font-semibold text-blue-700 hover:underline">Review in Mark Queue</a>
                        @endif
                    </div>
                @endforeach
            </section>

            <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Browse Results</h3>
                        <p class="mt-1 text-sm text-gray-500">College, department, stage, semester, and class view.</p>
                    </div>
                    <div class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                        @forelse($hierarchy['cards'] as $card)
                            @if($card['href'])
                                <a href="{{ $card['href'] }}" class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                                    <p class="text-xs font-semibold uppercase text-blue-700">{{ Str::singular(str_replace('_', ' ', $hierarchy['level'])) }}</p>
                                    <h4 class="mt-2 text-base font-semibold text-gray-900 group-hover:text-blue-800">{{ $card['title'] }}</h4>
                                    <p class="mt-1 text-sm text-gray-500">{{ $card['meta'] }}</p>
                                    <div class="mt-5 grid grid-cols-3 gap-3 border-t border-gray-100 pt-4 text-sm">
                                        <div><p class="text-xs text-gray-500">Marks</p><p class="mt-1 font-semibold text-gray-900">{{ $card['marks'] }}</p></div>
                                        <div><p class="text-xs text-gray-500">Pending</p><p class="mt-1 font-semibold text-amber-700">{{ $card['pending'] }}</p></div>
                                        <div><p class="text-xs text-gray-500">Avg</p><p class="mt-1 font-semibold text-gray-900">{{ is_null($card['average']) ? 'N/A' : $card['average'] }}</p></div>
                                    </div>
                                </a>
                            @else
                                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                    <p class="text-xs font-semibold uppercase text-gray-500">{{ Str::singular(str_replace('_', ' ', $hierarchy['level'])) }}</p>
                                    <h4 class="mt-2 text-base font-semibold text-gray-900">{{ $card['title'] }}</h4>
                                    <p class="mt-1 text-sm text-gray-500">{{ $card['meta'] }}</p>
                                    <p class="mt-5 border-t border-gray-100 pt-4 text-sm text-gray-600">{{ $card['marks'] }} marks / {{ $card['published'] }} published</p>
                                </div>
                            @endif
                        @empty
                            <div class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 md:col-span-2 xl:col-span-3">No result records match the current scope and filters.</div>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Status Breakdown</h3>
                    </div>
                    <div class="space-y-4 p-5">
                        @foreach($statusBreakdown as $status)
                            <div>
                                <div class="mb-1.5 flex justify-between text-sm"><span class="text-gray-600">{{ $status['label'] }}</span><span class="font-semibold text-gray-900">{{ $status['count'] }}</span></div>
                                <div class="h-2 overflow-hidden rounded bg-gray-100"><div class="h-full bg-blue-500" style="width: {{ $status['percent'] }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                </section>
            </div>

            <div class="grid gap-6 xl:grid-cols-2">
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Course Performance</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($coursePerformance as $row)
                            <div class="flex items-center justify-between gap-4 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $row['course'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $row['marks'] }} marks / {{ $row['published'] }} published</p>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ $row['passed'] }} passed / {{ $row['failed'] }} failed
                                        @if(! is_null($row['pass_rate']))
                                            / {{ $row['pass_rate'] }}% pass rate
                                        @endif
                                    </p>
                                </div>
                                <span class="shrink-0 text-sm font-semibold text-gray-900">{{ is_null($row['average']) ? 'N/A' : $row['average'] }} avg</span>
                            </div>
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-gray-500">No course performance data yet.</p>
                        @endforelse
                    </div>
                </section>

                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Departments Needing Attention</h3>
                    </div>
                    <div class="divide-y divide-gray-100">
                        @forelse($departmentRisk as $row)
                            <div class="flex items-center justify-between gap-4 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-gray-900">{{ $row['department'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $row['pending'] }} pending / {{ $row['unpublished'] }} not published</p>
                                </div>
                                <span class="shrink-0 rounded-md bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $row['attention'] }}</span>
                            </div>
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-gray-500">No departments need attention in this scope.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Marks in View</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Course</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Final</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Result</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Submitted</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">Links</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($recentMarks as $row)
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ $row['student'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $row['student_id'] }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm text-gray-900">{{ $row['course'] }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $row['class'] }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ $row['final_mark'] }}</td>
                                    <td class="px-5 py-4">
                                        <span class="{{ $row['result_status'] === 'Passed' ? 'rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-800' : 'rounded-md bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-800' }}">{{ $row['result_status'] }}</span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $row['submission_status'] }}</span>
                                        <span class="ml-1 rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700">{{ $row['visibility_status'] }}</span>
                                    </td>
                                    <td class="px-5 py-4 text-sm text-gray-500">{{ $row['submitted_at'] }}</td>
                                    <td class="px-5 py-4 text-right text-sm">
                                        @if($row['queue_url'])<a href="{{ $row['queue_url'] }}" class="font-semibold text-blue-700 hover:underline">Queue</a>@endif
                                        @if($row['student_url'])<a href="{{ $row['student_url'] }}" class="ml-3 font-semibold text-gray-700 hover:underline">Student</a>@endif
                                        @if($row['course_url'])<a href="{{ $row['course_url'] }}" class="ml-3 font-semibold text-gray-700 hover:underline">Course</a>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="px-5 py-12 text-center text-sm text-gray-500">No marks match the current scope and filters.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
