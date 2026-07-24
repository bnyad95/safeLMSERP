<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Course;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyPermission('courses.view');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->query('college_id'),
            'department_id' => $request->query('department_id'),
            'status' => in_array($request->query('status'), ['active', 'inactive'], true)
                ? $request->query('status')
                : '',
            'credits' => $request->filled('credits') ? (string) $request->query('credits') : '',
        ];

        $directoryQuery = $this->courseDirectoryQuery($filters);
        $matchingCourses = (clone $directoryQuery)->get();
        $courses = (clone $directoryQuery)->paginate(15)->withQueryString();
        $stats = [
            'total' => $matchingCourses->count(),
            'active' => $matchingCourses->where('status', 'active')->count(),
            'inactive' => $matchingCourses->where('status', 'inactive')->count(),
            'open_sections' => $matchingCourses->sum('open_sections_count'),
        ];
        $classificationGroups = $this->classifyCourses($matchingCourses);
        $colleges = College::orderBy('name')->get(['id', 'name']);
        $departments = Department::with('college')->orderBy('name')->get(['id', 'name', 'college_id']);
        $creditOptions = Course::distinct()->orderBy('credits')->pluck('credits');
        $abilities = $this->courseAbilities($request);
        $archivedCount = Course::onlyTrashed()->count();

        return view('courses.index', compact(
            'courses',
            'stats',
            'classificationGroups',
            'colleges',
            'departments',
            'creditOptions',
            'filters',
            'abilities',
            'archivedCount'
        ));
    }

    public function create()
    {
        $this->requireAnyPermission('courses.create');

        $colleges = College::orderBy('name')->get(['id', 'name']);
        $departments = Department::with('college')->orderBy('name')->get();

        return view('courses.create', compact('colleges', 'departments'));
    }

    public function store(Request $request)
    {
        $this->requireAnyPermission('courses.create');

        $validated = $this->validateCourse($request);
        $department = Department::findOrFail($validated['department_id']);
        $this->validateCode($request, $department->university_id);
        unset($validated['college_id']);

        Course::create($validated + ['university_id' => $department->university_id]);

        return redirect()->route('course-records.index')->with('success', 'Course created successfully.');
    }

    public function show(Request $request, Course $course)
    {
        $this->requireAnyPermission('courses.view');

        $course->load([
            'university',
            'department.college',
            'sections' => fn ($query) => $query
                ->with(['semester', 'teacher'])
                ->withCount(['activeEnrollments', 'assessmentItems', 'materials', 'timetables'])
                ->orderByDesc('created_at'),
        ])->loadCount(['marks', 'attendances', 'materials']);

        $summary = [
            'sections' => $course->sections->count(),
            'open_sections' => $course->sections->whereIn('status', ['planned', 'active'])->count(),
            'students' => $course->sections->sum('active_enrollments_count'),
            'assessments' => $course->sections->sum('assessment_items_count'),
        ];
        $abilities = $this->courseAbilities($request);

        return view('courses.show', compact('course', 'summary', 'abilities'));
    }

    public function edit(Course $course)
    {
        $this->requireAnyPermission('courses.update');

        $colleges = College::orderBy('name')->get(['id', 'name']);
        $departments = Department::with('college')->orderBy('name')->get();

        return view('courses.edit', compact('course', 'colleges', 'departments'));
    }

    public function update(Request $request, Course $course)
    {
        $this->requireAnyPermission('courses.update');

        $validated = $this->validateCourse($request);
        $department = Department::findOrFail($validated['department_id']);
        $this->validateCode($request, $department->university_id, $course);
        unset($validated['college_id']);

        $course->update($validated + ['university_id' => $department->university_id]);

        return redirect()->route('course-records.show', $course)->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course)
    {
        $this->requireAnyPermission('courses.archive');

        if ($course->sections()->whereIn('status', ['planned', 'active'])->exists()) {
            return back()->with('error', 'Close all planned and active sections before archiving this course.');
        }

        $course->delete();

        return redirect()->route('course-records.index')->with('success', 'Course archived successfully.');
    }

    public function archived(Request $request)
    {
        $this->requireAnyPermission('courses.archive');

        $search = trim((string) $request->query('q', ''));
        $courses = Course::onlyTrashed()
            ->with(['university', 'department.college'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('department', fn ($department) => $department
                        ->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('courses.archived', compact('courses', 'search'));
    }

    public function restore(int $courseId)
    {
        $this->requireAnyPermission('courses.archive');

        $course = Course::withTrashed()->findOrFail($courseId);
        abort_unless($course->trashed(), 404);
        $course->restore();

        return redirect()->route('course-records.archived')->with('success', 'Course restored successfully.');
    }

    private function validateCourse(Request $request): array
    {
        $request->merge(['status' => $request->input('status', 'active')]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:100'],
            'college_id' => ['required', 'exists:colleges,id'],
            'department_id' => [
                'required',
                Rule::exists('departments', 'id')
                    ->where(fn ($query) => $query->where('college_id', $request->input('college_id'))),
            ],
            'credits' => ['required', 'integer', 'min:1', 'max:10'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function validateCode(Request $request, int $universityId, ?Course $course = null): void
    {
        $request->validate([
            'code' => [
                Rule::unique('courses', 'code')
                    ->where(fn ($query) => $query->where('university_id', $universityId))
                    ->ignore($course?->id),
            ],
        ]);
    }

    private function courseDirectoryQuery(array $filters)
    {
        return Course::with(['university', 'department.college'])
            ->withCount([
                'sections',
                'sections as open_sections_count' => fn ($query) => $query->whereIn('status', ['planned', 'active']),
            ])
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($searchQuery) use ($filters) {
                $searchQuery->where('code', 'like', "%{$filters['q']}%")
                    ->orWhere('name', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['college_id'], fn ($query) => $query->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->when($filters['credits'] !== '' && ctype_digit($filters['credits']), fn ($query) => $query->where('credits', (int) $filters['credits']))
            ->orderBy('code');
    }

    private function classifyCourses($courses)
    {
        return $courses
            ->groupBy(fn (Course $course) => $course->department?->college?->name ?: 'No college')
            ->map(fn ($collegeCourses, string $collegeName) => [
                'college' => $collegeName,
                'count' => $collegeCourses->count(),
                'open_sections' => $collegeCourses->sum('open_sections_count'),
                'departments' => $collegeCourses
                    ->groupBy(fn (Course $course) => $course->department?->name ?: 'No department')
                    ->map(fn ($departmentCourses, string $departmentName) => [
                        'department' => $departmentName,
                        'count' => $departmentCourses->count(),
                        'open_sections' => $departmentCourses->sum('open_sections_count'),
                    ])
                    ->sortBy('department')
                    ->values(),
            ])
            ->sortBy('college')
            ->values();
    }

    private function courseAbilities(Request $request): array
    {
        $user = $request->user();
        $isSuper = $user->hasRole('super_administrator');

        return [
            'create' => $isSuper || $user->hasPermission('courses.create'),
            'update' => $isSuper || $user->hasPermission('courses.update'),
            'archive' => $isSuper || $user->hasPermission('courses.archive'),
        ];
    }
}
