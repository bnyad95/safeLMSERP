<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-2xl font-semibold text-gray-900">Academic Transcript</h2>
                <p class="mt-1 text-sm text-gray-600">Published academic results for {{ $student->full_name }}.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('transcripts.preview', $student) }}" target="_blank" rel="noopener" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Preview PDF</a>
                <a href="{{ route('transcripts.download', $student) }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Download PDF</a>
            </div>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(auth()->user()->hasRole('student'))
                <a href="{{ route('student-portal') }}" class="inline-flex text-sm font-semibold text-blue-700 hover:underline">Back to student dashboard</a>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="grid gap-5 text-sm sm:grid-cols-2 lg:grid-cols-5">
                    <div><p class="text-xs font-medium uppercase text-gray-500">Student</p><p class="mt-1 font-semibold text-gray-900">{{ $student->full_name }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">Student ID</p><p class="mt-1 font-semibold text-gray-900">{{ $student->student_id }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">University</p><p class="mt-1 font-semibold text-gray-900">{{ $student->university->name ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">Department</p><p class="mt-1 font-semibold text-gray-900">{{ $student->department->name ?? 'N/A' }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">Status</p><p class="mt-1 font-semibold text-gray-900">{{ $student->status }}</p></div>
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-3" aria-label="Transcript summary">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-5">
                    <p class="text-sm font-medium text-blue-700">Cumulative GPA</p>
                    <p class="mt-3 text-3xl font-semibold text-blue-900">{{ number_format($gpa, 2) }}</p>
                    <p class="mt-2 text-xs text-blue-700">Credit-weighted on a 4.00 scale</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-5">
                    <p class="text-sm font-medium text-emerald-700">Completed Courses</p>
                    <p class="mt-3 text-3xl font-semibold text-emerald-900">{{ $completedCourses }}</p>
                    <p class="mt-2 text-xs text-emerald-700">Courses with a passing final mark</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <p class="text-sm font-medium text-gray-500">Total Credits</p>
                    <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $credits }}</p>
                    <p class="mt-2 text-xs text-gray-500">Published course credit hours</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4"><h3 class="font-semibold text-gray-900">Published Results</h3></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Course</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Semester</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Credits</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Final Mark</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Grade</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($marks as $mark)
                                @php
                                    $grade = app(App\Services\TranscriptService::class)->getLetterGrade((float) $mark->final_mark);
                                    $gradeTone = in_array($grade, ['A', 'B']) ? 'text-emerald-700' : (in_array($grade, ['C', 'D']) ? 'text-amber-700' : 'text-red-700');
                                    $semesterDefinition = $mark->courseSection?->semester;
                                    $semester = $semesterDefinition
                                        ? $semesterDefinition->name.' '.$semesterDefinition->academic_year
                                        : 'N/A';
                                @endphp
                                <tr>
                                    <td class="px-5 py-4"><p class="text-sm font-semibold text-gray-900">{{ $mark->course->code ?? 'N/A' }}</p><p class="mt-1 text-xs text-gray-500">{{ $mark->course->name ?? 'N/A' }}</p></td>
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-gray-600">{{ $semester }}</td>
                                    <td class="px-5 py-4 text-sm text-gray-600">{{ $mark->course->credits ?? 0 }}</td>
                                    <td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ number_format((float) $mark->final_mark, 2) }}</td>
                                    <td class="px-5 py-4 text-sm font-semibold {{ $gradeTone }}">{{ $grade }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">No published marks are available.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
