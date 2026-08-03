<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Add Module Offering</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Module Offerings</p>
            </div>
            <a href="{{ route('module-offerings.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700">Back</a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if($setupIssues->isNotEmpty())
                <div class="rounded-lg border border-amber-300 bg-amber-50 p-5 dark:border-amber-800 dark:bg-amber-950/40">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-amber-950 dark:text-amber-100">Academic setup is incomplete</h3>
                            <ul class="mt-2 space-y-1 text-sm text-amber-900 dark:text-amber-200">
                                @foreach($setupIssues as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        </div>
                        <a href="{{ route('bologna-definition') }}" class="inline-flex shrink-0 items-center justify-center rounded-md border border-amber-400 bg-white px-4 py-2 text-sm font-semibold text-amber-900 hover:bg-amber-100 dark:border-amber-700 dark:bg-gray-900 dark:text-amber-100 dark:hover:bg-gray-800">Academic Setup</a>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('course-sections.store') }}" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
                @csrf
                <input type="hidden" name="open_section" value="1">

                <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-6">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Academic period</h3>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    <div>
                        <label for="module-university" class="block text-sm font-medium text-gray-700 dark:text-gray-300">University</label>
                        <select id="module-university" name="university_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="">Select university</option>
                            @foreach($universities as $university)
                                <option value="{{ $university->id }}" data-regular-semesters="{{ $university->expectedSemesterCount() }}" @selected(old('university_id', $filters['university_id']) == $university->id)>{{ $university->name }}{{ $university->code ? ' / '.$university->code : '' }}</option>
                            @endforeach
                        </select>
                        @error('university_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="module-academic-year" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Academic Year</label>
                        <select id="module-academic-year" name="academic_year_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="">Select academic year</option>
                            @foreach($academicYears as $academicYear)
                                <option value="{{ $academicYear->id }}" data-university-id="{{ $academicYear->university_id }}" @selected(old('academic_year_id', $filters['academic_year_id']) == $academicYear->id)>{{ $academicYear->name }} / {{ ucfirst($academicYear->status) }}</option>
                            @endforeach
                        </select>
                        @error('academic_year_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="module-semester" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Semester</label>
                        <select id="module-semester" name="semester_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="">Select semester</option>
                            @foreach($semesters as $semester)
                                <option
                                    value="{{ $semester->id }}"
                                    data-university-id="{{ $semester->university_id }}"
                                    data-academic-year-id="{{ $semester->academic_year_id }}"
                                    data-sequence="{{ $semester->sequence }}"
                                    data-term-type="{{ $semester->term_type }}"
                                    data-regular-semesters="{{ $semester->university?->expectedSemesterCount() ?? 2 }}"
                                    @selected(old('semester_id') == $semester->id)
                                >{{ $semester->name }}</option>
                            @endforeach
                        </select>
                        @error('semester_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="border-y border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-6">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Academic structure</h3>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    <div>
                        <label for="module-college" class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                        <select id="module-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="">Select college</option>
                            @foreach($colleges as $college)
                                <option value="{{ $college->id }}" data-university-id="{{ $college->university_id }}" @selected(old('college_id', $filters['college_id']) == $college->id)>{{ $college->name }}{{ $college->code ? ' / '.$college->code : '' }}</option>
                            @endforeach
                        </select>
                        @error('college_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="module-department" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                        <select id="module-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="">Select department</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" data-university-id="{{ $department->university_id }}" data-college-id="{{ $department->college_id }}" @selected(old('department_id', $filters['department_id']) == $department->id)>{{ $department->name }}{{ $department->code ? ' / '.$department->code : '' }}</option>
                            @endforeach
                        </select>
                        @error('department_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="module-course-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Course Search</label>
                        <input id="module-course-search" type="search" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="Code or course name">
                    </div>

                    <div class="sm:col-span-2">
                        <label for="module-course" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Catalog Course</label>
                        <select id="module-course" name="course_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="">Select active catalog course</option>
                            @foreach($courses as $course)
                                <option
                                    value="{{ $course->id }}"
                                    data-university-id="{{ $course->university_id }}"
                                    data-college-id="{{ $course->department?->college_id }}"
                                    data-department-id="{{ $course->department_id }}"
                                    data-search="{{ strtolower($course->code.' '.$course->name) }}"
                                    @selected(old('course_id') == $course->id)
                                >{{ $course->code }} - {{ $course->name }}</option>
                            @endforeach
                        </select>
                        @error('course_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="module-stage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Stage</label>
                        <select id="module-stage" name="stage_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            <option value="">Select stage</option>
                            @foreach($stages as $stage)
                                <option value="{{ $stage->id }}" data-university-id="{{ $stage->university_id }}" data-department-id="{{ $stage->department_id }}" data-sequence="{{ $stage->sequence }}" @selected(old('stage_id') == $stage->id)>{{ $stage->name }}</option>
                            @endforeach
                        </select>
                        @error('stage_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="border-y border-gray-200 px-5 py-4 dark:border-gray-700 sm:px-6">
                    <h3 class="font-semibold text-gray-900 dark:text-gray-100">Offering details</h3>
                </div>
                <div class="grid gap-5 p-5 sm:grid-cols-2 sm:p-6 lg:grid-cols-3">
                    <div>
                        <label for="module-group" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Group</label>
                        <input id="module-group" name="section_code" value="{{ old('section_code', 'A') }}" maxlength="50" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                        @error('section_code') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    @if($canAssignTeachers)
                        <div>
                            <label for="module-teacher-search" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teacher Search</label>
                            <input id="module-teacher-search" type="search" value="{{ $filters['teacher_q'] }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" placeholder="Name or staff ID">
                        </div>

                        <div>
                            <label for="module-teacher" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Teacher</label>
                            <select id="module-teacher" name="teacher_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                                <option value="">Unassigned</option>
                                @foreach($teachers as $teacher)
                                    <option
                                        value="{{ $teacher->id }}"
                                        data-university-id="{{ $teacher->university_id }}"
                                        data-department-id="{{ $teacher->department_id }}"
                                        data-search="{{ strtolower($teacher->full_name.' '.$teacher->staff_id) }}"
                                        @selected(old('teacher_id') == $teacher->id)
                                    >{{ $teacher->full_name }} / {{ $teacher->staff_id }}{{ $teacher->department ? ' / '.$teacher->department->name : '' }}</option>
                                @endforeach
                            </select>
                            @error('teacher_id') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div>
                        <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Program Semester</span>
                        <div id="program-semester-preview" class="mt-1 flex min-h-10 items-center rounded-md border border-gray-200 bg-gray-50 px-3 text-sm font-semibold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">
                            Select a stage and semester
                        </div>
                    </div>

                    <div>
                        <label for="module-capacity" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Capacity</label>
                        <input id="module-capacity" type="number" min="1" max="500" name="capacity" value="{{ old('capacity', 40) }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                        @error('capacity') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="module-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                        <select id="module-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                            @foreach(['planned' => 'Planned', 'active' => 'Active', 'closed' => 'Closed'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', 'active') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="module-notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                        <textarea id="module-notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">{{ old('notes') }}</textarea>
                        @error('notes') <p class="mt-1 text-xs text-red-600 dark:text-red-400">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="flex justify-end border-t border-gray-200 bg-gray-50 px-5 py-4 dark:border-gray-700 dark:bg-gray-800/60 sm:px-6">
                    <button @disabled($setupIssues->isNotEmpty()) class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-gray-400 dark:disabled:bg-gray-600">Create Offering</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const fields = {
                university: document.getElementById('module-university'),
                academicYear: document.getElementById('module-academic-year'),
                semester: document.getElementById('module-semester'),
                college: document.getElementById('module-college'),
                department: document.getElementById('module-department'),
                course: document.getElementById('module-course'),
                stage: document.getElementById('module-stage'),
                teacher: document.getElementById('module-teacher'),
                courseSearch: document.getElementById('module-course-search'),
                teacherSearch: document.getElementById('module-teacher-search'),
                preview: document.getElementById('program-semester-preview'),
            };

            if (!fields.university || !fields.academicYear || !fields.semester || !fields.college || !fields.department || !fields.course || !fields.stage) return;

            const selected = (field) => field?.value || '';
            const query = (field) => (field?.value || '').trim().toLowerCase();

            const filterOptions = (field, predicate) => {
                Array.from(field.options).forEach((option, index) => {
                    if (index === 0) return;
                    const compatible = predicate(option);
                    option.hidden = !compatible;
                    option.disabled = !compatible;
                    if (option.selected && !compatible) field.value = '';
                });
            };

            const selectOnlyOption = (field) => {
                if (field.value) return;
                const available = Array.from(field.options).filter((option, index) => index > 0 && !option.disabled);
                if (available.length === 1) field.value = available[0].value;
            };

            const updateProgramSemester = () => {
                const stageSequence = Number(fields.stage.selectedOptions[0]?.dataset.sequence || 0);
                const semesterSequence = Number(fields.semester.selectedOptions[0]?.dataset.sequence || 0);
                const regularSemesters = Number(fields.semester.selectedOptions[0]?.dataset.regularSemesters || fields.university.selectedOptions[0]?.dataset.regularSemesters || 0);
                const termType = fields.semester.selectedOptions[0]?.dataset.termType || '';

                if (!stageSequence || !semesterSequence || !regularSemesters) {
                    fields.preview.textContent = 'Select a stage and semester';
                    return;
                }

                fields.preview.textContent = termType === 'summer'
                    ? 'Summer Semester'
                    : `Program Semester ${((stageSequence - 1) * regularSemesters) + semesterSequence}`;
            };

            const cascade = () => {
                const universityId = selected(fields.university);
                filterOptions(fields.academicYear, (option) => universityId !== '' && option.dataset.universityId === universityId);
                selectOnlyOption(fields.academicYear);

                const academicYearId = selected(fields.academicYear);
                filterOptions(fields.semester, (option) => universityId !== '' && academicYearId !== ''
                    && option.dataset.universityId === universityId
                    && option.dataset.academicYearId === academicYearId);
                selectOnlyOption(fields.semester);

                filterOptions(fields.college, (option) => universityId !== '' && option.dataset.universityId === universityId);
                selectOnlyOption(fields.college);

                const collegeId = selected(fields.college);
                filterOptions(fields.department, (option) => universityId !== '' && collegeId !== ''
                    && option.dataset.universityId === universityId
                    && option.dataset.collegeId === collegeId);
                selectOnlyOption(fields.department);

                const departmentId = selected(fields.department);
                const courseQuery = query(fields.courseSearch);
                filterOptions(fields.course, (option) => universityId !== '' && collegeId !== '' && departmentId !== ''
                    && option.dataset.universityId === universityId
                    && option.dataset.collegeId === collegeId
                    && option.dataset.departmentId === departmentId
                    && (courseQuery === '' || option.dataset.search.includes(courseQuery)));

                filterOptions(fields.stage, (option) => universityId !== '' && departmentId !== ''
                    && option.dataset.universityId === universityId
                    && option.dataset.departmentId === departmentId);
                selectOnlyOption(fields.stage);

                if (fields.teacher) {
                    const teacherQuery = query(fields.teacherSearch);
                    filterOptions(fields.teacher, (option) => option.dataset.universityId === universityId
                        && (teacherQuery === '' || option.dataset.search.includes(teacherQuery)));
                }

                updateProgramSemester();
            };

            [fields.university, fields.academicYear, fields.semester, fields.college, fields.department, fields.course, fields.stage, fields.teacher]
                .filter(Boolean)
                .forEach((field) => field.addEventListener('change', cascade));
            [fields.courseSearch, fields.teacherSearch]
                .filter(Boolean)
                .forEach((field) => field.addEventListener('input', cascade));

            selectOnlyOption(fields.university);
            cascade();
        });
    </script>
</x-app-layout>
