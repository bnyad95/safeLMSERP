<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">{{ $canManageClassroom ? 'Teaching' : 'Classrooms' }}</h2>
            <p class="text-sm text-gray-600">{{ $filters['section_id'] ? ($canManageClassroom ? 'Class workspace' : 'Read-only class oversight') : ($canManageClassroom ? 'Choose a class to open its workspace.' : 'Browse from college to class.') }}</p>
        </div>
    </x-slot>

    @php
        $selectedSection = $filters['section_id'] ? $filteredSections->first() : null;
        $classTeacher = $selectedSection?->teacher ?? $teacher;
        $canManageAssessments = $canManageClassroom && auth()->user()?->hasAnyPermission(['lms.create_assignment', 'marks.enter']);
        $canViewAttendance = $canManageClassroom || auth()->user()?->hasRole('super_administrator') || auth()->user()?->hasAnyPermission(['attendance.view', 'attendance.create', 'attendance.update']);
        $canViewTimetable = $canManageClassroom || auth()->user()?->hasRole('super_administrator') || auth()->user()?->hasAnyPermission(['timetable.view', 'timetable.manage']);
        $workspaceTabs = [
            'stream' => 'Stream',
            'classwork' => 'Classwork',
            'people' => 'People',
            'grades' => 'Grades',
            'attendance' => 'Attendance',
            'timetable' => 'Timetable',
            'analytics' => 'Analytics',
        ];
        $classroomRoute = $canManageClassroom ? 'teacher-dashboard' : 'classrooms.index';
        $classroomUrl = fn (array $params = []) => route($classroomRoute, array_filter($params, fn ($value) => $value !== null && $value !== ''));
        $tabUrl = fn (string $tab) => $classroomUrl(['section_id' => $selectedSection?->id, 'tab' => $tab]);
        $assessmentUrl = fn ($assessment) => route('assessments.index', ['section_id' => $selectedSection?->id, 'assessment_id' => $assessment->id]).'#assessment-'.$assessment->id;
        $materialsUrl = fn (string $route = 'materials.index') => route($route, ['course' => $selectedSection?->course_id, 'section_id' => $selectedSection?->id]);
        $attendanceUrl = fn (string $route = 'attendance.index') => route($route, ['course' => $selectedSection?->course_id, 'section_id' => $selectedSection?->id]);
        $selectedCollege = $classroomHierarchy['selected_college'] ?? null;
        $selectedDepartment = $classroomHierarchy['selected_department'] ?? null;
        $selectedGrade = $classroomHierarchy['selected_grade'] ?? null;
        $collegeUrl = fn ($college) => $classroomUrl(['college_id' => $college->id]);
        $departmentUrl = fn ($department) => $classroomUrl(['college_id' => $selectedCollege?->id, 'department_id' => $department->id]);
        $gradeUrl = fn ($grade) => $classroomUrl(['college_id' => $selectedCollege?->id, 'department_id' => $selectedDepartment?->id, 'stage' => $grade['key']]);
        $classUrl = fn ($section) => $classroomUrl(['college_id' => $selectedCollege?->id, 'department_id' => $selectedDepartment?->id, 'stage' => $selectedGrade['key'] ?? null, 'section_id' => $section->id]);
        $classListUrl = $selectedGrade
            ? $classroomUrl(['college_id' => $selectedCollege?->id, 'department_id' => $selectedDepartment?->id, 'stage' => $selectedGrade['key']])
            : $classroomUrl();
    @endphp

    <div class="py-8">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            @if(! $selectedSection)
                <section class="mb-6 flex flex-col gap-2 border-b border-gray-200 pb-5 md:flex-row md:items-end md:justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-500">{{ $canManageClassroom ? 'Welcome back' : 'Academic oversight' }}</p>
                        <h3 class="mt-1 text-xl font-semibold text-gray-900">{{ $canManageClassroom ? ($teacher->full_name ?? 'Teacher') : 'Classrooms' }}</h3>
                        <p class="mt-1 text-sm text-gray-600">{{ $canManageClassroom ? (($teacher->title ?? 'Teaching staff').' - '.($teacher->department->name ?? 'Department')) : 'Browse colleges, departments, stages, and classes in your academic scope.' }}</p>
                    </div>
                    <p class="text-sm font-semibold text-gray-600">{{ $assignedSections->count() }} classes</p>
                </section>

                @if($canManageClassroom)
                    <section id="assigned-classes">
                        <h3 class="mb-3 text-base font-semibold text-gray-900">My Classes</h3>
                        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                            @forelse($assignedSections as $section)
                                <a href="{{ route('teacher-dashboard', ['section_id' => $section->id]) }}" class="group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                                    <div class="min-h-28 bg-blue-700 p-5 text-white transition group-hover:bg-blue-800">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-lg font-semibold">{{ $section->course->name ?? 'No course assigned' }}</p>
                                                <p class="mt-1 text-sm text-blue-100">{{ $section->course->code ?? 'Course' }} - Group {{ $section->section_code }}</p>
                                            </div>
                                            <span class="rounded-md bg-white/15 px-2 py-1 text-xs font-semibold">{{ $section->grade_level ?? 'Class' }}</span>
                                        </div>
                                    </div>
                                    <div class="grid grid-cols-2 divide-x divide-gray-100 border-t border-gray-100 px-4 py-4 text-sm">
                                        <div><p class="text-xs text-gray-500">Students</p><p class="mt-1 font-semibold text-gray-900">{{ $section->enrolled_count }}</p></div>
                                        <div class="pl-4"><p class="text-xs text-gray-500">Assessments</p><p class="mt-1 font-semibold text-gray-900">{{ $section->assessment_count }}</p></div>
                                    </div>
                                    <p class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500">{{ $section->semester->name ?? 'Semester' }} {{ $section->semester->academic_year ?? '' }}</p>
                                </a>
                            @empty
                                <div class="rounded-lg border border-dashed border-gray-300 bg-white p-6 text-sm text-gray-500">No classes are assigned to you yet.</div>
                            @endforelse
                        </div>
                    </section>
                @else
                    <nav class="mb-5 flex flex-wrap items-center gap-2 text-sm" aria-label="Classroom hierarchy">
                        <a href="{{ $classroomUrl() }}" class="font-semibold text-blue-700 hover:underline">Classrooms</a>
                        @if($selectedCollege)<span class="text-gray-400">/</span><a href="{{ $collegeUrl($selectedCollege) }}" class="font-semibold text-blue-700 hover:underline">{{ $selectedCollege->name }}</a>@endif
                        @if($selectedDepartment)<span class="text-gray-400">/</span><a href="{{ $departmentUrl($selectedDepartment) }}" class="font-semibold text-blue-700 hover:underline">{{ $selectedDepartment->name }}</a>@endif
                        @if($selectedGrade)<span class="text-gray-400">/</span><span class="font-semibold text-gray-700">{{ $selectedGrade['label'] }}</span>@endif
                    </nav>

                    @if(! $selectedCollege)
                        <section>
                            <div class="mb-4"><h3 class="text-lg font-semibold text-gray-900">Colleges</h3><p class="mt-1 text-sm text-gray-500">Select a college to view its departments.</p></div>
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @forelse($classroomHierarchy['colleges'] as $card)
                                    <a href="{{ $collegeUrl($card['college']) }}" class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-emerald-300 hover:shadow-md">
                                        <div class="flex items-start justify-between gap-4"><div><p class="text-xs font-semibold uppercase text-emerald-700">College</p><h4 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-emerald-800">{{ $card['college']->name }}</h4><p class="mt-1 text-sm text-gray-500">{{ $card['college']->code }}</p></div><span class="rounded-md bg-emerald-50 px-2.5 py-1 text-sm font-semibold text-emerald-800">{{ $card['department_count'] }}</span></div>
                                        <p class="mt-5 border-t border-gray-100 pt-4 text-sm text-gray-600">{{ $card['department_count'] }} {{ Str::plural('department', $card['department_count']) }} - {{ $card['class_count'] }} {{ Str::plural('class', $card['class_count']) }}</p>
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
                                @foreach($classroomHierarchy['departments'] as $card)
                                    <a href="{{ $departmentUrl($card['department']) }}" class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                                        <p class="text-xs font-semibold uppercase text-blue-700">Department</p><h4 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-blue-800">{{ $card['department']->name }}</h4><p class="mt-1 text-sm text-gray-500">{{ $card['department']->code }}</p>
                                        <p class="mt-5 border-t border-gray-100 pt-4 text-sm text-gray-600">{{ $card['grade_count'] }} {{ Str::plural('stage', $card['grade_count']) }} - {{ $card['class_count'] }} {{ Str::plural('class', $card['class_count']) }}</p>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @elseif(! $selectedGrade)
                        <section>
                            <div class="mb-4"><h3 class="text-lg font-semibold text-gray-900">Stages</h3><p class="mt-1 text-sm text-gray-500">Select a stage in {{ $selectedDepartment->name }}.</p></div>
                            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                @foreach($classroomHierarchy['grades'] as $card)
                                    <a href="{{ $gradeUrl($card) }}" class="group rounded-lg border border-gray-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-amber-300 hover:shadow-md">
                                        <p class="text-xs font-semibold uppercase text-amber-700">Stage</p><h4 class="mt-2 text-lg font-semibold text-gray-900 group-hover:text-amber-800">{{ $card['label'] }}</h4>
                                        <div class="mt-5 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm"><div><p class="text-xs text-gray-500">Classes</p><p class="mt-1 font-semibold text-gray-900">{{ $card['class_count'] }}</p></div><div><p class="text-xs text-gray-500">Students</p><p class="mt-1 font-semibold text-gray-900">{{ $card['student_count'] }}</p></div></div>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @else
                        <section id="admin-classes">
                            <div class="mb-4"><h3 class="text-lg font-semibold text-gray-900">Classes</h3><p class="mt-1 text-sm text-gray-500">{{ $selectedGrade['label'] }} classes in {{ $selectedDepartment->name }}.</p></div>
                            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                                @foreach($classroomHierarchy['classes'] as $section)
                                    <a href="{{ $classUrl($section) }}" class="group overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:-translate-y-0.5 hover:border-blue-300 hover:shadow-md">
                                        <div class="min-h-28 bg-blue-700 p-5 text-white transition group-hover:bg-blue-800"><p class="text-lg font-semibold">{{ $section->course->name ?? 'No course assigned' }}</p><p class="mt-1 text-sm text-blue-100">{{ $section->course->code ?? 'Course' }} - Group {{ $section->section_code }}</p></div>
                                        <div class="grid grid-cols-2 divide-x divide-gray-100 px-4 py-4 text-sm"><div><p class="text-xs text-gray-500">Students</p><p class="mt-1 font-semibold text-gray-900">{{ $section->enrolled_count }}</p></div><div class="pl-4"><p class="text-xs text-gray-500">Assessments</p><p class="mt-1 font-semibold text-gray-900">{{ $section->assessment_count }}</p></div></div>
                                        <p class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500">Teacher: {{ $section->teacher->full_name ?? 'Not assigned' }}</p>
                                    </a>
                                @endforeach
                            </div>
                        </section>
                    @endif
                @endif
            @else
                <a href="{{ $canManageClassroom ? route('teacher-dashboard') : $classListUrl }}" class="mb-4 inline-flex text-sm font-semibold text-blue-700 hover:underline">Back to classes</a>

                <section class="overflow-hidden rounded-lg bg-blue-700 text-white shadow-sm">
                    <div class="min-h-48 p-6 sm:p-8">
                        <p class="text-xs font-semibold uppercase text-blue-100">{{ $canManageClassroom ? 'Class Dashboard' : 'Class Overview' }}</p>
                        <h3 class="mt-3 text-2xl font-semibold sm:text-3xl">{{ $selectedSection->course->name ?? 'Course' }}</h3>
                        <p class="mt-2 text-base text-blue-100">{{ $selectedSection->course->code ?? 'Course' }} - Group {{ $selectedSection->section_code }}</p>
                        <div class="mt-7 flex flex-wrap gap-x-6 gap-y-2 text-sm text-blue-50">
                            <span>{{ $selectedSection->grade_level ?? 'No stage' }}</span>
                            <span>{{ $selectedSection->semester->name ?? 'Semester' }} {{ $selectedSection->semester->academic_year ?? '' }}</span>
                            <span>{{ $stats['total_students'] }} Students</span>
                            <span>{{ $stats['assessment_items'] }} Assessments</span>
                        </div>
                    </div>
                </section>

                @unless($canManageClassroom)
                    <section class="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-5 shadow-sm">
                        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                            <div>
                                <p class="text-xs font-semibold uppercase text-emerald-700">Oversight mode</p>
                                <h4 class="mt-1 text-lg font-semibold text-emerald-950">Read-only classroom review</h4>
                                <p class="mt-1 text-sm text-emerald-800">You can inspect class activity, students, results, attendance, timetable, analytics, and shared materials without teacher-only editing tools.</p>
                            </div>
                            <span class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-emerald-800">{{ $classTeacher->full_name ?? 'Teacher not assigned' }}</span>
                        </div>
                    </section>

                    <section class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Teacher</p><p class="mt-3 truncate text-lg font-semibold text-gray-900">{{ $classTeacher->full_name ?? 'Not assigned' }}</p></div>
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Students</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ $stats['total_students'] }}</p></div>
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Assessments</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ $stats['assessment_items'] }}</p></div>
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Attendance rate</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ is_null($classAnalytics['attendance_rate']) ? 'N/A' : number_format($classAnalytics['attendance_rate'], 1).'%' }}</p></div>
                        <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Timetable sessions</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ $classTimetableEntries->count() }}</p></div>
                    </section>
                @endunless

                <nav class="mt-4 flex overflow-x-auto border-b border-gray-200 bg-white" aria-label="Class workspace tabs">
                    @foreach($workspaceTabs as $tab => $label)
                        <a href="{{ $tabUrl($tab) }}" class="whitespace-nowrap border-b-2 px-5 py-4 text-sm font-semibold {{ $filters['tab'] === $tab ? 'border-blue-700 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:text-gray-900' }}">{{ $label }}</a>
                    @endforeach
                    @if($canManageClassroom)
                        <a href="{{ route('class-messages.index', $selectedSection) }}" class="whitespace-nowrap border-b-2 border-transparent px-5 py-4 text-sm font-semibold text-gray-600 hover:bg-gray-50 hover:text-gray-900">Messages</a>
                    @endif
                </nav>

                <div class="py-6">
                    @if($filters['tab'] === 'stream')
                        <div class="grid gap-6 lg:grid-cols-[240px_minmax(0,1fr)]">
                            <aside class="h-fit rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold text-gray-900">Upcoming</h4>
                                    <span class="text-xs font-medium text-gray-500">{{ $upcomingAssessments->count() }}</span>
                                </div>
                                <div class="mt-4 space-y-4">
                                    @forelse($upcomingAssessments->take(3) as $assessment)
                                        <a href="{{ $assessmentUrl($assessment) }}" class="block border-b border-gray-100 pb-3 last:border-0 last:pb-0">
                                            <p class="text-sm font-medium text-gray-900 hover:text-blue-700">{{ $assessment->title }}</p>
                                            <p class="mt-1 text-xs text-gray-500">Due {{ $assessment->due_at->format('M d, H:i') }}</p>
                                        </a>
                                    @empty
                                        <p class="text-sm text-gray-500">No work due soon.</p>
                                    @endforelse
                                </div>
                                <a href="{{ $tabUrl('classwork') }}" class="mt-4 inline-flex text-sm font-semibold text-blue-700 hover:underline">View all classwork</a>
                            </aside>

                            <div class="space-y-4">
                                @if($canManageClassroom)
                                    <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                                        <p class="text-sm font-semibold text-gray-900">Quick actions</p>
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <a href="{{ route('assessments.index', ['section_id' => $selectedSection->id]) }}" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Create assessment</a>
                                            <a href="{{ $materialsUrl('materials.create') }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Upload material</a>
                                            <a href="{{ $attendanceUrl() }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Take attendance</a>
                                            <form method="POST" action="{{ route('class-stream.student-posting.toggle', $selectedSection) }}">
                                                @csrf @method('PATCH')
                                                <button type="submit" class="rounded-md border px-3 py-2 text-sm font-semibold {{ $selectedSection->students_can_post_stream ? 'border-green-300 bg-green-50 text-green-800 hover:bg-green-100' : 'border-gray-300 text-gray-700 hover:bg-gray-50' }}">
                                                    Student posts: {{ $selectedSection->students_can_post_stream ? 'On' : 'Off' }}
                                                </button>
                                            </form>
                                        </div>
                                    </section>
                                @endif

                                @include('class-stream._feed', ['section' => $selectedSection, 'streamPosts' => $streamPosts, 'canCreatePost' => $canManageClassroom, 'canInteract' => $canManageClassroom])
                            </div>
                        </div>
                    @elseif($filters['tab'] === 'classwork')
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h4 class="text-lg font-semibold text-gray-900">Classwork</h4>
                                <p class="mt-1 text-sm text-gray-500">Assessments, exams, and learning materials for this class.</p>
                            </div>
                            @if($canManageClassroom)
                                <div class="flex flex-wrap gap-2">
                                    <a href="{{ route('assessments.index', ['section_id' => $selectedSection->id]) }}" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-800">Add assessment</a>
                                    <a href="{{ $materialsUrl('materials.create') }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Add material</a>
                                </div>
                            @endif
                        </div>

                        <section class="mt-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-gray-200 px-5 py-4"><h5 class="text-sm font-semibold text-gray-900">Assessments</h5></div>
                            <div class="divide-y divide-gray-100">
                                @forelse($teacherAssessments as $assessment)
                                    <div class="flex flex-col gap-3 px-5 py-4 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <a href="{{ $assessmentUrl($assessment) }}" class="text-sm font-semibold text-gray-900 hover:text-blue-700">{{ $assessment->title }}</a>
                                                <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-600">{{ $assessment->type }}</span>
                                            </div>
                                            <p class="mt-1 text-xs text-gray-500">{{ $assessment->submissions_count }} submissions{{ $assessment->due_at ? ' - Due '.$assessment->due_at->format('M d, Y H:i') : ' - No due date' }}</p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ $assessmentUrl($assessment) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Open</a>
                                            @if($canManageAssessments)<a href="{{ route('assessment-items.edit', $assessment) }}" class="rounded-md bg-blue-50 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-100">Edit</a>@endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-5 py-8 text-center text-sm text-gray-500">No assessments for this class.</div>
                                @endforelse
                            </div>
                        </section>

                        <section class="mt-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-gray-200 px-5 py-4">
                                <h5 class="text-sm font-semibold text-gray-900">Materials</h5>
                                @if($canManageClassroom)<a href="{{ $materialsUrl() }}" class="text-sm font-semibold text-blue-700 hover:underline">Manage</a>@endif
                            </div>
                            <div class="divide-y divide-gray-100">
                                @forelse($classMaterials as $material)
                                    <div class="flex items-center justify-between gap-4 px-5 py-4">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $material->title }}</p>
                                            <p class="mt-1 text-xs text-gray-500">{{ strtoupper($material->file_type) }} - {{ ucfirst($material->visibility) }}</p>
                                        </div>
                                        <div class="flex shrink-0 items-center gap-3">
                                            <span class="text-xs text-gray-500">{{ $material->created_at?->format('M d') }}</span>
                                            @if($material->file_path)
                                                <a href="{{ route('materials.download', ['course' => $selectedSection->course_id, 'material' => $material->id, 'section_id' => $selectedSection->id]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50">Download</a>
                                            @else
                                                <span class="text-xs text-gray-400">No file</span>
                                            @endif
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-5 py-8 text-center text-sm text-gray-500">No materials for this class.</div>
                                @endforelse
                            </div>
                        </section>
                    @elseif($filters['tab'] === 'people')
                        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="border-b border-blue-200 px-5 py-4">
                                <h4 class="text-lg font-semibold text-blue-800">Teacher</h4>
                            </div>
                            <div class="flex items-center gap-3 px-5 py-4">
                                <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-100 font-semibold text-green-800">{{ strtoupper(substr($classTeacher->full_name ?? 'T', 0, 1)) }}</div>
                                <p class="text-sm font-semibold text-gray-900">{{ $classTeacher->full_name ?? 'Teacher not assigned' }}</p>
                            </div>
                        </section>

                        <section class="mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="flex items-center justify-between border-b border-blue-200 px-5 py-4">
                                <h4 class="text-lg font-semibold text-blue-800">Students</h4>
                                <span class="text-sm text-gray-500">{{ $classStudents->count() }} enrolled</span>
                            </div>
                            <div class="divide-y divide-gray-100">
                                @forelse($classStudents as $student)
                                    <div class="grid gap-2 px-5 py-4 md:grid-cols-[minmax(0,1.2fr)_minmax(0,0.7fr)_minmax(0,1fr)_auto] md:items-center">
                                        <div class="flex min-w-0 items-center gap-3">
                                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700">{{ strtoupper(substr($student->full_name, 0, 1)) }}</div>
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $student->full_name }}</p>
                                        </div>
                                        <p class="text-sm text-gray-600">{{ $student->student_id }}</p>
                                        <p class="truncate text-sm text-gray-500">{{ $student->email }}</p>
                                        @if($canManageClassroom && $studentUser = $classStudentUsers->get($student->email))
                                            <a href="{{ route('class-messages.index', ['courseSection' => $selectedSection, 'recipient_id' => $studentUser->id]) }}" class="rounded-md border border-gray-300 px-3 py-1.5 text-center text-xs font-semibold text-gray-700 hover:bg-gray-50">Message</a>
                                        @elseif($canManageClassroom)
                                            <span class="text-xs text-gray-400">No account</span>
                                        @endif
                                    </div>
                                @empty
                                    <div class="px-5 py-8 text-center text-sm text-gray-500">No enrolled students for this class.</div>
                                @endforelse
                            </div>
                        </section>
                    @elseif($filters['tab'] === 'grades')
                        <section x-data="{ editing: false }" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="flex flex-col gap-2 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <h4 class="text-lg font-semibold text-gray-900">Grades</h4>
                                    <p class="mt-1 text-sm text-gray-500">{{ $stats['prefinal_marks_entered'] }} pre-final marks entered - final exam scores are entered by the examination committee</p>
                                </div>
                                @if($canManageClassroom)
                                    <button type="button" x-show="! editing" x-on:click="editing = true" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Enter pre-final marks</button>
                                @endif
                            </div>
                            @if($canManageClassroom)
                                @error('prefinal_marks')<div class="border-b border-red-200 bg-red-50 px-5 py-3 text-sm text-red-700">{{ $message }}</div>@enderror
                                <form method="POST" action="{{ route('teacher.prefinal-marks.store', $selectedSection) }}">
                                    @csrf
                            @else
                                <div>
                            @endif
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Pre-final mark</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">First trial</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Second trial</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Final mark</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @forelse($classStudents as $student)
                                                @php
                                                    $mark = $classMarks->get($student->id);
                                                    $status = $mark->submission_status ?? 'draft';
                                                    $locked = in_array($status, ['submitted', 'under_review', 'approved'], true) || ! is_null($mark?->first_trial_final_exam) || ! is_null($mark?->second_trial_final_exam);
                                                @endphp
                                                <tr>
                                                    <td class="px-5 py-4"><p class="text-sm font-semibold text-gray-900">{{ $student->full_name }}</p><p class="mt-1 text-xs text-gray-500">{{ $student->student_id }}</p></td>
                                                    <td class="px-5 py-4">
                                                        @if($canManageClassroom)
                                                            <span x-show="! editing" class="text-sm font-semibold text-gray-800">{{ is_null($mark?->prefinal_mark) ? '-' : number_format((float) $mark->prefinal_mark, 2) }}</span>
                                                            <div x-show="editing" x-cloak>
                                                                <input type="number" name="prefinal_marks[{{ $student->id }}]" value="{{ old('prefinal_marks.'.$student->id, $mark?->prefinal_mark) }}" min="0" max="{{ config('academics.prefinal_mark_max', 100) }}" step="0.01" @disabled($locked) class="w-28 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 disabled:bg-gray-100 disabled:text-gray-500">
                                                                @if($locked)<p class="mt-1 text-xs text-gray-500">Locked</p>@endif
                                                            </div>
                                                        @else
                                                            <span class="text-sm font-semibold text-gray-800">{{ is_null($mark?->prefinal_mark) ? '-' : number_format((float) $mark->prefinal_mark, 2) }}</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-5 py-4 text-sm text-gray-700">{{ is_null($mark?->first_trial_final_exam) ? '-' : number_format((float) $mark->first_trial_final_exam, 2) }}</td>
                                                    <td class="px-5 py-4 text-sm text-gray-700">{{ is_null($mark?->second_trial_final_exam) ? '-' : number_format((float) $mark->second_trial_final_exam, 2) }}</td>
                                                    <td class="px-5 py-4 text-sm text-gray-700">{{ ($mark?->final_mark ?? 0) > 0 ? number_format((float) $mark->final_mark, 2) : '-' }}</td>
                                                    <td class="px-5 py-4 text-sm capitalize text-gray-600">{{ str_replace('_', ' ', $status) }}</td>
                                                </tr>
                                            @empty
                                                <tr><td colspan="6" class="px-5 py-8 text-center text-sm text-gray-500">No enrolled students for this class.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                                @if($canManageClassroom)
                                    <div x-show="editing" x-cloak class="flex justify-end gap-2 border-t border-gray-200 bg-gray-50 px-5 py-4">
                                        <button type="button" x-on:click="editing = false" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Cancel</button>
                                        <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Save pre-final marks</button>
                                    </div>
                                @endif
                            @if($canManageClassroom)</form>@else</div>@endif
                        </section>
                    @elseif($filters['tab'] === 'attendance')
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">Attendance</h4>
                            <p class="mt-1 text-sm text-gray-500">{{ $canManageClassroom ? 'Record daily attendance and review class participation.' : 'Review class participation and attendance history.' }}</p>
                        </div>
                        @if($canViewAttendance)
                            <div class="mt-5 grid gap-4 md:grid-cols-3">
                                @if($canManageClassroom)<a href="{{ $attendanceUrl() }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300"><p class="text-sm font-semibold text-gray-900">Take attendance</p><p class="mt-2 text-sm text-gray-500">Mark students present, absent, late, or excused.</p></a>@endif
                                <a href="{{ $attendanceUrl('attendance.report') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300"><p class="text-sm font-semibold text-gray-900">Class report</p><p class="mt-2 text-sm text-gray-500">Review attendance rates and participation risk.</p></a>
                                <a href="{{ $attendanceUrl('attendance.history') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm hover:border-blue-300"><p class="text-sm font-semibold text-gray-900">Attendance history</p><p class="mt-2 text-sm text-gray-500">Browse previously recorded class sessions.</p></a>
                            </div>
                        @endif
                    @elseif($filters['tab'] === 'timetable')
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div><h4 class="text-lg font-semibold text-gray-900">Class Timetable</h4><p class="mt-1 text-sm text-gray-500">Scheduled sessions for this class only.</p></div>
                            @if($canViewTimetable)<a href="{{ route('timetables.index') }}" class="text-sm font-semibold text-blue-700 hover:underline">{{ $canManageClassroom ? 'Open my full timetable' : 'Open full timetable' }}</a>@endif
                        </div>
                        <section class="mt-5 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-100">
                                    <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Day</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Time</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Room</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Type</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th></tr></thead>
                                    <tbody class="divide-y divide-gray-100">
                                        @forelse($classTimetableEntries as $entry)
                                            <tr><td class="px-5 py-4 text-sm font-semibold text-gray-900">{{ $entry->day_of_week }}</td><td class="px-5 py-4 text-sm text-gray-600">{{ substr($entry->start_time, 0, 5) }}-{{ substr($entry->end_time, 0, 5) }}</td><td class="px-5 py-4 text-sm text-gray-600">{{ $entry->classroom->name ?? $entry->room_number ?? 'No room' }}</td><td class="px-5 py-4 text-sm capitalize text-gray-600">{{ $entry->type }}</td><td class="px-5 py-4 text-sm capitalize {{ $entry->status === 'scheduled' ? 'text-green-700' : 'text-gray-500' }}">{{ $entry->status }}</td></tr>
                                        @empty
                                            <tr><td colspan="5" class="px-5 py-8 text-center text-sm text-gray-500">No timetable entries are scheduled for this class.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    @else
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900">Class Analytics</h4>
                            <p class="mt-1 text-sm text-gray-500">Performance and participation for this class only.</p>
                        </div>

                        <div class="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Average attendance</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ is_null($classAnalytics['attendance_rate']) ? 'N/A' : number_format($classAnalytics['attendance_rate'], 1).'%' }}</p></div>
                            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Students at risk</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ $classAnalytics['at_risk'] }}</p></div>
                            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Average pre-final</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ is_null($classAnalytics['prefinal_average']) ? 'N/A' : number_format($classAnalytics['prefinal_average'], 1) }}</p></div>
                            <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><p class="text-sm text-gray-500">Average final</p><p class="mt-3 text-2xl font-semibold text-gray-900">{{ is_null($classAnalytics['final_average']) ? 'N/A' : number_format($classAnalytics['final_average'], 1) }}</p></div>
                        </div>

                        <div class="mt-6 grid gap-6 xl:grid-cols-2">
                            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-200 px-5 py-4"><h5 class="text-sm font-semibold text-gray-900">Attendance risk</h5></div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Student</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Rate</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Absent</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Risk</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @forelse($attendanceRisk as $row)
                                                <tr><td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ $row['student']->full_name }}</td><td class="px-5 py-3 text-sm text-gray-700">{{ is_null($row['rate']) ? '-' : number_format($row['rate'], 1).'%' }}</td><td class="px-5 py-3 text-sm text-gray-600">{{ $row['absent'] }}</td><td class="px-5 py-3 text-sm {{ $row['risk'] === 'High' ? 'text-red-700' : ($row['risk'] === 'Watch' ? 'text-amber-700' : 'text-gray-600') }}">{{ $row['risk'] }}</td></tr>
                                            @empty
                                                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No enrolled students.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                                <div class="border-b border-gray-200 px-5 py-4"><h5 class="text-sm font-semibold text-gray-900">Assessment performance</h5></div>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-100">
                                        <thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Assessment</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Submitted</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Graded</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Average</th></tr></thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @forelse($assessmentAnalytics as $row)
                                                <tr><td class="px-5 py-3 text-sm font-semibold text-gray-900">{{ $row['assessment']->title }}</td><td class="px-5 py-3 text-sm text-gray-600">{{ $row['submitted'] }} / {{ $stats['total_students'] }}</td><td class="px-5 py-3 text-sm text-gray-600">{{ $row['graded'] }}</td><td class="px-5 py-3 text-sm text-gray-700">{{ is_null($row['average']) ? '-' : number_format($row['average'], 1).'%' }}</td></tr>
                                            @empty
                                                <tr><td colspan="4" class="px-5 py-8 text-center text-sm text-gray-500">No assessments for this class.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
