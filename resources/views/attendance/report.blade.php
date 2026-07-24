<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Attendance Report</h2>
                <p class="text-sm text-gray-600">{{ $course->name }} ({{ $course->code }}){{ $section ? ' - Group '.$section->section_code : '' }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('attendance.history', ['course' => $course->id, 'section_id' => $section?->id]) }}"
                   class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                    History
                </a>
                <a href="{{ route('attendance.index', ['course' => $course->id, 'section_id' => $section?->id]) }}"
                   class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700">
                    Mark Attendance
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Classes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Present</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Absent</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Late</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Excused</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Attendance %</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Detail</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($stats as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $row['student_name'] }}</td>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $row['total_classes'] }}</td>
                                <td class="px-6 py-3 text-sm font-medium text-green-700">{{ $row['present'] }}</td>
                                <td class="px-6 py-3 text-sm text-red-600">{{ $row['absent'] ?? 0 }}</td>
                                <td class="px-6 py-3 text-sm text-yellow-600">{{ $row['late'] ?? 0 }}</td>
                                <td class="px-6 py-3 text-sm text-blue-600">{{ $row['excused'] ?? 0 }}</td>
                                <td class="px-6 py-3">
                                    @php
                                        $pct = $row['percentage'];
                                        $color = $pct >= 75 ? 'bg-green-500' : ($pct >= 50 ? 'bg-yellow-500' : 'bg-red-500');
                                    @endphp
                                    <div class="flex items-center gap-2">
                                        <div class="h-2 flex-1 rounded-full bg-gray-200">
                                            <div class="{{ $color }} h-2 rounded-full" style="width: {{ $pct }}%"></div>
                                        </div>
                                        <span class="text-sm font-medium {{ $pct >= 75 ? 'text-green-700' : ($pct >= 50 ? 'text-yellow-700' : 'text-red-700') }}">
                                            {{ $pct }}%
                                        </span>
                                    </div>
                                </td>
                                <td class="px-6 py-3">
                                    <a href="{{ route('attendance.student-report', ['course' => $course->id, 'student' => $row['student_id'], 'section_id' => $section?->id]) }}"
                                       class="text-xs text-blue-600 hover:underline">View</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">No attendance records yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
