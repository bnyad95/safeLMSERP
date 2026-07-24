<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500">Learning &amp; Results</p>
            <h2 class="mt-1 text-xl font-semibold text-gray-800">Final Exam Entry</h2>
            <p class="mt-1 text-sm text-gray-600">Enter final exam trials by academic year, college, department, stage, semester, and course.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('marks.final-exam.index') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <div>
                        <label for="final-academic-year" class="block text-sm font-medium text-gray-700">Academic year</label>
                        <select id="final-academic-year" name="academic_year" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All years</option>
                            @foreach($filterOptions['academic_years'] as $academicYear)
                                <option value="{{ $academicYear }}" @selected($filters['academic_year'] === $academicYear)>{{ $academicYear }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="final-college" class="block text-sm font-medium text-gray-700">College</label>
                        <select id="final-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All colleges</option>
                            @foreach($filterOptions['colleges'] as $college)
                                <option value="{{ $college->id }}" @selected($filters['college_id'] === $college->id)>{{ $college->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="final-department" class="block text-sm font-medium text-gray-700">Department</label>
                        <select id="final-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All departments</option>
                            @foreach($filterOptions['departments'] as $department)
                                <option value="{{ $department->id }}" @selected($filters['department_id'] === $department->id)>{{ $department->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="final-stage" class="block text-sm font-medium text-gray-700">Stage</label>
                        <select id="final-stage" name="stage" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All stages</option>
                            @foreach($filterOptions['stages'] as $stage)
                                <option value="{{ $stage['key'] }}" @selected((string) $filters['stage'] === (string) $stage['key'])>{{ $stage['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="final-semester" class="block text-sm font-medium text-gray-700">Semester</label>
                        <select id="final-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All semesters</option>
                            @foreach($filterOptions['semesters'] as $semester)
                                <option value="{{ $semester->id }}" @selected($filters['semester_id'] === $semester->id)>{{ $semester->name }} {{ $semester->academic_year }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="final-search" class="block text-sm font-medium text-gray-700">Search</label>
                        <input id="final-search" name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Student, ID, course">
                    </div>

                    <div class="flex flex-wrap items-end gap-3 xl:col-span-6">
                        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                        <a href="{{ route('marks.final-exam.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                        <a href="{{ route('marks.submission-queue') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back to Mark Queue</a>
                    </div>
                </form>
            </section>

            <section class="grid gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="text-sm text-blue-800">Waiting first trial</p>
                    <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $finalExamStats['waiting_first_trial'] }}</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50 p-4">
                    <p class="text-sm text-red-800">Eligible second trial</p>
                    <p class="mt-2 text-2xl font-semibold text-red-950">{{ $finalExamStats['waiting_second_trial'] }}</p>
                </div>
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4">
                    <p class="text-sm text-emerald-800">Ready for review</p>
                    <p class="mt-2 text-2xl font-semibold text-emerald-950">{{ $finalExamStats['ready_for_review'] }}</p>
                </div>
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Courses Waiting for Final Exam Entry</h3>
                        <p class="mt-1 text-sm text-gray-500">Open a course to enter first-trial and eligible second-trial scores.</p>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($courseCards as $card)
                        @php
                            $course = $card['course'];
                            $href = route('marks.final-exam.course', array_merge(request()->except(['course_id', 'final_exam_page', 'submission_status']), ['course' => $course]));
                        @endphp
                        <a href="{{ $href }}" class="block rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-blue-300 hover:bg-blue-50">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">{{ $course->code }} - {{ $course->name }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ $card['college']?->name ?? 'No college' }} / {{ $card['department']?->name ?? 'No department' }}</p>
                                </div>
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ $card['marks_count'] }} students</span>
                            </div>
                            <div class="mt-4 grid grid-cols-3 gap-2 text-center">
                                <div class="rounded-md bg-blue-50 px-2 py-2">
                                    <p class="text-xs text-blue-800">First</p>
                                    <p class="text-sm font-semibold text-blue-950">{{ $card['waiting_first_trial'] }}</p>
                                </div>
                                <div class="rounded-md bg-red-50 px-2 py-2">
                                    <p class="text-xs text-red-800">Second</p>
                                    <p class="text-sm font-semibold text-red-950">{{ $card['waiting_second_trial'] }}</p>
                                </div>
                                <div class="rounded-md bg-gray-50 px-2 py-2">
                                    <p class="text-xs text-gray-600">Classes</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ $card['section_count'] }}</p>
                                </div>
                            </div>
                            <p class="mt-3 text-xs text-gray-500">
                                {{ $card['semesters']->map(fn ($semester) => trim($semester->name.' '.$semester->academic_year))->implode(', ') ?: 'No semester' }}
                                @if($card['stages']->isNotEmpty())
                                    / {{ $card['stages']->implode(', ') }}
                                @endif
                            </p>
                        </a>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 md:col-span-2 xl:col-span-3">
                            No courses are waiting for final exam entry in your scope.
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
