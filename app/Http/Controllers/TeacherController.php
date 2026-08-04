<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\Department;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\University;
use App\Models\User;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TeacherController extends Controller
{
    public function index(Request $request)
    {
        $this->requireAnyPermission('teachers.view');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'university_id' => $request->query('university_id'),
            'college_id' => $request->query('college_id'),
            'department_id' => $request->query('department_id'),
            'status' => in_array($request->query('status'), ['Active', 'Inactive', 'Retired'], true)
                ? $request->query('status')
                : '',
        ];

        $directoryQuery = $this->teacherDirectoryQuery($filters, $request->user());
        $teachers = (clone $directoryQuery)->paginate(15)->withQueryString();
        $stats = $this->teacherStatusStats((clone $directoryQuery));
        $classificationGroups = $this->teacherClassificationGroups((clone $directoryQuery));
        $universities = $this->scopedUniversities($request->user());
        $colleges = $this->scopedColleges($request->user());
        $departments = $this->scopedDepartments($request->user());
        $abilities = $this->teacherAbilities($request);
        $archivedCount = $this->scopedTeacherQuery($request->user(), true)->count();

        return view('teachers.index', compact('teachers', 'stats', 'classificationGroups', 'universities', 'colleges', 'departments', 'filters', 'abilities', 'archivedCount'));
    }

    public function create()
    {
        $this->requireAnyPermission('teachers.create');

        $departments = $this->scopedDepartments(request()->user());
        $colleges = $this->scopedColleges(request()->user());
        $universities = $this->scopedUniversities(request()->user());
        $suggestedStaffId = Teacher::suggestedStaffIdentifier();

        return view('teachers.create', compact('departments', 'colleges', 'universities', 'suggestedStaffId'));
    }

    public function store(Request $request)
    {
        $this->requireAnyPermission('teachers.create');

        $validated = $this->validateTeacher($request);
        $this->validateOrganizationSelection($validated);
        $temporaryPassword = $validated['password'];
        unset($validated['password']);
        unset($validated['college_id']);

        $teacher = DB::transaction(function () use ($validated, $temporaryPassword) {
            $teacher = Teacher::create($validated);
            $this->syncTeacherUser($teacher, $temporaryPassword, true, false);

            return $teacher;
        });

        $message = $teacher->status === 'Active'
            ? 'Teacher created successfully. Temporary password set. Teacher must change password at first login.'
            : 'Teacher created successfully. Login access will remain suspended until the teacher is active.';

        return redirect()->route('teachers.index')->with('success', $message);
    }

    public function show(Request $request, Teacher $teacher)
    {
        $this->requireAnyPermission('teachers.view');
        $this->authorizeTeacherScope($request, $teacher);

        $teacher->load([
            'university',
            'department.college',
            'user',
            'courseSections' => fn ($query) => $query
                ->with(['course', 'semester', 'timetables.classroom'])
                ->withCount(['activeEnrollments', 'assessmentItems'])
                ->orderByDesc('created_at'),
        ]);
        $abilities = $this->teacherAbilities($request);
        $workload = [
            'classes' => $teacher->courseSections->whereIn('status', ['planned', 'active'])->count(),
            'students' => $teacher->courseSections->where('status', 'active')->sum('active_enrollments_count'),
            'assessments' => $teacher->courseSections->sum('assessment_items_count'),
            'schedule_entries' => $teacher->courseSections->sum(fn ($section) => $section->timetables->count()),
        ];

        return view('teachers.show', compact('teacher', 'abilities', 'workload'));
    }

    public function edit(Teacher $teacher)
    {
        $this->requireAnyPermission('teachers.update');
        $this->authorizeTeacherScope(request(), $teacher);

        $departments = $this->scopedDepartments(request()->user());
        $colleges = $this->scopedColleges(request()->user());
        $universities = $this->scopedUniversities(request()->user());

        return view('teachers.edit', compact('teacher', 'departments', 'colleges', 'universities'));
    }

    public function update(Request $request, Teacher $teacher)
    {
        $this->requireAnyPermission('teachers.update');
        $this->authorizeTeacherScope($request, $teacher);

        $validated = $this->validateTeacher($request, $teacher);
        $this->validateOrganizationSelection($validated);
        unset($validated['college_id']);

        if ($validated['status'] !== 'Active' && $this->hasOpenAssignments($teacher)) {
            throw ValidationException::withMessages([
                'status' => 'Reassign or close the teacher\'s planned and active classes before changing the teacher to inactive or retired.',
            ]);
        }

        $accountCreated = DB::transaction(function () use ($teacher, $validated) {
            $teacher->update($validated);

            return $this->syncTeacherUser($teacher);
        });

        if ($accountCreated && $teacher->status === 'Active') {
            Password::sendResetLink(['email' => $teacher->email]);
        }

        return redirect()->route('teachers.show', $teacher)->with('success', 'Teacher updated successfully.');
    }

    public function destroy(Teacher $teacher)
    {
        $this->requireAnyPermission('teachers.archive');
        $this->authorizeTeacherScope(request(), $teacher);

        if ($this->hasOpenAssignments($teacher)) {
            return back()->with('error', 'Reassign or close the teacher\'s planned and active classes before archiving.');
        }

        DB::transaction(function () use ($teacher) {
            $user = $this->linkedUser($teacher);
            $teacherRole = Role::where('name', 'teacher')->first();

            if ($user && $teacherRole) {
                if ($user->roles()->where('roles.id', '!=', $teacherRole->id)->doesntExist()) {
                    $user->delete();
                } else {
                    $user->roles()->detach($teacherRole->id);
                }
            }

            $teacher->delete();
        });

        return redirect()->route('teachers.index')->with('success', 'Teacher archived successfully.');
    }

    public function archived(Request $request)
    {
        $this->requireAnyPermission('teachers.archive');

        $search = trim((string) $request->query('q', ''));
        $teachers = $this->scopedTeacherQuery($request->user(), true)
            ->with(['university', 'department.college'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('full_name', 'like', "%{$search}%")
                    ->orWhere('staff_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            }))
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('teachers.archived', compact('teachers', 'search'));
    }

    public function restore(int $teacherId)
    {
        $this->requireAnyPermission('teachers.archive');

        $teacher = $this->scopedTeacherQuery(request()->user(), true)->findOrFail($teacherId);
        abort_unless($teacher->trashed(), 404);

        $accountCreated = DB::transaction(function () use ($teacher) {
            $teacher->restore();

            return $this->syncTeacherUser($teacher);
        });

        if ($accountCreated && $teacher->status === 'Active') {
            Password::sendResetLink(['email' => $teacher->email]);
        }

        return redirect()->route('teachers.archived')->with('success', 'Teacher restored successfully.');
    }

    private function validateTeacher(Request $request, ?Teacher $teacher = null): array
    {
        $linkedUser = $teacher
            ? ($this->linkedUser($teacher) ?: $this->legacyTeacherUser($teacher))
            : null;
        $emailRules = ['required', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($teacher)];
        $emailRules[] = Rule::unique('users', 'email')->ignore($linkedUser?->id);

        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'title' => ['nullable', 'string', 'max:100'],
            'university_id' => ['required', 'exists:universities,id'],
            'college_id' => ['required', 'exists:colleges,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'Retired'])],
        ];

        if (! $teacher) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
        }

        return $request->validate($rules);
    }

    private function validateOrganizationSelection(array $validated): void
    {
        $university = University::findOrFail($validated['university_id']);
        $department = Department::findOrFail($validated['department_id']);

        if ($department->university_id !== $university->id) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department does not belong to the selected university.',
            ]);
        }

        if (! empty($validated['college_id']) && (int) $department->college_id !== (int) $validated['college_id']) {
            throw ValidationException::withMessages([
                'department_id' => 'The selected department does not belong to the selected college.',
            ]);
        }

        if (! empty($validated['college_id']) && ! College::whereKey($validated['college_id'])->where('university_id', $university->id)->exists()) {
            throw ValidationException::withMessages([
                'college_id' => 'The selected college does not belong to the selected university.',
            ]);
        }

        $this->authorizeDepartmentScope(request()->user(), $department);
    }

    private function teacherDirectoryQuery(array $filters, User $user)
    {
        $query = $this->scopedTeacherQuery($user)
            ->with(['university', 'department.college'])
            ->withCount([
                'courseSections',
                'courseSections as active_sections_count' => fn ($query) => $query->where('status', 'active'),
            ])
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($searchQuery) use ($filters) {
                $searchQuery->where('teachers.full_name', 'like', "%{$filters['q']}%")
                    ->orWhere('teachers.staff_id', 'like', "%{$filters['q']}%")
                    ->orWhere('teachers.email', 'like', "%{$filters['q']}%")
                    ->orWhere('teachers.title', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['university_id'], fn ($query) => $query->where('teachers.university_id', $filters['university_id']))
            ->when($filters['college_id'], fn ($query) => $query->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($query) => $query->where('teachers.department_id', $filters['department_id']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('teachers.status', $filters['status']))
            ->orderBy('teachers.full_name');

        return $query;
    }

    private function classifyTeachers($teachers)
    {
        return $teachers
            ->groupBy(fn (Teacher $teacher) => $teacher->department?->college?->name ?: 'No college')
            ->map(fn ($collegeTeachers, string $collegeName) => [
                'college' => $collegeName,
                'count' => $collegeTeachers->count(),
                'active_classes' => $collegeTeachers->sum('active_sections_count'),
                'departments' => $collegeTeachers
                    ->groupBy(fn (Teacher $teacher) => $teacher->department?->name ?: 'No department')
                    ->map(fn ($departmentTeachers, string $departmentName) => [
                        'department' => $departmentName,
                        'count' => $departmentTeachers->count(),
                        'active_classes' => $departmentTeachers->sum('active_sections_count'),
                    ])
                    ->sortBy('department')
                    ->values(),
            ])
            ->sortBy('college')
            ->values();
    }

    private function teacherStatusStats($query): array
    {
        $counts = (clone $query)
            ->reorder()
            ->select('teachers.status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('teachers.status')
            ->pluck('aggregate', 'status');

        $matchingTeacherIds = (clone $query)
            ->reorder()
            ->select('teachers.id');

        $activeClasses = Teacher::withoutGlobalScope('organization')
            ->whereIn('teachers.id', $matchingTeacherIds)
            ->join('course_sections as active_teacher_sections', function ($join) {
                $join->on('teachers.id', '=', 'active_teacher_sections.teacher_id')
                    ->where('active_teacher_sections.status', 'active')
                    ->whereNull('active_teacher_sections.deleted_at');
            })
            ->count('active_teacher_sections.id');

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) ($counts['Active'] ?? 0),
            'inactive' => (int) ($counts['Inactive'] ?? 0),
            'retired' => (int) ($counts['Retired'] ?? 0),
            'active_classes' => (int) $activeClasses,
        ];
    }

    private function teacherClassificationGroups($query)
    {
        $matchingTeacherIds = (clone $query)
            ->reorder()
            ->select('teachers.id');

        $rows = Teacher::withoutGlobalScope('organization')
            ->whereIn('teachers.id', $matchingTeacherIds)
            ->leftJoin('departments as classification_departments', 'teachers.department_id', '=', 'classification_departments.id')
            ->leftJoin('colleges as classification_colleges', 'classification_departments.college_id', '=', 'classification_colleges.id')
            ->leftJoin('course_sections as classification_sections', function ($join) {
                $join->on('teachers.id', '=', 'classification_sections.teacher_id')
                    ->where('classification_sections.status', 'active')
                    ->whereNull('classification_sections.deleted_at');
            })
            ->selectRaw("
                COALESCE(classification_colleges.name, 'No college') as college_name,
                COALESCE(classification_departments.name, 'No department') as department_name,
                COUNT(DISTINCT teachers.id) as teachers_count,
                COUNT(classification_sections.id) as active_classes_count
            ")
            ->groupBy('college_name', 'department_name')
            ->orderBy('college_name')
            ->orderBy('department_name')
            ->get();

        return $rows
            ->groupBy('college_name')
            ->map(fn ($collegeRows, string $collegeName) => [
                'college' => $collegeName,
                'count' => (int) $collegeRows->sum('teachers_count'),
                'active_classes' => (int) $collegeRows->sum('active_classes_count'),
                'departments' => $collegeRows
                    ->map(fn ($row) => [
                        'department' => $row->department_name,
                        'count' => (int) $row->teachers_count,
                        'active_classes' => (int) $row->active_classes_count,
                    ])
                    ->values(),
            ])
            ->values();
    }

    private function teacherAbilities(Request $request): array
    {
        $user = $request->user();
        $isSuper = $user->hasRole('super_administrator');

        return [
            'create' => $isSuper || $user->hasPermission('teachers.create'),
            'update' => $isSuper || $user->hasPermission('teachers.update'),
            'archive' => $isSuper || $user->hasPermission('teachers.archive'),
        ];
    }

    private function hasOpenAssignments(Teacher $teacher): bool
    {
        return $teacher->courseSections()
            ->withoutGlobalScope('organization')
            ->whereIn('status', ['planned', 'active'])
            ->exists();
    }

    private function scopedTeacherQuery(User $user, bool $onlyTrashed = false)
    {
        $query = $onlyTrashed ? Teacher::onlyTrashed() : Teacher::query();
        OrganizationScope::apply($query, $user, 'teacher');

        return $query;
    }

    private function scopedUniversities(User $user)
    {
        $query = University::orderBy('name');
        OrganizationScope::apply($query, $user, 'university');

        return $query->get(['id', 'name']);
    }

    private function scopedColleges(User $user)
    {
        $query = College::with('university')->orderBy('name');
        OrganizationScope::apply($query, $user, 'college');

        return $query->get(['id', 'name', 'code', 'university_id']);
    }

    private function scopedDepartments(User $user)
    {
        $query = Department::with('college')->orderBy('name');
        OrganizationScope::apply($query, $user, 'department');

        return $query->get(['id', 'name', 'code', 'college_id', 'university_id']);
    }

    private function authorizeTeacherScope(Request $request, Teacher $teacher): void
    {
        $visible = $this->scopedTeacherQuery($request->user())
            ->whereKey($teacher->id)
            ->exists();

        abort_unless($visible, 404);
    }

    private function authorizeDepartmentScope(User $user, Department $department): void
    {
        $query = Department::query();
        OrganizationScope::apply($query, $user, 'department');
        $visible = $query->whereKey($department->id)->exists();

        abort_unless($visible, 403);
    }

    private function syncTeacherUser(
        Teacher $teacher,
        ?string $temporaryPassword = null,
        bool $forcePasswordChange = false,
        bool $allowLegacyLink = true
    ): bool {
        $user = $this->linkedUser($teacher)
            ?: ($allowLegacyLink ? $this->legacyTeacherUser($teacher) : null);
        $accountCreated = ! $user;

        if (! $user) {
            $user = new User(['password' => $temporaryPassword ?? Str::password(32)]);
            $user->email_verified_at = now();
        } elseif ($user->trashed()) {
            $user->restore();
        }

        $user->fill([
            'name' => $teacher->full_name,
            'email' => $teacher->email,
            'university_id' => $teacher->university_id,
            'college_id' => $teacher->department?->college_id,
            'department_id' => $teacher->department_id,
        ]);

        if ($temporaryPassword !== null) {
            $user->password = $temporaryPassword;
        }

        if ($forcePasswordChange) {
            $user->must_change_password = true;
        }

        $user->save();

        $teacherRole = Role::firstOrCreate(
            ['name' => 'teacher'],
            ['display_name' => 'Instructor', 'description' => 'Teacher role']
        );
        if ($teacher->status === 'Active') {
            $user->roles()->syncWithoutDetaching([$teacherRole->id]);
        } elseif ($user->roles()->where('roles.id', '!=', $teacherRole->id)->exists()) {
            $user->roles()->detach($teacherRole->id);
        } else {
            $user->delete();
        }

        if ($teacher->user_id !== $user->id) {
            $teacher->update(['user_id' => $user->id]);
        }

        return $accountCreated;
    }

    private function linkedUser(Teacher $teacher): ?User
    {
        return $teacher->user_id
            ? User::withTrashed()->find($teacher->user_id)
            : null;
    }

    private function legacyTeacherUser(Teacher $teacher): ?User
    {
        return User::withTrashed()
            ->where('email', $teacher->getRawOriginal('email'))
            ->whereHas('roles', fn ($query) => $query->where('name', 'teacher'))
            ->first();
    }
}
