<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ __('Global Search') }}</h2>
                <p class="text-sm text-gray-600">{{ __('Find students, teachers, courses, users, and master data from one place.') }}</p>
            </div>
            <a href="{{ route('dashboard') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                {{ __('Back to Dashboard') }}
            </a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                <form method="GET" action="{{ route('search') }}" class="flex flex-col gap-3 md:flex-row">
                    <input
                        type="text"
                        name="q"
                        value="{{ $query }}"
                        placeholder="{{ __('Search by name, email, code, id...') }}"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                    />
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black">
                        {{ __('Search') }}
                    </button>
                </form>

                @if ($query === '')
                    <p class="mt-4 rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-700">
                        {{ __('Enter a keyword to search across ERP modules.') }}
                    </p>
                @else
                    <p class="mt-4 text-sm text-gray-600">
                        {{ __('Showing up to 10 records per module for ":query". Found :count results.', ['query' => $query, 'count' => $totalResults]) }}
                    </p>
                @endif
            </div>

            @if ($query !== '' && $totalResults === 0)
                <div class="rounded-xl border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                    {{ __('No records found in modules you can access.') }}
                </div>
            @endif

            @if ($query !== '' && $canSearchStudents)
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Students') }}</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($students as $student)
                            <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <span class="font-medium text-gray-900">{{ $student->full_name }}</span>
                                <span class="text-gray-500">• {{ $student->student_id }}</span>
                                @if ($student->email)
                                    <span class="text-gray-500">• {{ $student->email }}</span>
                                @endif
                                <span class="text-gray-500">• {{ $student->department->name ?? __('No department') }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('No student matches.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($query !== '' && $canSearchTeachers)
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Teachers') }}</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($teachers as $teacher)
                            <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <span class="font-medium text-gray-900">{{ $teacher->full_name }}</span>
                                <span class="text-gray-500">• {{ $teacher->staff_id }}</span>
                                @if ($teacher->email)
                                    <span class="text-gray-500">• {{ $teacher->email }}</span>
                                @endif
                                @if ($teacher->title)
                                    <span class="text-gray-500">• {{ $teacher->title }}</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('No teacher matches.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($query !== '' && $canSearchCourses)
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Courses') }}</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($courses as $course)
                            <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <span class="font-medium text-gray-900">{{ $course->name }}</span>
                                <span class="text-gray-500">• {{ $course->code }}</span>
                                <span class="text-gray-500">• {{ $course->department->name ?? __('No department') }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('No course matches.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif

            @if ($query !== '' && $canSearchStructure)
                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Universities') }}</h3>
                        <div class="mt-4 space-y-2">
                            @forelse ($universities as $university)
                                <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <span class="font-medium text-gray-900">{{ $university->name }}</span>
                                    <span class="text-gray-500">• {{ $university->code }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No university matches.') }}</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Colleges') }}</h3>
                        <div class="mt-4 space-y-2">
                            @forelse ($colleges as $college)
                                <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <span class="font-medium text-gray-900">{{ $college->name }}</span>
                                    @if ($college->code)
                                        <span class="text-gray-500">• {{ $college->code }}</span>
                                    @endif
                                    <span class="text-gray-500">• {{ $college->university->name ?? __('N/A') }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No college matches.') }}</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Departments') }}</h3>
                        <div class="mt-4 space-y-2">
                            @forelse ($departments as $department)
                                <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <span class="font-medium text-gray-900">{{ $department->name }}</span>
                                    @if ($department->code)
                                        <span class="text-gray-500">• {{ $department->code }}</span>
                                    @endif
                                    <span class="text-gray-500">• {{ $department->university->name ?? __('N/A') }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No department matches.') }}</p>
                            @endforelse
                        </div>
                    </div>

                    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                        <h3 class="text-lg font-semibold text-gray-900">{{ __('Semesters') }}</h3>
                        <div class="mt-4 space-y-2">
                            @forelse ($semesters as $semester)
                                <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                    <span class="font-medium text-gray-900">{{ $semester->name }}</span>
                                    <span class="text-gray-500">• {{ $semester->academic_year }}</span>
                                    <span class="text-gray-500">• {{ $semester->university->name ?? __('N/A') }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">{{ __('No semester matches.') }}</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            @endif

            @if ($query !== '' && $canSearchUsers)
                <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900">{{ __('Users') }}</h3>
                    <div class="mt-4 space-y-2">
                        @forelse ($users as $user)
                            <div class="rounded-md border border-gray-100 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <span class="font-medium text-gray-900">{{ $user->name }}</span>
                                <span class="text-gray-500">• {{ $user->email }}</span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-500">{{ __('No user matches.') }}</p>
                        @endforelse
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
