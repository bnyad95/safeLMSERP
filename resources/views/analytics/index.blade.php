<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Institution Analytics</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $canViewFinanceAnalytics ? 'Academic, attendance, finance, and course performance signals.' : 'Academic, attendance, and course performance signals.' }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('analytics.index') }}" class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:p-5">
                <input type="hidden" name="tab" value="{{ $activeTab }}">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">College</label>
                        <select name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All colleges</option>
                            @foreach($filterOptions['colleges'] as $college)
                                <option value="{{ $college->id }}" @selected((int) $filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Department</label>
                        <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All departments</option>
                            @foreach($filterOptions['departments'] as $department)
                                <option value="{{ $department->id }}" @selected((int) $filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Stage</label>
                        <select name="stage" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All stages</option>
                            @foreach($filterOptions['stages'] as $stage)
                                <option value="{{ $stage }}" @selected($filters['stage'] === $stage)>{{ $stage }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Semester</label>
                        <select name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All semesters</option>
                            @foreach($filterOptions['semesters'] as $semester)
                                <option value="{{ $semester->id }}" @selected((int) $filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="min-w-0">
                        <label class="block text-sm font-medium text-gray-700">Academic Year</label>
                        <select name="academic_year" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All years</option>
                            @foreach($filterOptions['academicYears'] as $year)
                                <option value="{{ $year }}" @selected($filters['academic_year'] === $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <a href="{{ route('analytics.index', ['tab' => $activeTab]) }}" class="inline-flex justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply Filters</button>
                </div>
            </form>

            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-2xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </div>

            @php
                $tabs = [
                    'academic' => 'Academic',
                    'attendance' => 'Attendance',
                    'courses' => 'Courses',
                ];

                if ($canViewFinanceAnalytics) {
                    $tabs = array_merge(
                        array_slice($tabs, 0, 2, true),
                        ['finance' => 'Finance'],
                        array_slice($tabs, 2, null, true)
                    );
                }
            @endphp
            <div class="flex flex-wrap gap-2">
                @foreach($tabs as $key => $label)
                    <a href="{{ route('analytics.index', array_merge(request()->query(), ['tab' => $key])) }}" class="rounded-md px-3 py-2 text-sm font-semibold {{ $activeTab === $key ? 'bg-gray-900 text-white' : 'border border-gray-300 bg-white text-gray-700 hover:bg-gray-50' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            @if($activeTab === 'academic')
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">GPA Trend</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Semester</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">GPA</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Average Mark</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Published Marks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($gpaTrend as $row)
                                    <tr>
                                        <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $row['semester'] }}</td>
                                        <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ number_format($row['gpa'], 2) }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ number_format($row['average_mark'], 1) }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ $row['marks_count'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No published marks available for GPA trend.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($activeTab === 'attendance')
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Attendance Risk</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Department</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Rate</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Absences</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Risk</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($attendanceRisk as $row)
                                    <tr>
                                        <td class="px-5 py-3 text-sm font-medium text-gray-900">{{ $row['student']->full_name }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ $row['student']->department->name ?? '-' }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-900">{{ number_format($row['rate'], 1) }}%</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ $row['absent'] }} / {{ $row['total'] }}</td>
                                        <td class="px-5 py-3">
                                            <span class="rounded-md {{ $row['risk'] === 'High' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700' }} px-2 py-1 text-xs font-semibold">{{ $row['risk'] }}</span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">No attendance risk detected.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($activeTab === 'finance')
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Unpaid Balances</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Balance</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Overdue</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Last Activity</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($unpaidBalances as $row)
                                    <tr>
                                        <td class="px-5 py-3 text-sm">
                                            <div class="font-medium text-gray-900">{{ $row['student']->full_name }}</div>
                                            <div class="text-gray-500">{{ $row['student']->student_id }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ money($row['balance'], $row['currency']) }} {{ $row['currency'] }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ $row['overdue'] }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ $row['last_activity'] ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No unpaid balances found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif

            @if($activeTab === 'courses')
                <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-5 py-4">
                        <h3 class="text-base font-semibold text-gray-900">Course Performance</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Course</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Average</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Pass Rate</th>
                                    <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Attendance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($coursePerformance as $row)
                                    <tr>
                                        <td class="px-5 py-3 text-sm">
                                            <div class="font-medium text-gray-900">{{ $row['course']->code }} / {{ $row['course']->name }}</div>
                                            <div class="text-gray-500">{{ $row['course']->department->name ?? '-' }}</div>
                                        </td>
                                        <td class="px-5 py-3 text-sm text-gray-900">{{ is_null($row['average_mark']) ? '-' : number_format($row['average_mark'], 1) }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ is_null($row['pass_rate']) ? '-' : number_format($row['pass_rate'], 1).'%' }}</td>
                                        <td class="px-5 py-3 text-sm text-gray-600">{{ is_null($row['attendance_rate']) ? '-' : number_format($row['attendance_rate'], 1).'%' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No course performance data yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-app-layout>
