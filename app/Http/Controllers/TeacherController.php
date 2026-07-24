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
        $matchingTeachers = (clone $directoryQuery)->get();
        $teachers = (clone $directoryQuery)->paginate(15)->withQueryString();
        $stats = [
            'total' => $matchingTeachers->count(),
            'active' => $matchingTeachers->where('status', 'Active')->count(),
            'inactive' => $matchingTeachers->where('status', 'Inactive')->count(),
            'retired' => $matchingTeachers->where('status', 'Retired')->count(),
            'active_classes' => $matchingTeachers->sum('active_sections_count'),
        ];
        $classificationGroups = $this->classifyTeachers($matchingTeachers);
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

        return view('teachers.create', compact('departments', 'colleges', 'universities'));
    }

    public function store(Request $request)
    {
        $this->requireAnyPermission('teachers.create');

        $validated = $this->validateTeacher($request);
        $this->validateOrganizationSelection($validated);
        unset($validated['college_id']);

        [$teacher, $accountCreated] = DB::transaction(function () use ($validated) {
            $teacher = Teacher::create($validated);
            $accountCreated = $this->syncTeacherUser($teacher);

            return [$teacher, $accountCreated];
        });

        if ($accountCreated) {
            Password::sendResetLink(['email' => $teacher->email]);
        }

        $message = $accountCreated
            ? 'Teacher created successfully. A secure password setup link was sent to the teacher email.'
            : 'Teacher created successfully. The existing login account was linked to the teacher profile.';

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

        if ($validated['status'] === 'Retired' && $this->hasOpenAssignments($teacher)) {
            throw ValidationException::withMessages([
                'status' => 'Reassign or close the teacher\'s planned and active classes before retirement.',
            ]);
        }

        $accountCreated = DB::transaction(function () use ($teacher, $validated) {
            $teacher->update($validated);

            return $this->syncTeacherUser($teacher);
        });

        if ($accountCreated) {
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

        if ($accountCreated) {
            Password::sendResetLink(['email' => $teacher->email]);
        }

        return redirect()->route('teachers.archived')->with('success', 'Teacher restored successfully.');
    }

    private function validateTeacher(Request $request, ?Teacher $teacher = null): array
    {
        $linkedUser = $teacher ? $this->linkedUser($teacher) : null;
        $emailRules = ['required', 'email', 'max:255', Rule::unique('teachers', 'email')->ignore($teacher)];
        if ($teacher) {
            $emailRules[] = Rule::unique('users', 'email')->ignore($linkedUser?->id);
        }

        return $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'staff_id' => ['required', 'string', 'max:100', Rule::unique('teachers', 'staff_id')->ignore($teacher)],
            'email' => $emailRules,
            'title' => ['nullable', 'string', 'max:100'],
            'university_id' => ['required', 'exists:universities,id'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'Retired'])],
        ]);
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
                $searchQuery->where('full_name', 'like', "%{$filters['q']}%")
                    ->orWhere('staff_id', 'like', "%{$filters['q']}%")
                    ->orWhere('email', 'like', "%{$filters['q']}%")
                    ->orWhere('title', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['university_id'], fn ($query) => $query->where('university_id', $filters['university_id']))
            ->when($filters['college_id'], fn ($query) => $query->whereHas('department', fn ($department) => $department->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($query) => $query->where('department_id', $filters['department_id']))
            ->when($filters['status'] !== '', fn ($query) => $query->where('status', $filters['status']))
            ->orderBy('full_name');

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
        return $teacher->courseSections()->whereIn('status', ['planned', 'active'])->exists();
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
        $visible = $this->scopedDepartments($user)
            ->contains('id', $department->id);

        abort_unless($visible, 403);
    }

    private function syncTeacherUser(Teacher $teacher): bool
    {
        $user = $this->linkedUser($teacher)
            ?: User::withTrashed()->where('email', $teacher->email)->first();
        $accountCreated = ! $user;

        if (! $user) {
            $user = new User(['password' => Str::password(32)]);
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
        $user->save();

        $teacherRole = Role::firstOrCreate(
            ['name' => 'teacher'],
            ['display_name' => 'Instructor', 'description' => 'Teacher role']
        );
        $user->roles()->syncWithoutDetaching([$teacherRole->id]);

        if ($teacher->user_id !== $user->id) {
            $teacher->update(['user_id' => $user->id]);
        }

        return $accountCreated;
    }

    private function linkedUser(Teacher $teacher): ?User
    {
        return $teacher->user_id
            ? User::withTrashed()->find($teacher->user_id)
            : User::withTrashed()->where('email', $teacher->getRawOriginal('email'))->first();
    }
}
