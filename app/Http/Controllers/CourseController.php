<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request)
    {
        $this->requireCatalogAccess($request, 'courses.view');
        $user = $request->user();

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->query('college_id'),
            'department_id' => $request->query('department_id'),
            'status' => in_array($request->query('status'), ['active', 'inactive'], true)
                ? $request->query('status')
                : '',
            'credits' => $request->filled('credits') ? (string) $request->query('credits') : '',
        ];

        $directoryQuery = $this->courseDirectoryQuery($filters, $user);
        $courses = (clone $directoryQuery)->paginate(15)->withQueryString();
        $classificationGroups = $this->courseClassificationGroups($filters, $user);
        $stats = [
            'total' => (clone $directoryQuery)->reorder()->count(),
            'active' => (clone $directoryQuery)->reorder()->where('status', 'active')->count(),
            'inactive' => (clone $directoryQuery)->reorder()->where('status', 'inactive')->count(),
            'open_sections' => $classificationGroups->sum('open_sections'),
        ];
        [$colleges, $departments] = $this->organizationOptions($user);
        $creditQuery = Course::query();
        OrganizationScope::apply($creditQuery, $user, 'course');
        $creditOptions = $creditQuery->distinct()->orderBy('credits')->pluck('credits');
        $abilities = $this->courseAbilities($request);
        $archivedQuery = Course::onlyTrashed();
        OrganizationScope::apply($archivedQuery, $user, 'course');
        $archivedCount = $archivedQuery->count();

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
        $this->requireCatalogAccess(request(), 'courses.create', true);

        [$colleges, $departments] = $this->organizationOptions(request()->user());

        return view('courses.create', compact('colleges', 'departments'));
    }

    public function store(Request $request)
    {
        $this->requireCatalogAccess($request, 'courses.create', true);

        $validated = $this->validateCourse($request);
        $department = $this->scopedDepartment((int) $validated['department_id'], $request->user());
        $this->validateCode($request, $department->university_id);
        unset($validated['college_id']);

        Course::create($validated + ['university_id' => $department->university_id]);

        return redirect()->route('course-records.index')->with('success', 'Course created successfully.');
    }

    public function show(Request $request, Course $course)
    {
        $this->requireCatalogAccess($request, 'courses.view');
        $this->authorizeCourse($course, $request->user());

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
        $this->requireCatalogAccess(request(), 'courses.update', true);
        $this->authorizeCourse($course, request()->user());

        [$colleges, $departments] = $this->organizationOptions(request()->user());

        return view('courses.edit', compact('course', 'colleges', 'departments'));
    }

    public function update(Request $request, Course $course)
    {
        $this->requireCatalogAccess($request, 'courses.update', true);
        $this->authorizeCourse($course, $request->user());

        $validated = $this->validateCourse($request);
        $department = $this->scopedDepartment((int) $validated['department_id'], $request->user());
        $this->validateCode($request, $department->university_id, $course);
        unset($validated['college_id']);

        if ((int) $course->department_id !== (int) $department->id
            && $course->sections()->withTrashed()->exists()) {
            return back()->withInput()->with('error', 'A catalog course with module offerings cannot be moved to another department.');
        }

        $course->update($validated + ['university_id' => $department->university_id]);

        return redirect()->route('course-records.show', $course)->with('success', 'Course updated successfully.');
    }

    public function destroy(Course $course)
    {
        $this->requireCatalogAccess(request(), 'courses.archive', true);
        $this->authorizeCourse($course, request()->user());

        if ($course->sections()->whereIn('status', ['planned', 'active'])->exists()) {
            return back()->with('error', 'Close all planned and active sections before archiving this course.');
        }

        $course->delete();

        return redirect()->route('course-records.index')->with('success', 'Course archived successfully.');
    }

    public function archived(Request $request)
    {
        $this->requireCatalogAccess($request, 'courses.archive', true);

        $search = trim((string) $request->query('q', ''));
        $coursesQuery = Course::onlyTrashed()
            ->with(['university', 'department.college'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('department', fn ($department) => $department
                        ->where('name', 'like', "%{$search}%"));
            }))
            ->orderByDesc('deleted_at');
        OrganizationScope::apply($coursesQuery, $request->user(), 'course');
        $courses = $coursesQuery
            ->paginate(15)
            ->withQueryString();

        return view('courses.archived', compact('courses', 'search'));
    }

    public function restore(int $courseId)
    {
        $this->requireCatalogAccess(request(), 'courses.archive', true);

        $query = Course::withTrashed()->whereKey($courseId);
        OrganizationScope::apply($query, request()->user(), 'course');
        $course = $query->firstOrFail();
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
            'credits' => ['required', 'numeric', 'min:0.5', 'max:30'],
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

    private function courseDirectoryQuery(array $filters, User $user)
    {
        $query = Course::with(['university', 'department.college'])
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
            ->when($filters['credits'] !== '' && ctype_digit($filters['credits']), fn ($query) => $query->where('credits', (int) $filters['credits']));
        OrganizationScope::apply($query, $user, 'course');

        return $query->orderBy('code');
    }

    private function courseClassificationGroups(array $filters, User $user)
    {
        $rowsQuery = Course::query()
            ->leftJoin('departments', 'courses.department_id', '=', 'departments.id')
            ->leftJoin('colleges', 'departments.college_id', '=', 'colleges.id')
            ->leftJoin('course_sections', function ($join) {
                $join->on('course_sections.course_id', '=', 'courses.id')
                    ->whereIn('course_sections.status', ['planned', 'active'])
                    ->whereNull('course_sections.deleted_at');
            })
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($searchQuery) use ($filters) {
                $searchQuery->where('courses.code', 'like', "%{$filters['q']}%")
                    ->orWhere('courses.name', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['college_id'], fn ($query) => $query->where('departments.college_id', $filters['college_id']))
            ->when($filters['department_id'], fn ($query) => $query->where('courses.department_id', $filters['department_id']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('courses.status', $filters['status']))
            ->when($filters['credits'] !== '' && ctype_digit($filters['credits']), fn ($query) => $query->where('courses.credits', (int) $filters['credits']));
        OrganizationScope::apply($rowsQuery, $user, 'course');
        $rows = $rowsQuery
            ->selectRaw("COALESCE(colleges.name, 'No college') as college")
            ->selectRaw("COALESCE(departments.name, 'No department') as department")
            ->selectRaw('COUNT(DISTINCT courses.id) as courses_count')
            ->selectRaw('COUNT(course_sections.id) as open_sections_count')
            ->groupBy('college', 'department')
            ->orderBy('college')
            ->orderBy('department')
            ->get();

        return $rows
            ->groupBy('college')
            ->map(fn ($collegeRows, string $collegeName) => [
                'college' => $collegeName,
                'count' => $collegeRows->sum(fn ($row) => (int) $row->courses_count),
                'open_sections' => $collegeRows->sum(fn ($row) => (int) $row->open_sections_count),
                'departments' => $collegeRows
                    ->map(fn ($row) => [
                        'department' => $row->department,
                        'count' => (int) $row->courses_count,
                        'open_sections' => (int) $row->open_sections_count,
                    ])
                    ->values(),
            ])
            ->values();
    }

    private function organizationOptions(User $user): array
    {
        $collegeQuery = College::orderBy('name');
        $departmentQuery = Department::with('college')->orderBy('name');
        OrganizationScope::apply($collegeQuery, $user, 'college');
        OrganizationScope::apply($departmentQuery, $user, 'department');

        return [
            $collegeQuery->get(['id', 'name']),
            $departmentQuery->get(['id', 'name', 'college_id']),
        ];
    }

    private function scopedDepartment(int $departmentId, User $user): Department
    {
        $query = Department::whereKey($departmentId);
        OrganizationScope::apply($query, $user, 'department');

        return $query->firstOrFail();
    }

    private function authorizeCourse(Course $course, User $user): void
    {
        $query = Course::withTrashed()->whereKey($course->id);
        OrganizationScope::apply($query, $user, 'course');
        abort_unless($query->exists(), 403);
    }

    private function courseAbilities(Request $request): array
    {
        $user = $request->user();
        $isSuper = $user->hasRole('super_administrator');

        return [
            'create' => $isSuper || $user->hasPermission('courses.create') || $user->hasDirectPermissionGrant('academic_setup.manage'),
            'update' => $isSuper || $user->hasPermission('courses.update') || $user->hasDirectPermissionGrant('academic_setup.manage'),
            'archive' => $isSuper || $user->hasPermission('courses.archive') || $user->hasDirectPermissionGrant('academic_setup.manage'),
        ];
    }

    private function requireCatalogAccess(Request $request, string $permission, bool $manage = false): void
    {
        $user = $request->user();
        $academicGrant = $user->hasDirectPermissionGrant($manage ? 'academic_setup.manage' : 'academic_setup.view')
            || $user->hasDirectPermissionGrant('academic_setup.manage');
        $classroomOnly = $user->hasAnyRole(['student', 'teacher', 'teaching_assistant'])
            && ! $user->hasAnyRole(['super_administrator', 'administrator', 'university_administrator', 'college_administrator', 'department_administrator', 'registrar']);

        abort_if($classroomOnly && ! $academicGrant, 403);
        abort_unless($user->hasRole('super_administrator') || $user->hasPermission($permission) || $academicGrant, 403);
    }
}
