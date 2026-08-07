<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-2xl font-semibold text-gray-900">Course Registration</h2>
            <p class="mt-1 text-sm text-gray-600">Register for available course groups in your department.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (!$student)
                <div class="rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                    No student profile is linked to your account email yet. Ask administration to create a student record with your email.
                </div>
            @else
                @unless($canRegister)
                    <div class="rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200">
                        {{ strtolower((string) $student->status) !== 'active' ? 'Course registration is unavailable while your student record is inactive.' : 'Course registration is unavailable until your current stage is assigned.' }}
                    </div>
                @endunless

                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                    <div class="grid gap-4 text-sm md:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold uppercase text-gray-500">Student</p>
                            <p class="mt-1 font-medium text-gray-900">{{ $student->full_name }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-gray-500">Student ID</p>
                            <p class="mt-1 font-medium text-gray-900">{{ $student->student_id }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-gray-500">Department</p>
                            <p class="mt-1 font-medium text-gray-900">{{ $student->department->name ?? 'No department' }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold uppercase text-gray-500">Registered Groups</p>
                            <p class="mt-1 font-medium text-gray-900">{{ $registrations->count() }}</p>
                        </div>
                    </div>
                </section>

                <section class="border-y border-gray-200 py-5">
                    <form method="GET" action="{{ route('course-registration.index') }}" class="grid gap-4 md:grid-cols-[minmax(220px,1fr)_220px_180px_auto] md:items-end">
                        <div>
                            <label for="registration-search" class="block text-xs font-semibold uppercase text-gray-500">Course</label>
                            <input id="registration-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Name or code" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                        <div>
                            <label for="registration-semester" class="block text-xs font-semibold uppercase text-gray-500">Semester</label>
                            <select id="registration-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All semesters</option>
                                @foreach($semesterOptions as $semester)
                                    <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="registration-grade" class="block text-xs font-semibold uppercase text-gray-500">Stage</label>
                            <select id="registration-grade" name="grade_level" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">All stages</option>
                                @foreach($gradeOptions as $grade)
                                    <option value="{{ $grade }}" @selected($filters['grade_level'] === $grade)>{{ $grade }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex gap-2">
                            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                            <a href="{{ route('course-registration.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                        </div>
                    </form>
                </section>

                <div class="grid gap-6 xl:grid-cols-[0.9fr_1.2fr]">
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-gray-900">My Registered Courses</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($registrations as $registration)
                                @php($section = $registration->courseSection)
                                <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-start sm:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900">{{ $section->course->code ?? 'Course' }} / {{ $section->course->name ?? 'No course' }}</p>
                                            @if($registration->is_retake)
                                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">Retake</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ $section->semester->name ?? 'Semester' }} {{ $section->semester->academic_year ?? '' }} / {{ $section->grade_level ?? 'No stage' }} / Group {{ $section->section_code }}
                                        </p>
                                        <p class="mt-1 text-sm text-gray-500">{{ $section->teacher->full_name ?? 'No teacher assigned' }} / Registered {{ $registration->enrolled_at?->format('Y-m-d') }}</p>
                                    </div>
                                    <a href="{{ route('class-stream.show', $section) }}" class="shrink-0 text-sm font-semibold text-blue-700 hover:underline">Open classroom</a>
                                </div>
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-gray-500">You have not registered for any course groups yet.</div>
                            @endforelse
                        </div>
                    </section>

                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-gray-900">Available Course Groups</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($availableSections as $section)
                                @php($remainingSeats = max($section->capacity - $section->registered_count, 0))
                                <div class="flex flex-col gap-4 px-5 py-4 md:flex-row md:items-start md:justify-between">
                                    <div>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="font-medium text-gray-900">{{ $section->course->code ?? 'Course' }} / {{ $section->course->name ?? 'No course' }}</p>
                                            @if($section->is_retake_registration)
                                                <span class="rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-700">Retake</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-sm text-gray-500">
                                            {{ $section->semester->name ?? 'Semester' }} {{ $section->semester->academic_year ?? '' }} / {{ $section->grade_level ?? 'No stage' }} / Group {{ $section->section_code }}
                                        </p>
                                        <p class="mt-1 text-sm text-gray-500">{{ $section->teacher->full_name ?? 'No teacher assigned' }}</p>
                                        <p class="mt-2 text-xs font-semibold text-gray-600">{{ $remainingSeats }} seats available</p>
                                        @if($section->is_retake_registration)
                                            <p class="mt-1 text-xs text-amber-700">Available because your previous published result was below the pass mark.</p>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('course-registration.store') }}" onsubmit="return confirm('Register for this course group?')">
                                        @csrf
                                        <input type="hidden" name="course_section_id" value="{{ $section->id }}">
                                        <button type="submit" @disabled($remainingSeats < 1 || ! $canRegister) class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800 disabled:cursor-not-allowed disabled:bg-gray-300">
                                            Register
                                        </button>
                                    </form>
                                </div>
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-gray-500">No course groups match the current filters or have available seats.</div>
                            @endforelse
                        </div>
                    </section>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
