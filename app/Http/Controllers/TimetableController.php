<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TimetableController extends Controller
{
    public function index(Request $request)
    {
        if (! $request->user()?->hasRole('student')) {
            $this->requireAnyPermission('timetable.view', 'timetable.manage');
        }

        $canManageTimetable = $request->user()?->hasRole('super_administrator') || $request->user()?->hasPermission('timetable.manage');

        if ($request->user()?->hasRole('student') && ! $canManageTimetable) {
            $student = Student::where('email', $request->user()->email)->first();
            $sectionIds = $student
                ? $student->enrollments()
                    ->where('status', 'enrolled')
                    ->whereHas('courseSection', fn ($query) => $query->whereIn('status', ['planned', 'active']))
                    ->pluck('course_section_id')
                : collect();
            $studentTimetableEntries = Timetable::with([
                'course.department',
                'courseSection.semester',
                'teacher',
                'classroom',
                'timeSlot',
            ])
                ->whereIn('course_section_id', $sectionIds)
                ->orderByRaw("case day_of_week when 'Sunday' then 1 when 'Monday' then 2 when 'Tuesday' then 3 when 'Wednesday' then 4 when 'Thursday' then 5 when 'Friday' then 6 when 'Saturday' then 7 else 8 end")
                ->orderBy('start_time')
                ->get();

            return view('timetables.index', [
                'isStudentTimetable' => true,
                'isTeacherTimetable' => false,
                'student' => $student,
                'studentTimetableEntries' => $studentTimetableEntries,
                'studentTimetablesByDay' => $studentTimetableEntries->groupBy('day_of_week'),
                'days' => Timetable::getDays(),
                'canManageTimetable' => false,
            ]);
        }

        if ($request->user()?->hasRole('teacher') && ! $canManageTimetable) {
            $teacher = Teacher::where('email', $request->user()->email)->first();
            $teacherTimetableEntries = Timetable::with([
                'course.department',
                'courseSection.semester',
                'classroom',
                'timeSlot',
            ])
                ->when($teacher, fn ($query) => $query->where('teacher_id', $teacher->id))
                ->when(! $teacher, fn ($query) => $query->whereRaw('1 = 0'))
                ->whereHas('courseSection', fn ($query) => $query->whereIn('status', ['planned', 'active']))
                ->orderByRaw("case day_of_week when 'Sunday' then 1 when 'Monday' then 2 when 'Tuesday' then 3 when 'Wednesday' then 4 when 'Thursday' then 5 when 'Friday' then 6 when 'Saturday' then 7 else 8 end")
                ->orderBy('start_time')
                ->get();

            return view('timetables.index', [
                'isStudentTimetable' => false,
                'isTeacherTimetable' => true,
                'teacher' => $teacher,
                'teacherTimetableEntries' => $teacherTimetableEntries,
                'teacherTimetablesByDay' => $teacherTimetableEntries->groupBy('day_of_week'),
                'days' => Timetable::getDays(),
                'canManageTimetable' => false,
            ]);
        }

        $filters = [
            'department_id' => $request->integer('department_id') ?: null,
            'grade_level' => trim((string) $request->query('grade_level', '')),
            'semester_id' => $request->integer('semester_id') ?: null,
            'group' => trim((string) $request->query('group', '')),
            'teacher_id' => $request->integer('teacher_id') ?: null,
            'classroom_id' => $request->integer('classroom_id') ?: null,
            'day_of_week' => in_array($request->query('day_of_week'), Timetable::getDays(), true) ? $request->query('day_of_week') : '',
            'type' => in_array($request->query('type'), Timetable::types(), true) ? $request->query('type') : '',
            'status' => in_array($request->query('status'), ['scheduled', 'cancelled'], true) ? $request->query('status') : '',
        ];

        $entries = Timetable::with([
            'course.department',
            'courseSection.semester',
            'courseSection.activeEnrollments',
            'teacher',
            'classroom',
            'timeSlot',
        ])
            ->when($filters['department_id'], fn ($query, $departmentId) => $query->whereHas('course', fn ($courseQuery) => $courseQuery->where('department_id', $departmentId)))
            ->when($filters['grade_level'] !== '', fn ($query) => $query->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('grade_level', $filters['grade_level'])))
            ->when($filters['semester_id'], fn ($query, $semesterId) => $query->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('semester_id', $semesterId)))
            ->when($filters['group'] !== '', fn ($query) => $query->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery->where('section_code', $filters['group'])))
            ->when($filters['teacher_id'], fn ($query, $teacherId) => $query->where('teacher_id', $teacherId))
            ->when($filters['classroom_id'], fn ($query, $classroomId) => $query->where('classroom_id', $classroomId))
            ->when($filters['day_of_week'] !== '', fn ($query) => $query->where('day_of_week', $filters['day_of_week']))
            ->when($filters['type'] !== '', fn ($query) => $query->where('type', $filters['type']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderByRaw("case day_of_week when 'Sunday' then 1 when 'Monday' then 2 when 'Tuesday' then 3 when 'Wednesday' then 4 when 'Thursday' then 5 when 'Friday' then 6 when 'Saturday' then 7 else 8 end")
            ->orderBy('start_time')
            ->get();

        $timetables = $entries->groupBy('day_of_week');

        return view('timetables.index', [
            'timetables' => $timetables,
            'isStudentTimetable' => false,
            'isTeacherTimetable' => false,
            'classifiedTimetables' => $this->classifiedTimetables($entries),
            'weeklyGrid' => $this->weeklyGrid($entries),
            'canManageTimetable' => $canManageTimetable,
            'sections' => CourseSection::with(['course.department', 'semester', 'teacher'])
                ->withCount(['activeEnrollments as enrolled_count'])
                ->whereIn('status', ['planned', 'active'])
                ->whereHas('semester.academicYear', fn ($query) => $query->whereIn('status', ['upcoming', 'active']))
                ->when($filters['department_id'], fn ($query, $departmentId) => $query->whereHas('course', fn ($courseQuery) => $courseQuery->where('department_id', $departmentId)))
                ->when($filters['grade_level'] !== '', fn ($query) => $query->where('grade_level', $filters['grade_level']))
                ->orderByDesc('created_at')
                ->get(),
            'departments' => Department::orderBy('name')->get(),
            'semesters' => Semester::orderByDesc('academic_year')->orderBy('name')->get(),
            'gradeOptions' => CourseSection::whereNotNull('grade_level')->distinct()->orderBy('grade_level')->pluck('grade_level'),
            'groupOptions' => CourseSection::distinct()->orderBy('section_code')->pluck('section_code'),
            'classrooms' => Classroom::orderBy('name')->get(),
            'availableClassrooms' => Classroom::where('status', 'available')->orderBy('name')->get(),
            'teachers' => Teacher::where('status', 'Active')->orderBy('full_name')->get(['id', 'full_name']),
            'universities' => University::orderBy('name')->get(['id', 'name', 'code']),
            'days' => Timetable::getDays(),
            'types' => Timetable::types(),
            'filters' => $filters,
        ]);
    }

    public function store(Request $request)
    {
        $this->requireAnyPermission('timetable.manage');

        $prepared = $this->prepareTimetableData($request);
        if (! $prepared['ok']) {
            return back()->withInput()->with('error', $prepared['message']);
        }

        Timetable::create([
            ...$prepared['data'],
        ]);

        return redirect()->route('timetables.index')->with('success', __('Timetable entry scheduled.'));
    }

    public function update(Request $request, Timetable $timetable)
    {
        $this->requireAnyPermission('timetable.manage');

        $timetable->load('courseSection.semester.academicYear');
        if ($timetable->courseSection?->semester?->academicYear?->isLocked()) {
            return back()->with('error', __('Timetable entries in a closed academic year are read-only.'));
        }

        $prepared = $this->prepareTimetableData($request, $timetable);
        if (! $prepared['ok']) {
            return back()->withInput()->with('error', $prepared['message']);
        }

        $timetable->update($prepared['data']);

        return redirect()->route('timetables.index')->with('success', __('Timetable entry updated.'));
    }

    public function storeRoom(Request $request)
    {
        $this->requireAnyPermission('timetable.manage');

        $validated = $request->validate([
            'university_id' => ['nullable', 'integer', 'exists:universities,id'],
            'name' => ['required', 'string', 'max:100'],
            'building' => ['nullable', 'string', 'max:100'],
            'capacity' => ['required', 'integer', 'min:1', 'max:1000'],
            'type' => ['required', Rule::in(['classroom', 'lab', 'hall', 'online'])],
            'status' => ['required', Rule::in(['available', 'maintenance', 'inactive'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $universityId = $user->university_id
            ?: ($validated['university_id'] ?? null)
            ?: (University::count() === 1 ? University::value('id') : null);
        if (! $universityId) {
            throw ValidationException::withMessages([
                'university_id' => __('Choose the university that owns this room.'),
            ]);
        }

        $university = University::whereKey($universityId)->firstOrFail();
        $duplicate = Classroom::where('university_id', $university->id)
            ->where('name', $validated['name'])
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => __('This university already has a room with that name.'),
            ]);
        }

        Classroom::create(array_merge($validated, ['university_id' => $university->id]));

        return redirect()->route('timetables.index')->with('success', __('Room added.'));
    }

    public function destroy(Timetable $timetable)
    {
        $this->requireAnyPermission('timetable.manage');

        $timetable->load('courseSection.semester.academicYear');
        if ($timetable->courseSection?->semester?->academicYear?->isLocked()) {
            return back()->with('error', __('Timetable entries in a closed academic year are read-only.'));
        }

        $timetable->delete();

        return redirect()->route('timetables.index')->with('success', __('Timetable entry removed.'));
    }

    private function prepareTimetableData(Request $request, ?Timetable $existing = null): array
    {
        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'grade_level' => ['required', 'string', 'max:50'],
            'course_section_id' => ['required', 'exists:course_sections,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'day_of_week' => ['required', Rule::in(Timetable::getDays())],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'type' => ['required', Rule::in(Timetable::types())],
            'status' => ['required', Rule::in(['scheduled', 'cancelled'])],
        ]);

        $department = Department::findOrFail($validated['department_id']);
        $section = CourseSection::with(['course.department', 'teacher', 'activeEnrollments', 'semester.academicYear'])
            ->findOrFail($validated['course_section_id']);
        $classroom = Classroom::findOrFail($validated['classroom_id']);
        $startTime = $validated['start_time'];
        $endTime = $validated['end_time'];

        if ($section->course?->department_id !== $department->id) {
            return ['ok' => false, 'message' => __('The selected module must belong to the selected department.')];
        }

        if (($section->grade_level ?: '') !== $validated['grade_level']) {
            return ['ok' => false, 'message' => __('The selected module must match the selected stage.')];
        }

        $sectionUniversityId = $section->course?->department?->university_id;
        if ($classroom->university_id && (int) $classroom->university_id !== (int) $sectionUniversityId) {
            return ['ok' => false, 'message' => __('The selected room must belong to the same university as the module.')];
        }

        if ($section->semester?->academicYear?->isLocked()) {
            return ['ok' => false, 'message' => __('Timetable entries in a closed academic year are read-only.')];
        }

        if ($startTime >= $endTime) {
            return ['ok' => false, 'message' => __('End time must be after start time.')];
        }

        if ($validated['status'] === 'scheduled') {
            if ($classroom->status !== 'available') {
                return ['ok' => false, 'message' => __('Only available rooms can receive scheduled timetable entries.')];
            }

            if ($classroom->capacity < $section->activeEnrollments->count()) {
                return ['ok' => false, 'message' => __('Room capacity is lower than the enrolled student count for this module.')];
            }

            $conflicts = $this->conflictsFor($section, $classroom, $validated['day_of_week'], $startTime, $endTime, $existing?->id);

            if ($conflicts->isNotEmpty()) {
                return ['ok' => false, 'message' => __('Timetable conflict: :conflicts', ['conflicts' => $conflicts->pluck('label')->unique()->take(3)->implode('; ')])];
            }
        }

        return [
            'ok' => true,
            'data' => [
                'course_id' => $section->course_id,
                'course_section_id' => $section->id,
                'teacher_id' => $section->teacher_id,
                'classroom_id' => $classroom->id,
                'time_slot_id' => null,
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $startTime,
                'end_time' => $endTime,
                'room_number' => $classroom->name,
                'type' => $validated['type'],
                'status' => $validated['status'],
            ],
        ];
    }

    private function conflictsFor(CourseSection $section, Classroom $classroom, string $dayOfWeek, string $startTime, string $endTime, ?int $ignoredTimetableId = null)
    {
        // Conflict detection must see across org boundaries (e.g. shared rooms used by
        // other departments) even though the rest of the page scopes Timetable to the
        // acting user's own organization. The eager-loaded relations and the nested
        // whereHas below carry their own models' organization scopes too, so those are
        // lifted as well or a conflicting entry from another department would still be
        // invisible to this check (or resolve to a blank label).
        $unscopedRelations = [
            'course' => fn ($query) => $query->withoutGlobalScope('organization'),
            'courseSection' => fn ($query) => $query->withoutGlobalScope('organization'),
            'teacher' => fn ($query) => $query->withoutGlobalScope('organization'),
            'classroom' => fn ($query) => $query->withoutGlobalScope('organization'),
        ];

        $baseConflicts = Timetable::withoutGlobalScope('organization')
            ->with($unscopedRelations)
            ->overlapping($dayOfWeek, $startTime, $endTime)
            ->where('status', 'scheduled')
            ->when($ignoredTimetableId, fn ($query) => $query->whereKeyNot($ignoredTimetableId))
            ->where(function ($query) use ($section, $classroom) {
                $query->where('classroom_id', $classroom->id)
                    ->orWhere('course_section_id', $section->id);

                if ($section->teacher_id) {
                    $query->orWhere('teacher_id', $section->teacher_id);
                }
            })
            ->get()
            ->map(fn (Timetable $entry) => [
                'label' => $this->conflictLabel($entry, $section, $classroom),
            ])
            ->toBase();

        $studentIds = $section->activeEnrollments->pluck('student_id');

        if ($studentIds->isEmpty()) {
            return $baseConflicts;
        }

        $studentConflicts = Timetable::withoutGlobalScope('organization')
            ->with($unscopedRelations)
            ->overlapping($dayOfWeek, $startTime, $endTime)
            ->where('status', 'scheduled')
            ->when($ignoredTimetableId, fn ($query) => $query->whereKeyNot($ignoredTimetableId))
            ->where('course_section_id', '!=', $section->id)
            ->whereHas('courseSection', function ($query) use ($studentIds) {
                $query->withoutGlobalScope('organization')
                    ->whereHas('activeEnrollments', function ($enrollments) use ($studentIds) {
                        $enrollments->withoutGlobalScope('organization')->whereIn('student_id', $studentIds);
                    });
            })
            ->get()
            ->map(fn (Timetable $entry) => [
                'label' => __('enrolled student overlap with :entry', ['entry' => $this->entryName($entry)]),
            ])
            ->toBase();

        return $baseConflicts->merge($studentConflicts);
    }

    private function conflictLabel(Timetable $entry, CourseSection $section, Classroom $classroom): string
    {
        if ($entry->classroom_id === $classroom->id) {
            return __('room :room overlaps with :entry', ['room' => $classroom->name, 'entry' => $this->entryName($entry)]);
        }

        if ($entry->teacher_id && $entry->teacher_id === $section->teacher_id) {
            return __('teacher overlaps with :entry', ['entry' => $this->entryName($entry)]);
        }

        if ($entry->course_section_id === $section->id) {
            return __('module overlaps with another time for :entry', ['entry' => $this->entryName($entry)]);
        }

        return __('overlap with :entry', ['entry' => $this->entryName($entry)]);
    }

    private function entryName(Timetable $entry): string
    {
        $sectionCode = $entry->courseSection?->section_code ? ' '.$entry->courseSection->section_code : '';

        return trim(($entry->course?->code ?? __('course')).$sectionCode.' '.$entry->day_of_week.' '.substr((string) $entry->start_time, 0, 5).'-'.substr((string) $entry->end_time, 0, 5));
    }

    private function classifiedTimetables($entries)
    {
        return $entries
            ->groupBy(fn (Timetable $entry) => $entry->course->department->name ?? __('No department'))
            ->map(function ($departmentEntries, string $departmentName) {
                return [
                    'department' => $departmentName,
                    'count' => $departmentEntries->count(),
                    'grades' => $departmentEntries
                        ->groupBy(fn (Timetable $entry) => $entry->courseSection?->grade_level ?: __('No stage'))
                        ->map(function ($gradeEntries, string $gradeName) {
                            return [
                                'grade' => $gradeName,
                                'count' => $gradeEntries->count(),
                                'semesters' => $gradeEntries
                                    ->groupBy(fn (Timetable $entry) => trim(($entry->courseSection?->semester?->name ?? __('No semester')).' '.($entry->courseSection?->semester?->academic_year ?? '')))
                                    ->map(function ($semesterEntries, string $semesterName) {
                                        return [
                                            'semester' => $semesterName,
                                            'count' => $semesterEntries->count(),
                                            'groups' => $semesterEntries
                                                ->groupBy(fn (Timetable $entry) => $entry->courseSection?->section_code ?? __('No group'))
                                                ->map(fn ($groupEntries, string $groupName) => [
                                                    'group' => $groupName,
                                                    'count' => $groupEntries->count(),
                                                    'days' => $groupEntries->groupBy('day_of_week'),
                                                ])
                                                ->values(),
                                        ];
                                    })
                                    ->values(),
                            ];
                        })
                        ->values(),
                ];
            })
            ->values();
    }

    private function weeklyGrid($entries)
    {
        return $entries
            ->groupBy(fn (Timetable $entry) => substr((string) $entry->start_time, 0, 5).'-'.substr((string) $entry->end_time, 0, 5))
            ->sortKeys()
            ->map(fn ($slotEntries, string $slot) => [
                'slot' => $slot,
                'days' => $slotEntries->groupBy('day_of_week'),
            ])
            ->values();
    }
}
