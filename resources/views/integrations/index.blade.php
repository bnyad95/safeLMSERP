<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Data Import / Export</h2>
                <p class="text-sm text-gray-600">Scoped CSV imports, exports, and read-only API endpoints for institutional data.</p>
            </div>
        </div>
    </x-slot>

    @php
        $tabUrl = fn (string $tab) => route('integrations.index', array_merge(request()->query(), ['tab' => $tab]));
        $exportQuery = collect($filters)->filter(fn ($value) => filled($value))->all();
        $user = auth()->user();
        $canImportStudents = $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['students.create', 'students.update']);
        $canImportCourses = $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['courses.create', 'courses.update']);
        $canImportMarks = $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['marks.enter', 'marks.review', 'marks.approve']);
        $canExport = [
            'students' => $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['students.view', 'students.create', 'students.update']),
            'teachers' => $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['teachers.view', 'teachers.create', 'teachers.update']),
            'courses' => $user?->hasRole('super_administrator') || $user?->hasPermission('courses.view'),
            'enrollments' => $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['enrollments.view', 'enrollments.manage']),
            'attendance' => $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['attendance.view', 'attendance.update']),
            'timetable' => $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['timetable.view', 'timetable.manage']),
            'finance' => $user?->hasRole('super_administrator') || $user?->hasPermission('finance.view'),
            'marks' => $user?->hasRole('super_administrator') || $user?->hasAnyPermission(['marks.view', 'marks.enter', 'marks.review', 'marks.approve', 'marks.publish']),
        ];
        $imports = [
            ['key' => 'students', 'title' => 'Students', 'description' => 'Profiles, departments, admissions, and emergency contacts.', 'enabled' => $canImportStudents, 'importRoute' => route('integrations.import.students'), 'sampleRoute' => route('integrations.samples.students'), 'sample' => $samples['students']],
            ['key' => 'courses', 'title' => 'Courses', 'description' => 'Catalog records mapped to departments.', 'enabled' => $canImportCourses, 'importRoute' => route('integrations.import.courses'), 'sampleRoute' => route('integrations.samples.courses'), 'sample' => $samples['courses']],
            ['key' => 'marks', 'title' => 'Marks', 'description' => 'Student course marks for results workflows.', 'enabled' => $canImportMarks, 'importRoute' => route('integrations.import.marks'), 'sampleRoute' => route('integrations.samples.marks'), 'sample' => $samples['marks']],
        ];
        $exports = [
            ['key' => 'students', 'title' => 'Students', 'description' => 'Profiles, admissions, departments, and emergency contacts.', 'route' => route('integrations.export.students', $exportQuery)],
            ['key' => 'teachers', 'title' => 'Teachers', 'description' => 'Staff profiles, departments, status, and titles.', 'route' => route('integrations.export.teachers', $exportQuery)],
            ['key' => 'courses', 'title' => 'Courses', 'description' => 'Course catalog by college and department.', 'route' => route('integrations.export.courses', $exportQuery)],
            ['key' => 'enrollments', 'title' => 'Enrollments / Modules', 'description' => 'Students registered into modules, stages, semesters, and teachers.', 'route' => route('integrations.export.enrollments', $exportQuery)],
            ['key' => 'attendance', 'title' => 'Attendance', 'description' => 'Daily class attendance by student, module, and semester.', 'route' => route('integrations.export.attendance', $exportQuery)],
            ['key' => 'timetable', 'title' => 'Timetable', 'description' => 'Weekly schedule entries by class, room, teacher, and stage.', 'route' => route('integrations.export.timetable', $exportQuery)],
            ['key' => 'finance', 'title' => 'Finance', 'description' => 'Invoices, receipts, payments, balances, and statuses.', 'route' => route('integrations.export.finance', $exportQuery)],
            ['key' => 'marks', 'title' => 'Marks', 'description' => 'Assessment and exam marks with workflow statuses.', 'route' => route('integrations.export.marks', $exportQuery)],
        ];
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($errors->any())
                <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="flex flex-wrap gap-2 border-b border-gray-200 px-4 py-3">
                    @foreach(['imports' => 'Imports', 'exports' => 'Exports', 'api' => 'API', 'history' => 'Import History'] as $key => $label)
                        <a href="{{ $tabUrl($key) }}" class="rounded-md px-4 py-2 text-sm font-semibold {{ $activeTab === $key ? 'bg-gray-900 text-white' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                            {{ $label }}
                        </a>
                    @endforeach
                </div>

                @if(in_array($activeTab, ['exports', 'api'], true))
                    <form method="GET" action="{{ route('integrations.index') }}" class="grid gap-4 border-b border-gray-200 bg-gray-50 p-4 lg:grid-cols-6">
                        <input type="hidden" name="tab" value="{{ $activeTab }}">
                        <div>
                            <label class="text-xs font-semibold uppercase text-gray-500" for="college_id">College</label>
                            <select id="college_id" name="college_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">All colleges</option>
                                @foreach($filterOptions['colleges'] as $college)
                                    <option value="{{ $college->id }}" @selected((int) $filters['college_id'] === $college->id)>{{ $college->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-gray-500" for="department_id">Department</label>
                            <select id="department_id" name="department_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">All departments</option>
                                @foreach($filterOptions['departments'] as $department)
                                    <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === $department->id)>{{ $department->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-gray-500" for="stage">Stage</label>
                            <select id="stage" name="stage" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">All stages</option>
                                @foreach($filterOptions['stages'] as $stage)
                                    <option value="{{ $stage }}" @selected($filters['stage'] === $stage)>{{ $stage }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-gray-500" for="semester_id">Semester</label>
                            <select id="semester_id" name="semester_id" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">All semesters</option>
                                @foreach($filterOptions['semesters'] as $semester)
                                    <option value="{{ $semester->id }}" @selected((int) $filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="text-xs font-semibold uppercase text-gray-500" for="academic_year">Academic Year</label>
                            <select id="academic_year" name="academic_year" class="mt-1 w-full rounded-md border-gray-300 text-sm">
                                <option value="">All years</option>
                                @foreach($filterOptions['academicYears'] as $year)
                                    <option value="{{ $year }}" @selected($filters['academic_year'] === $year)>{{ $year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-end gap-2">
                            <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                            <a href="{{ route('integrations.index', ['tab' => $activeTab]) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                        </div>
                    </form>
                @endif

                <div class="p-4 sm:p-6">
                    @if($activeTab === 'imports')
                        <div class="grid gap-5 lg:grid-cols-3">
                            @foreach($imports as $dataset)
                                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                    <div class="flex min-h-28 flex-col justify-between gap-4">
                                        <div>
                                            <h3 class="text-base font-semibold text-gray-900">{{ $dataset['title'] }}</h3>
                                            <p class="mt-1 text-sm text-gray-500">{{ $dataset['description'] }}</p>
                                        </div>
                                        <a href="{{ $dataset['sampleRoute'] }}" class="text-sm font-semibold text-blue-700 hover:underline">{{ __('Download sample CSV') }}</a>
                                    </div>

                                    @if($dataset['enabled'])
                                        <form action="{{ $dataset['importRoute'] }}" method="POST" enctype="multipart/form-data" class="mt-5 space-y-3">
                                            @csrf
                                            <input type="file" name="csv_file" accept=".csv,text/csv,text/plain" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                            <button type="submit" class="w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Import CSV</button>
                                        </form>
                                    @else
                                        <div class="mt-5 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">{{ __('Import permission is not assigned.') }}</div>
                                    @endif

                                    <pre class="mt-4 max-h-40 overflow-auto whitespace-pre-wrap break-all rounded-md bg-gray-950 p-3 text-xs text-gray-100">{{ $dataset['sample'] }}</pre>
                                </section>
                            @endforeach
                        </div>
                    @elseif($activeTab === 'exports')
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                            @foreach($exports as $dataset)
                                <section class="flex min-h-44 flex-col justify-between rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900">{{ $dataset['title'] }}</h3>
                                        <p class="mt-1 text-sm text-gray-500">{{ $dataset['description'] }}</p>
                                    </div>
                                    @if($canExport[$dataset['key']])
                                        <a href="{{ $dataset['route'] }}" class="mt-5 inline-flex justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Export CSV</a>
                                    @else
                                        <span class="mt-5 inline-flex justify-center rounded-md border border-gray-200 bg-gray-50 px-4 py-2 text-sm font-semibold text-gray-400">No access</span>
                                    @endif
                                </section>
                            @endforeach
                        </div>
                    @elseif($activeTab === 'api')
                        <div class="grid gap-5 lg:grid-cols-3">
                            @foreach(['/api/v1/students' => 'Students', '/api/v1/courses' => 'Courses', '/api/v1/marks' => 'Marks'] as $endpoint => $label)
                                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                                    <div class="flex items-center justify-between gap-3">
                                        <h3 class="text-base font-semibold text-gray-900">{{ $label }}</h3>
                                        <span class="rounded bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-700">GET</span>
                                    </div>
                                    <code class="mt-4 block overflow-x-auto rounded-md bg-gray-950 p-3 text-xs text-gray-100">{{ url($endpoint) }}</code>
                                    <p class="mt-3 text-sm text-gray-500">Accepts the same filters shown above and returns paginated JSON with Sanctum bearer authentication.</p>
                                </section>
                            @endforeach
                        </div>
                    @else
                        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
                            <div class="border-b border-gray-200 px-5 py-4">
                                <h3 class="text-base font-semibold text-gray-900">Recent Imports</h3>
                            </div>
                            <div class="divide-y divide-gray-200">
                                @forelse($recentImports as $import)
                                    <div class="p-5">
                                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm font-semibold text-gray-900">{{ ucfirst($import->type) }} / {{ $import->filename }}</p>
                                                <p class="text-xs text-gray-500">{{ $import->created_at->format('M d, Y H:i') }} by {{ $import->importedBy->name ?? 'Unknown user' }}</p>
                                            </div>
                                            <div class="text-sm text-gray-600">
                                                {{ $import->successful }} imported / {{ $import->failed }} failed
                                            </div>
                                        </div>
                                        @if($import->errors)
                                            <details class="mt-3">
                                                <summary class="cursor-pointer text-sm font-semibold text-red-600">View errors</summary>
                                                <ul class="mt-2 space-y-1 text-xs text-red-700">
                                                    @foreach($import->errors as $error)
                                                        <li>{{ $error }}</li>
                                                    @endforeach
                                                </ul>
                                            </details>
                                        @endif
                                    </div>
                                @empty
                                    <p class="p-5 text-sm text-gray-500">No CSV imports have been recorded yet.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
