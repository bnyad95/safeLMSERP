<?php

namespace App\Http\Controllers;

use App\Models\AssessmentItem;
use App\Models\Attendance;
use App\Models\Course;
use App\Models\CourseMaterial;
use App\Models\CourseSection;
use App\Models\Mark;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\Timetable;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeacherDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = Auth::user();

        $canManageClassroom = $user->hasRole('teacher');
        $canInspectClassrooms = $user->hasAnyRole([
            'administrator',
            'super_administrator',
            'university_administrator',
            'college_administrator',
            'department_administrator',
            'lms_administrator',
            'teaching_assistant',
        ]);

        if (! $canManageClassroom && ! $canInspectClassrooms) {
            abort(403);
        }

        $teacher = $canManageClassroom
            ? Teacher::where('email', $user->email)->first()
            : null;

        if ($canManageClassroom && ! $teacher) {
            abort(403);
        }

        $tabAliases = [
            'students' => 'people',
            'assessments' => 'classwork',
            'materials' => 'classwork',
            'marks' => 'grades',
        ];
        $requestedTab = $tabAliases[$request->query('tab')] ?? $request->query('tab');
        $activeTab = in_array($requestedTab, ['stream', 'classwork', 'people', 'grades', 'attendance', 'timetable', 'analytics'], true)
            ? $requestedTab
            : 'stream';

        $filters = [
            'section_id' => $request->integer('section_id') ?: null,
            'group' => trim((string) $request->query('group', '')),
            'college_id' => $request->integer('college_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'grade' => trim((string) ($request->query('stage') ?? $request->query('grade', ''))),
            'tab' => $activeTab,
        ];

        $assignedSectionsQuery = CourseSection::with(['course.department.college', 'semester', 'teacher.department'])
            ->withCount(['activeEnrollments as enrolled_count', 'assessmentItems as assessment_count'])
            ->whereIn('status', ['planned', 'active'])
            ->when($canManageClassroom, fn ($query) => $query->where('teacher_id', $teacher->id));

        if (! $canManageClassroom) {
            OrganizationScope::apply($assignedSectionsQuery, $user, 'section');
        }

        $assignedSections = (clone $assignedSectionsQuery)
            ->orderByDesc('created_at')
            ->get();

        $requestedSection = $filters['section_id']
            ? $assignedSections->firstWhere('id', $filters['section_id'])
            : null;
        abort_if($filters['section_id'] && ! $requestedSection, 404);

        $classroomHierarchy = null;
        if (! $canManageClassroom) {
            $sectionCollegeId = $requestedSection?->course?->department?->college_id;
            $sectionDepartmentId = $requestedSection?->course?->department_id;
            $sectionGrade = $requestedSection
                ? ($requestedSection->grade_level ?: '__unassigned__')
                : null;

            $selectedCollegeId = $sectionCollegeId ?: $filters['college_id'];
            $selectedDepartmentId = $sectionDepartmentId ?: $filters['department_id'];
            $selectedGrade = $sectionGrade ?: ($filters['grade'] ?: null);

            $collegeCards = $assignedSections
                ->filter(fn (CourseSection $section) => $section->course?->department?->college)
                ->groupBy(fn (CourseSection $section) => $section->course->department->college_id)
                ->map(function ($sections) {
                    return [
                        'college' => $sections->first()->course->department->college,
                        'department_count' => $sections->pluck('course.department_id')->unique()->count(),
                        'class_count' => $sections->count(),
                    ];
                })
                ->sortBy(fn ($card) => $card['college']->name)
                ->values();
            $selectedCollege = $collegeCards->first(fn ($card) => $card['college']->id === $selectedCollegeId)['college'] ?? null;
            abort_if($filters['college_id'] && ! $selectedCollege, 404);

            $collegeSections = $selectedCollege
                ? $assignedSections->filter(fn (CourseSection $section) => $section->course?->department?->college_id === $selectedCollege->id)
                : collect();
            $departmentCards = $collegeSections
                ->groupBy(fn (CourseSection $section) => $section->course->department_id)
                ->map(function ($sections) {
                    return [
                        'department' => $sections->first()->course->department,
                        'grade_count' => $sections->map(fn (CourseSection $section) => $section->grade_level ?: '__unassigned__')->unique()->count(),
                        'class_count' => $sections->count(),
                    ];
                })
                ->sortBy(fn ($card) => $card['department']->name)
                ->values();
            $selectedDepartment = $departmentCards->first(fn ($card) => $card['department']->id === $selectedDepartmentId)['department'] ?? null;
            abort_if($filters['department_id'] && ! $selectedDepartment, 404);

            $departmentSections = $selectedDepartment
                ? $collegeSections->filter(fn (CourseSection $section) => $section->course->department_id === $selectedDepartment->id)
                : collect();
            $gradeCards = $departmentSections
                ->groupBy(fn (CourseSection $section) => $section->grade_level ?: '__unassigned__')
                ->map(function ($sections, $grade) {
                    return [
                        'key' => $grade,
                        'label' => $grade === '__unassigned__' ? 'Stage not specified' : $grade,
                        'class_count' => $sections->count(),
                        'student_count' => $sections->sum('enrolled_count'),
                    ];
                })
                ->sortBy('label')
                ->values();
            $selectedGradeCard = $gradeCards->first(fn ($card) => $card['key'] === $selectedGrade)
                ?? $gradeCards->first(fn ($card) => $this->gradeAlias($card['key']) === $this->gradeAlias($selectedGrade))
                ?? ($this->stageOrdinal($selectedGrade) ? $gradeCards->values()->get($this->stageOrdinal($selectedGrade) - 1) : null);
            abort_if($filters['grade'] && ! $selectedGradeCard, 404);

            $classSections = collect();
            if ($selectedGradeCard) {
                $matchedByAlias = $selectedGrade && $selectedGrade !== $selectedGradeCard['key'];
                $classSections = $departmentSections
                    ->filter(function (CourseSection $section) use ($selectedGradeCard, $matchedByAlias) {
                        $sectionStage = $section->grade_level ?: '__unassigned__';

                        return $matchedByAlias
                            ? $this->gradeAlias($sectionStage) === $this->gradeAlias($selectedGradeCard['key'])
                            : $sectionStage === $selectedGradeCard['key'];
                    })
                    ->sortBy(fn (CourseSection $section) => ($section->course->name ?? '').' '.$section->section_code)
                    ->values();
            }

            $classroomHierarchy = [
                'colleges' => $collegeCards,
                'selected_college' => $selectedCollege,
                'departments' => $departmentCards,
                'selected_department' => $selectedDepartment,
                'grades' => $gradeCards,
                'selected_grade' => $selectedGradeCard,
                'classes' => $classSections,
            ];
        }

        $groupOptions = $assignedSections
            ->pluck('section_code')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        $filteredSections = (clone $assignedSectionsQuery)
            ->when($filters['section_id'], fn ($query) => $query->whereKey($filters['section_id']))
            ->when($filters['group'] !== '', fn ($query) => $query->where('section_code', $filters['group']))
            ->when(! $canManageClassroom && ! $filters['section_id'], fn ($query) => $query->whereRaw('1 = 0'))
            ->orderByDesc('created_at')
            ->get();

        $filteredSectionIds = $filteredSections->pluck('id');
        $filteredCourseIds = $filteredSections->pluck('course_id')->unique();
        $filteredStudentIds = Student::whereHas('enrollments', fn ($query) => $query
            ->where('status', 'enrolled')
            ->whereIn('course_section_id', $filteredSectionIds))
            ->pluck('id');

        $courses = $teacher
            ? Course::whereIn('id', $filteredCourseIds)->with('department')->paginate(10)->withQueryString()
            : Course::whereIn('id', $filteredCourseIds)->with('department')->paginate(10)->withQueryString();

        $recentMarks = Mark::with(['student', 'course'])
            ->whereIn('course_id', $filteredCourseIds)
            ->whereIn('student_id', $filteredStudentIds)
            ->where(function ($query) use ($filteredSectionIds) {
                $query->whereIn('course_section_id', $filteredSectionIds)
                    ->orWhereNull('course_section_id');
            })
            ->latest()
            ->limit(10)
            ->get();

        $classMarks = Mark::whereIn('course_id', $filteredCourseIds)
            ->whereIn('student_id', $filteredStudentIds)
            ->where(function ($query) use ($filteredSectionIds) {
                $query->whereIn('course_section_id', $filteredSectionIds)
                    ->orWhereNull('course_section_id');
            })
            ->get()
            ->keyBy('student_id');

        $classStudents = Student::with(['department', 'enrollments.courseSection.course'])
            ->whereIn('id', $filteredStudentIds)
            ->orderBy('full_name')
            ->get();
        $classStudentUsers = User::whereIn('email', $classStudents->pluck('email'))->get()->keyBy('email');

        $teacherAssessments = AssessmentItem::with(['courseSection.course', 'courseSection.semester', 'submissions'])
            ->withCount('submissions')
            ->whereIn('course_section_id', $filteredSectionIds)
            ->orderByRaw('due_at is null, due_at asc')
            ->latest()
            ->get();

        $classMaterials = CourseMaterial::whereIn('course_id', $filteredCourseIds)
            ->where(function ($query) use ($filteredSectionIds) {
                $query->whereIn('course_section_id', $filteredSectionIds)
                    ->orWhereNull('course_section_id');
            })
            ->latest()
            ->limit(8)
            ->get();

        $upcomingAssessments = $teacherAssessments
            ->where('status', 'published')
            ->filter(fn (AssessmentItem $assessment) => $assessment->due_at && $assessment->due_at->isFuture())
            ->sortBy('due_at')
            ->take(5);

        $streamPosts = collect();
        if ($filters['section_id'] && $selectedSection = $filteredSections->first()) {
            $streamPosts = app(ClassStreamController::class)->postsFor($selectedSection, $user->id);
        }

        $classAttendances = Attendance::whereIn('course_section_id', $filteredSectionIds)
            ->whereIn('student_id', $filteredStudentIds)
            ->get();
        $classTimetableEntries = Timetable::with(['classroom', 'timeSlot'])
            ->whereIn('course_section_id', $filteredSectionIds)
            ->orderByRaw("case day_of_week when 'Monday' then 1 when 'Tuesday' then 2 when 'Wednesday' then 3 when 'Thursday' then 4 when 'Friday' then 5 when 'Saturday' then 6 else 7 end")
            ->orderBy('start_time')
            ->get();
        $attendanceRisk = $classStudents->map(function (Student $student) use ($classAttendances) {
            $records = $classAttendances->where('student_id', $student->id);
            $total = $records->count();
            $present = $records->whereIn('status', ['present', 'late', 'excused'])->count();
            $rate = $total ? round(($present / $total) * 100, 1) : null;

            return [
                'student' => $student,
                'total' => $total,
                'absent' => $records->where('status', 'absent')->count(),
                'rate' => $rate,
                'risk' => is_null($rate) ? 'No data' : ($rate < 75 ? 'High' : ($rate < 85 ? 'Watch' : 'Normal')),
            ];
        })->sortBy(fn ($row) => $row['rate'] ?? 101)->values();
        $recordedAttendance = $attendanceRisk->whereNotNull('rate');
        $classAnalytics = [
            'attendance_rate' => $recordedAttendance->isNotEmpty() ? round((float) $recordedAttendance->avg('rate'), 1) : null,
            'at_risk' => $attendanceRisk->whereIn('risk', ['High', 'Watch'])->count(),
            'prefinal_average' => $classMarks->whereNotNull('prefinal_mark')->isNotEmpty() ? round((float) $classMarks->whereNotNull('prefinal_mark')->avg('prefinal_mark'), 1) : null,
            'final_average' => $classMarks->where('final_mark', '>', 0)->isNotEmpty() ? round((float) $classMarks->where('final_mark', '>', 0)->avg('final_mark'), 1) : null,
        ];
        $assessmentAnalytics = $teacherAssessments->map(function (AssessmentItem $assessment) {
            $graded = $assessment->submissions->whereNotNull('score');

            return [
                'assessment' => $assessment,
                'submitted' => $assessment->submissions->count(),
                'graded' => $graded->count(),
                'average' => $graded->isNotEmpty() ? round(((float) $graded->avg('score') / max((float) $assessment->max_score, 1)) * 100, 1) : null,
            ];
        });

        $stats = [
            'total_courses' => $courses->total(),
            'total_sections' => $filteredSections->count(),
            'total_students' => $filteredStudentIds->count(),
            'marks_entered' => Mark::whereIn('course_id', $filteredCourseIds)->whereIn('student_id', $filteredStudentIds)->where(fn ($query) => $query->whereIn('course_section_id', $filteredSectionIds)->orWhereNull('course_section_id'))->where('final_mark', '>', 0)->count(),
            'pending_marks' => Mark::whereIn('course_id', $filteredCourseIds)->whereIn('student_id', $filteredStudentIds)->where(fn ($query) => $query->whereIn('course_section_id', $filteredSectionIds)->orWhereNull('course_section_id'))->where(fn ($query) => $query->whereNull('final_mark')->orWhere('final_mark', '<=', 0))->count(),
            'assessment_items' => AssessmentItem::whereIn('course_section_id', $filteredSectionIds)->count(),
            'prefinal_marks_entered' => $classMarks->whereNotNull('prefinal_mark')->count(),
        ];

        return view('teacher.dashboard', compact('teacher', 'canManageClassroom', 'classroomHierarchy', 'courses', 'recentMarks', 'classMarks', 'stats', 'assignedSections', 'filteredSections', 'groupOptions', 'filters', 'classStudents', 'classStudentUsers', 'teacherAssessments', 'classMaterials', 'upcomingAssessments', 'streamPosts', 'attendanceRisk', 'classAnalytics', 'assessmentAnalytics', 'classTimetableEntries'));
    }

    private function gradeAlias(?string $grade): ?string
    {
        if (! $grade || $grade === '__unassigned__') {
            return $grade;
        }

        $normalized = strtolower(trim($grade));

        if (preg_match('/\d+/', $normalized, $matches)) {
            return $matches[0];
        }

        $ordinals = [
            'first' => '1',
            'one' => '1',
            'second' => '2',
            'two' => '2',
            'third' => '3',
            'three' => '3',
            'fourth' => '4',
            'four' => '4',
            'fifth' => '5',
            'five' => '5',
            'sixth' => '6',
            'six' => '6',
        ];

        foreach ($ordinals as $word => $number) {
            if (str_contains($normalized, $word)) {
                return $number;
            }
        }

        return preg_replace('/[^a-z0-9]+/', '', $normalized);
    }

    private function stageOrdinal(?string $stage): ?int
    {
        $alias = $this->gradeAlias($stage);

        return ctype_digit((string) $alias) ? max((int) $alias, 1) : null;
    }
}
