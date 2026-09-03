<?php

namespace App\Http\Controllers;

use App\Models\College;
use App\Models\CourseSection;
use App\Models\Department;
use App\Models\Role;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\StudentGuardian;
use App\Models\University;
use App\Models\User;
use App\Services\TuitionChargeService;
use App\Support\OrganizationScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->requireAnyPermission('students.view');

        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'college_id' => $request->query('college_id'),
            'department_id' => $request->query('department_id'),
            'grade_level' => trim((string) $request->query('grade_level', '')),
            'status' => in_array($request->query('status'), ['Active', 'Inactive', 'Graduated'], true)
                ? $request->query('status')
                : '',
        ];

        $directoryQuery = $this->studentDirectoryQuery($filters);
        $students = (clone $directoryQuery)
            ->paginate(15)
            ->withQueryString();
        $students->setCollection($this->addGradeLabels($students->getCollection()));

        $classificationGroups = $this->studentClassificationGroups((clone $directoryQuery));
        $stats = $this->studentStatusStats((clone $directoryQuery));
        $colleges = $this->scopedColleges($request->user());
        $departments = $this->scopedDepartments($request->user());
        $gradeQuery = CourseSection::whereNotNull('grade_level');
        OrganizationScope::apply($gradeQuery, $request->user(), 'section');
        $gradeOptions = $gradeQuery
            ->distinct()
            ->orderBy('grade_level')
            ->pluck('grade_level');
        $currentStageQuery = Stage::orderBy('name');
        OrganizationScope::apply($currentStageQuery, $request->user(), 'stage');
        $gradeOptions = $gradeOptions
            ->merge($currentStageQuery->pluck('name'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $abilities = $this->studentAbilities($request);
        $archivedCount = $this->scopedStudentQuery($request->user(), true)->count();

        return view('students.index', compact('students', 'classificationGroups', 'colleges', 'departments', 'gradeOptions', 'filters', 'stats', 'abilities', 'archivedCount'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->requireAnyPermission('students.create');

        $universities = $this->scopedUniversities(request()->user());
        $colleges = $this->scopedColleges(request()->user());
        $departments = $this->scopedDepartments(request()->user());
        $stages = $this->scopedStages(request()->user());
        $suggestedStudentId = Student::suggestedStudentIdentifier();

        return view('students.create', compact('universities', 'colleges', 'departments', 'stages', 'suggestedStudentId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $this->requireAnyPermission('students.create');

        $validated = $this->validateStudent($request, null);
        $department = $this->validateOrganizationSelection($validated, $request->user());
        $this->validateStageSelection($validated, $department, $request->user());
        $validated['university_id'] = $department->university_id;
        unset($validated['college_id']);
        $temporaryPassword = $validated['password'];
        unset($validated['password'], $validated['password_confirmation']);

        [$student, $accountCreated] = DB::transaction(function () use ($validated, $temporaryPassword, $request) {
            $student = Student::create($validated);
            $accountCreated = $this->syncStudentUser($student, $temporaryPassword, true);
            $tuitionCharges = app(TuitionChargeService::class);
            $tuitionCharges->autoGenerateFlatChargesForNewStudent($student, $request->user());
            $tuitionCharges->autoGenerateFullChargeForStudent($student, $request->user());

            return [$student, $accountCreated];
        });

        $message = $accountCreated
            ? __('Student created successfully. The student can sign in with the temporary password and will be asked to create a new password.')
            : __('Student created successfully. The existing login account was linked to the student profile.');

        return redirect()->route('students.index')->with('success', $message);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Student $student)
    {
        $this->requireAnyPermission('students.view');
        $this->authorizeStudentScope($request, $student);

        $student->load([
            'university',
            'department.college',
            'currentStage',
            'stageProgressions.stage',
            'stageProgressions.nextStage',
            'user',
            'guardians',
            'documents.uploader',
            'enrollments.courseSection.course',
            'enrollments.courseSection.semester',
        ]);
        $student->grade_labels = $this->gradeLabelsFor($student);
        $abilities = $this->studentAbilities($request);

        return view('students.show', compact('student', 'abilities'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Student $student)
    {
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope(request(), $student);

        $student->load(['department.college', 'guardians', 'documents.uploader']);
        $universities = $this->scopedUniversities(request()->user());
        $colleges = $this->scopedColleges(request()->user());
        $departments = $this->scopedDepartments(request()->user());
        $stages = $this->scopedStages(request()->user());

        return view('students.edit', compact('student', 'universities', 'colleges', 'departments', 'stages'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Student $student)
    {
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope($request, $student);

        $validated = $this->validateStudent($request, $student);
        $department = $this->validateOrganizationSelection($validated, $request->user());
        $this->protectDepartmentChange($student, $department);
        $this->validateStageSelection($validated, $department, $request->user(), $student);
        $validated['university_id'] = $department->university_id;
        unset($validated['college_id']);

        $accountCreated = DB::transaction(function () use ($student, $validated) {
            $student->update($validated);

            return $this->syncStudentUser($student);
        });

        if ($accountCreated) {
            Password::sendResetLink(['email' => $student->email]);
        }

        return redirect()->route('students.edit', $student)->with('success', __('Student updated successfully.'));
    }

    public function storeGuardian(Request $request, Student $student)
    {
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope($request, $student);

        $validated = $request->validate($this->guardianRules());
        $validated['is_primary'] = $request->boolean('is_primary');

        if ($validated['is_primary']) {
            $student->guardians()->update(['is_primary' => false]);
        }

        $student->guardians()->create($validated);

        return redirect()->route('students.edit', $student)->with('success', __('Guardian added to student profile.'));
    }

    public function updateGuardian(Request $request, StudentGuardian $guardian)
    {
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope($request, $guardian->student);

        $validated = $request->validate($this->guardianRules());
        $validated['is_primary'] = $request->boolean('is_primary');

        if ($validated['is_primary']) {
            $guardian->student->guardians()->whereKeyNot($guardian->id)->update(['is_primary' => false]);
        }

        $guardian->update($validated);

        return redirect()->route('students.edit', $guardian->student)->with('success', __('Guardian updated.'));
    }

    public function destroyGuardian(StudentGuardian $guardian)
    {
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope(request(), $guardian->student);

        $student = $guardian->student;
        $guardian->delete();

        return redirect()->route('students.edit', $student)->with('success', __('Guardian removed.'));
    }

    public function storeDocument(Request $request, Student $student)
    {
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope($request, $student);

        $validated = $request->validate([
            'type' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:255'],
            'document' => $this->safeUploadRules('required'),
            'status' => ['required', Rule::in(['Submitted', 'Verified', 'Rejected', 'Expired'])],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $file = $request->file('document');
        $path = $file->store('student-documents');

        $student->documents()->create([
            'uploaded_by' => $request->user()->id,
            'type' => $validated['type'],
            'title' => $validated['title'],
            'file_path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        return redirect()->route('students.edit', $student)->with('success', __('Document uploaded.'));
    }

    public function downloadDocument(Request $request, StudentDocument $document)
    {
        abort_unless($this->canAccessDocument($request, $document), 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_name);
    }

    public function destroyDocument(StudentDocument $document)
    {
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope(request(), $document->student);

        $student = $document->student;

        if ($document->file_path) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return redirect()->route('students.edit', $student)->with('success', __('Document removed.'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student)
    {
        $this->requireAnyPermission('students.archive');
        $this->authorizeStudentScope(request(), $student);

        DB::transaction(function () use ($student) {
            $user = $this->linkedUser($student);
            $studentRole = Role::where('name', 'student')->first();

            if ($user && $studentRole) {
                if ($user->roles()->where('roles.id', '!=', $studentRole->id)->doesntExist()) {
                    $user->delete();
                } else {
                    $user->roles()->detach($studentRole->id);
                }
            }

            $student->delete();
        });

        return redirect()->route('students.index')->with('success', __('Student archived successfully.'));
    }

    public function archived(Request $request)
    {
        $this->requireAnyPermission('students.archive');

        $search = trim((string) $request->query('q', ''));
        $students = $this->scopedStudentQuery($request->user(), true)
            ->with(['department.college'])
            ->when($search !== '', fn ($query) => $query->where(function ($searchQuery) use ($search) {
                $searchQuery->where('full_name', 'like', "%{$search}%")
                    ->orWhere('student_id', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('national_id', 'like', "%{$search}%");
            }))
            ->orderByDesc('deleted_at')
            ->paginate(15)
            ->withQueryString();

        return view('students.archived', compact('students', 'search'));
    }

    public function restore(Request $request, int $studentId)
    {
        $this->requireAnyPermission('students.archive');

        $student = $this->scopedStudentQuery($request->user(), true)->findOrFail($studentId);
        abort_unless($student->trashed(), 404);

        $accountCreated = DB::transaction(function () use ($student) {
            $student->restore();

            return $this->syncStudentUser($student);
        });

        if ($accountCreated) {
            Password::sendResetLink(['email' => $student->email]);
        }

        return redirect()->route('students.archived')->with('success', __('Student restored successfully.'));
    }

    public function resetPassword(Request $request, Student $student)
    {
        $this->requireAnyRole('super_administrator', 'department_administrator');
        $this->requireAnyPermission('students.update');
        $this->authorizeStudentScope($request, $student);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $this->syncStudentUser($student);
        $account = $this->linkedUser($student);
        abort_unless($account, 404, __('Student login account was not found.'));

        if (! $request->user()->hasRole('super_administrator')) {
            $nonStudentRoles = $account->roles()
                ->where('name', '!=', 'student')
                ->exists();

            abort_if($nonStudentRoles, 403, __('This login account has non-student roles.'));
        }

        $account->update([
            'password' => $validated['password'],
            'must_change_password' => true,
        ]);

        return redirect()
            ->route('students.show', $student)
            ->with('success', __('Student password reset successfully.'));
    }

    private function validateStudent(Request $request, ?Student $student): array
    {
        $linkedUser = $student ? $this->linkedUser($student) : null;
        $emailRules = ['required', 'email', 'max:255', Rule::unique('students', 'email')->ignore($student)];
        $emailRules[] = Rule::unique('users', 'email')->ignore($linkedUser?->id);

        $rules = [
            'full_name' => ['required', 'string', 'max:255'],
            'email' => $emailRules,
            'phone' => ['nullable', 'string', 'max:50'],
            'national_id' => ['nullable', 'string', 'max:50', Rule::unique('students', 'national_id')->ignore($student)],
            'university_id' => ['nullable', 'exists:universities,id'],
            'college_id' => ['nullable', 'exists:colleges,id'],
            'department_id' => ['required', 'exists:departments,id'],
            'current_stage_id' => ['nullable', 'exists:stages,id'],
            'status' => ['required', 'in:Active,Inactive,Graduated'],
            'admission_status' => ['nullable', Rule::in(['Applicant', 'Admitted', 'Enrolled', 'Deferred', 'Withdrawn', 'Graduated'])],
            'admission_date' => ['nullable', 'date'],
            'admission_type' => ['nullable', 'string', 'max:100'],
            'previous_school' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:50'],
        ];

        if (! $student) {
            $rules['password'] = ['required', 'string', 'min:8', 'confirmed'];
            $rules['preferred_payment_method'] = ['nullable', Rule::in(['semester', 'full'])];
            $rules['scholarship_percentage'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        $validated = $request->validate($rules);

        $validated['admission_status'] = $validated['admission_status'] ?? 'Admitted';

        return $validated;
    }

    private function guardianRules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255'],
            'relationship' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:2000'],
            'is_primary' => ['nullable', 'boolean'],
        ];
    }

    private function canAccessDocument(Request $request, StudentDocument $document): bool
    {
        $user = $request->user();

        if ($user->hasRole('super_administrator')) {
            return true;
        }

        if ($user->hasAnyPermission(['students.view', 'students.update'])) {
            return $this->scopedStudentQuery($user)
                ->whereKey($document->student_id)
                ->exists();
        }

        if ($user->hasRole('student') && ((int) $document->student?->user_id === (int) $user->id || $user->email === $document->student?->email)) {
            return true;
        }

        return false;
    }

    private function classifyStudents($students)
    {
        return $students
            ->groupBy(fn (Student $student) => $student->department?->college?->name ?: 'No college')
            ->map(function ($collegeStudents, string $collegeName) {
                return [
                    'college' => $collegeName,
                    'count' => $collegeStudents->count(),
                    'departments' => $collegeStudents
                        ->groupBy(fn (Student $student) => $student->department?->name ?: 'No department')
                        ->map(function ($departmentStudents, string $departmentName) {
                            $gradeRows = $departmentStudents->flatMap(function (Student $student) {
                                $grades = $student->grade_labels->isNotEmpty()
                                    ? $student->grade_labels
                                    : collect(['No stage']);

                                return $grades->map(fn (string $grade) => [
                                    'grade' => $grade,
                                    'student' => $student,
                                ]);
                            });

                            return [
                                'department' => $departmentName,
                                'count' => $departmentStudents->count(),
                                'grades' => $gradeRows
                                    ->groupBy('grade')
                                    ->map(fn ($rows, string $grade) => [
                                        'grade' => $grade,
                                        'count' => $rows->pluck('student')->unique('id')->count(),
                                        'students' => $rows->pluck('student')->unique('id')->values(),
                                    ])
                                    ->sortBy('grade')
                                    ->values(),
                            ];
                        })
                        ->sortBy('department')
                        ->values(),
                ];
            })
            ->sortBy('college')
            ->values();
    }

    private function studentStatusStats($query): array
    {
        $counts = $query
            ->reorder()
            ->select('status', DB::raw('COUNT(*) as aggregate'))
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'total' => (int) $counts->sum(),
            'active' => (int) ($counts['Active'] ?? 0),
            'inactive' => (int) ($counts['Inactive'] ?? 0),
            'graduated' => (int) ($counts['Graduated'] ?? 0),
        ];
    }

    private function studentClassificationGroups($query)
    {
        $matchingStudentIds = (clone $query)
            ->reorder()
            ->select('students.id');

        $rows = Student::withoutGlobalScope('organization')
            ->whereIn('students.id', $matchingStudentIds)
            ->leftJoin('departments as classification_departments', 'students.department_id', '=', 'classification_departments.id')
            ->leftJoin('colleges as classification_colleges', 'classification_departments.college_id', '=', 'classification_colleges.id')
            ->leftJoin('stages as current_stages', 'students.current_stage_id', '=', 'current_stages.id')
            ->leftJoin('enrollments as classification_enrollments', function ($join) {
                $join->on('students.id', '=', 'classification_enrollments.student_id')
                    ->where('classification_enrollments.status', 'enrolled')
                    ->whereNull('classification_enrollments.deleted_at');
            })
            ->leftJoin('course_sections as classification_sections', function ($join) {
                $join->on('classification_enrollments.course_section_id', '=', 'classification_sections.id')
                    ->whereIn('classification_sections.status', ['planned', 'active'])
                    ->whereNull('classification_sections.deleted_at');
            })
            ->selectRaw("
                COALESCE(classification_colleges.name, 'No college') as college_name,
                COALESCE(classification_departments.name, 'No department') as department_name,
                COALESCE(classification_sections.grade_level, current_stages.name, 'No stage') as stage_name,
                COUNT(DISTINCT students.id) as students_count
            ")
            ->groupBy('college_name', 'department_name', 'stage_name')
            ->orderBy('college_name')
            ->orderBy('department_name')
            ->orderBy('stage_name')
            ->get();

        return $rows
            ->groupBy('college_name')
            ->map(fn ($collegeRows, string $collegeName) => [
                'college' => $collegeName === 'No college' ? __('No college') : $collegeName,
                'count' => (int) $collegeRows->sum('students_count'),
                'departments' => $collegeRows
                    ->groupBy('department_name')
                    ->map(fn ($departmentRows, string $departmentName) => [
                        'department' => $departmentName === 'No department' ? __('No department') : $departmentName,
                        'count' => (int) $departmentRows->sum('students_count'),
                        'grades' => $departmentRows
                            ->map(fn ($row) => [
                                'grade' => $row->stage_name === 'No stage' ? __('No stage') : $row->stage_name,
                                'count' => (int) $row->students_count,
                            ])
                            ->values(),
                    ])
                    ->values(),
            ])
            ->values();
    }

    private function studentDirectoryQuery(array $filters)
    {
        return $this->scopedStudentQuery(request()->user())
            ->with(['department.college', 'currentStage', 'enrollments.courseSection'])
            ->when($filters['q'] !== '', fn ($query) => $query->where(function ($searchQuery) use ($filters) {
                $searchQuery->where('students.full_name', 'like', "%{$filters['q']}%")
                    ->orWhere('students.student_id', 'like', "%{$filters['q']}%")
                    ->orWhere('students.email', 'like', "%{$filters['q']}%")
                    ->orWhere('students.national_id', 'like', "%{$filters['q']}%");
            }))
            ->when($filters['college_id'], fn ($query) => $query->whereHas('department', fn ($departmentQuery) => $departmentQuery->where('college_id', $filters['college_id'])))
            ->when($filters['department_id'], fn ($query) => $query->where('students.department_id', $filters['department_id']))
            ->when($filters['grade_level'] !== '', fn ($query) => $query->where(function ($stageQuery) use ($filters) {
                $stageQuery->whereHas('enrollments', fn ($enrollmentQuery) => $enrollmentQuery
                    ->where('status', 'enrolled')
                    ->whereHas('courseSection', fn ($sectionQuery) => $sectionQuery
                        ->where('grade_level', $filters['grade_level'])
                        ->whereIn('status', ['planned', 'active'])))
                    ->orWhereHas('currentStage', fn ($currentStageQuery) => $currentStageQuery->where('name', $filters['grade_level']));
            }))
            ->when($filters['status'] !== '', fn ($query) => $query->where('students.status', $filters['status']))
            ->orderBy('students.full_name');
    }

    private function addGradeLabels($students)
    {
        return $students->map(function (Student $student) {
            $student->grade_labels = $this->gradeLabelsFor($student);

            return $student;
        });
    }

    private function gradeLabelsFor(Student $student)
    {
        $activeStages = $student->enrollments
            ->where('status', 'enrolled')
            ->filter(fn ($enrollment) => in_array($enrollment->courseSection?->status, ['planned', 'active'], true))
            ->pluck('courseSection.grade_level')
            ->filter()
            ->unique()
            ->sort()
            ->values();

        return $activeStages->isNotEmpty()
            ? $activeStages
            : collect([$student->currentStage?->name])->filter()->values();
    }

    private function studentAbilities(Request $request): array
    {
        $user = $request->user();
        $isSuper = $user->hasRole('super_administrator');

        return [
            'create' => $isSuper || $user->hasPermission('students.create'),
            'update' => $isSuper || $user->hasPermission('students.update'),
            'archive' => $isSuper || $user->hasPermission('students.archive'),
            'reset_password' => $isSuper || ($user->hasRole('department_administrator') && $user->hasPermission('students.update')),
            'transcript' => $isSuper || ($user->hasAnyRole(['administrator', 'university_administrator', 'college_administrator', 'department_administrator']) && $user->hasPermission('marks.view')),
        ];
    }

    private function scopedStudentQuery(User $user, bool $onlyTrashed = false)
    {
        $query = $onlyTrashed ? Student::onlyTrashed() : Student::query();
        OrganizationScope::apply($query, $user, 'student');

        return $query;
    }

    private function scopedUniversities(User $user)
    {
        $query = University::orderBy('name');
        OrganizationScope::apply($query, $user, 'university');

        return $query->get(['id', 'name', 'code']);
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

    private function scopedStages(User $user)
    {
        $query = Stage::orderBy('sequence')->orderBy('name');
        OrganizationScope::apply($query, $user, 'stage');

        return $query->get(['id', 'university_id', 'department_id', 'name', 'code', 'sequence']);
    }

    private function validateOrganizationSelection(array $validated, User $user): Department
    {
        $department = $this->scopedDepartments($user)->firstWhere('id', (int) $validated['department_id']);

        if (! $department) {
            throw ValidationException::withMessages([
                'department_id' => __('The selected department is outside your organization scope.'),
            ]);
        }

        if (! empty($validated['university_id']) && (int) $department->university_id !== (int) $validated['university_id']) {
            throw ValidationException::withMessages([
                'department_id' => __('The selected department does not belong to the selected university.'),
            ]);
        }

        if (! empty($validated['college_id']) && (int) $department->college_id !== (int) $validated['college_id']) {
            throw ValidationException::withMessages([
                'department_id' => __('The selected department does not belong to the selected college.'),
            ]);
        }

        return $department;
    }

    private function validateStageSelection(array $validated, Department $department, User $user, ?Student $student = null): void
    {
        $stageId = $validated['current_stage_id'] ?? null;
        if (! $stageId) {
            if ($student?->enrollments()->where('status', 'enrolled')->where('is_retake', false)->exists()) {
                throw ValidationException::withMessages([
                    'current_stage_id' => __('A student with active regular modules must have a current stage.'),
                ]);
            }

            return;
        }

        $stageQuery = Stage::whereKey($stageId)
            ->where('university_id', $department->university_id)
            ->where('department_id', $department->id);
        OrganizationScope::apply($stageQuery, $user, 'stage');
        $stage = $stageQuery->first();

        if (! $stage) {
            throw ValidationException::withMessages([
                'current_stage_id' => __('The selected stage does not belong to the selected department or your organization scope.'),
            ]);
        }

        if ($student?->enrollments()
            ->where('status', 'enrolled')
            ->where('is_retake', false)
            ->whereHas('courseSection', fn ($query) => $query
                ->whereNotNull('stage_id')
                ->where('stage_id', '!=', $stage->id))
            ->exists()) {
            throw ValidationException::withMessages([
                'current_stage_id' => __('The selected stage conflicts with the student active regular modules.'),
            ]);
        }
    }

    private function protectDepartmentChange(Student $student, Department $department): void
    {
        if ((int) $student->department_id === (int) $department->id) {
            return;
        }

        $hasAcademicHistory = $student->enrollments()->withTrashed()->exists()
            || $student->marks()->withTrashed()->exists()
            || $student->stageProgressions()->exists();

        if ($hasAcademicHistory) {
            throw ValidationException::withMessages([
                'department_id' => __('The department cannot be changed after enrollment, marks, or stage progression records exist.'),
            ]);
        }
    }

    private function authorizeStudentScope(Request $request, Student $student): void
    {
        $query = $student->trashed()
            ? $this->scopedStudentQuery($request->user(), true)
            : $this->scopedStudentQuery($request->user());

        abort_unless($query->whereKey($student->id)->exists(), 404);
    }

    private function syncStudentUser(Student $student, ?string $temporaryPassword = null, bool $forcePasswordChange = false): bool
    {
        $student->loadMissing('department');
        $user = $this->linkedUser($student)
            ?: User::withTrashed()->where('email', $student->email)->first();
        $accountCreated = ! $user;

        if (! $user) {
            $user = new User([
                'password' => $temporaryPassword ?? Str::password(32),
            ]);
            $user->email_verified_at = now();
        } elseif ($user->trashed()) {
            $user->restore();
        }

        $user->fill([
            'name' => $student->full_name,
            'email' => $student->email,
            'university_id' => $student->university_id,
            'college_id' => $student->department?->college_id,
            'department_id' => $student->department_id,
        ]);

        if ($temporaryPassword !== null) {
            $user->password = $temporaryPassword;
        }

        if ($forcePasswordChange) {
            $user->must_change_password = true;
        }

        $user->save();

        $studentRole = Role::firstOrCreate(
            ['name' => 'student'],
            [
                'display_name' => 'Student User',
                'description' => 'Student role',
            ]
        );
        $user->roles()->syncWithoutDetaching([$studentRole->id]);

        if ($student->user_id !== $user->id) {
            $student->update(['user_id' => $user->id]);
        }

        return $accountCreated;
    }

    private function linkedUser(Student $student): ?User
    {
        return $student->user_id
            ? User::withTrashed()->find($student->user_id)
            : User::withTrashed()->where('email', $student->getRawOriginal('email'))->first();
    }
}
