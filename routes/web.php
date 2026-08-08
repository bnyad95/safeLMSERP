<?php

use App\Http\Controllers\AcademicYearClosureController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\ArchivedClassController;
use App\Http\Controllers\AssessmentController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\ClassMessageController;
use App\Http\Controllers\ClassStreamController;
use App\Http\Controllers\CollegeController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CourseMaterialController;
use App\Http\Controllers\CourseRegistrationController;
use App\Http\Controllers\CsvImportController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\ErpController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\MarkSubmissionController;
use App\Http\Controllers\NotificationCenterController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleWorkspaceController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\StageController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\TeacherController;
use App\Http\Controllers\TeacherDashboardController;
use App\Http\Controllers\TeacherMarkController;
use App\Http\Controllers\TimetableController;
use App\Http\Controllers\TranscriptController;
use App\Http\Controllers\UniversityController;
use App\Http\Controllers\UserManagementController;
use App\Http\Controllers\WebInstallerController;
use Illuminate\Support\Facades\Route;

Route::get('/setup', [WebInstallerController::class, 'show'])
    ->middleware('throttle:20,1')
    ->name('installer.show');
Route::post('/setup', [WebInstallerController::class, 'install'])
    ->middleware('throttle:5,1')
    ->name('installer.install');

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [ErpController::class, 'dashboard'])->name('dashboard');
    Route::get('/notifications', [NotificationCenterController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationCenterController::class, 'markAllRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}/read', [NotificationCenterController::class, 'markRead'])->name('notifications.read');
    Route::get('/search', [ErpController::class, 'search'])->name('search');
    Route::get('/search/suggestions', [ErpController::class, 'searchSuggestions'])->name('search.suggestions');
    Route::get('/analytics', [AnalyticsController::class, 'index'])
        ->middleware('role.any:super_administrator,administrator,university_president')
        ->middleware('permission.any:reports.academic')
        ->name('analytics.index');
    Route::prefix('integrations')
        ->name('integrations.')
        ->middleware('role.any:administrator,super_administrator,university_administrator,college_administrator,department_administrator,examination_administrator,lms_administrator')
        ->group(function () {
            Route::get('/', [CsvImportController::class, 'index'])->name('index');
            Route::post('/import/students', [CsvImportController::class, 'importStudents'])->name('import.students');
            Route::post('/import/courses', [CsvImportController::class, 'importCourses'])->name('import.courses');
            Route::post('/import/marks', [CsvImportController::class, 'importMarks'])->name('import.marks');
            Route::get('/export/students', [CsvImportController::class, 'exportStudents'])->name('export.students');
            Route::get('/export/courses', [CsvImportController::class, 'exportCourses'])->name('export.courses');
            Route::get('/export/marks', [CsvImportController::class, 'exportMarks'])->name('export.marks');
            Route::get('/export/teachers', [CsvImportController::class, 'exportTeachers'])->name('export.teachers');
            Route::get('/export/enrollments', [CsvImportController::class, 'exportEnrollments'])->name('export.enrollments');
            Route::get('/export/attendance', [CsvImportController::class, 'exportAttendance'])->name('export.attendance');
            Route::get('/export/timetable', [CsvImportController::class, 'exportTimetable'])->name('export.timetable');
            Route::get('/samples/students', [CsvImportController::class, 'sampleStudents'])->name('samples.students');
            Route::get('/samples/courses', [CsvImportController::class, 'sampleCourses'])->name('samples.courses');
            Route::get('/samples/marks', [CsvImportController::class, 'sampleMarks'])->name('samples.marks');
        });
    Route::get('/integrations/export/finance', [CsvImportController::class, 'exportFinance'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view')
        ->middleware('permission.any:reports.financial')
        ->name('integrations.export.finance');
    Route::get('/student-portal', [ErpController::class, 'studentPortal'])
        ->middleware('role.any:student')
        ->name('student-portal');
    Route::get('/library-workspace', [RoleWorkspaceController::class, 'library'])
        ->middleware('role.any:librarian')
        ->name('library.workspace');
    Route::get('/parent-portal', [RoleWorkspaceController::class, 'parent'])
        ->middleware('role.any:parent_user')
        ->name('parent.workspace');
    Route::get('/front-desk', [RoleWorkspaceController::class, 'reception'])
        ->middleware('role.any:receptionist')
        ->name('reception.workspace');
    Route::get('/my-finance', [FinanceController::class, 'studentFinance'])
        ->middleware('role.any:student')
        ->name('student.finance');
    Route::get('/my-finance/transactions/{financeTransaction}/receipt', [FinanceController::class, 'receipt'])
        ->middleware('access.any:role:student')
        ->name('student.finance.receipt');
    Route::get('/course-registration', [CourseRegistrationController::class, 'index'])
        ->middleware('role.any:student')
        ->name('course-registration.index');
    Route::post('/course-registration', [CourseRegistrationController::class, 'store'])
        ->middleware('role.any:student')
        ->name('course-registration.store');
    Route::get('/teacher-dashboard', TeacherDashboardController::class)
        ->middleware('role.any:teacher,teaching_assistant,administrator,super_administrator,university_administrator,college_administrator,department_administrator,lms_administrator')
        ->name('teacher-dashboard');
    Route::get('/archived-classes', [ArchivedClassController::class, 'index'])
        ->middleware('role.any:teacher,student')
        ->name('archived-classes.index');
    Route::get('/classrooms', TeacherDashboardController::class)
        ->middleware('role.any:administrator,super_administrator,university_administrator,college_administrator,department_administrator,lms_administrator')
        ->name('classrooms.index');
    Route::post('/classes/{courseSection}/prefinal-marks', [TeacherMarkController::class, 'storePrefinal'])
        ->middleware('role.any:teacher')
        ->name('teacher.prefinal-marks.store');
    Route::prefix('classes/{courseSection}/stream')->name('class-stream.')->middleware('role.any:teacher,student,super_administrator')->group(function () {
        Route::get('/', [ClassStreamController::class, 'show'])->name('show');
        Route::post('/posts', [ClassStreamController::class, 'store'])->name('posts.store');
        Route::patch('/student-posting', [ClassStreamController::class, 'toggleStudentPosting'])->name('student-posting.toggle');
        Route::delete('/posts/{post}', [ClassStreamController::class, 'destroy'])->name('posts.destroy');
        Route::post('/posts/{post}/comments', [ClassStreamController::class, 'comment'])->name('comments.store');
        Route::post('/posts/{post}/reaction', [ClassStreamController::class, 'react'])->name('reactions.toggle');
    });
    Route::get('/classes/{courseSection}/stream/posts/{post}/attachment', [ClassStreamController::class, 'attachment'])
        ->middleware('role.any:teacher,student,administrator,super_administrator,university_administrator,college_administrator,department_administrator,lms_administrator')
        ->name('class-stream.posts.attachment');
    Route::prefix('classes/{courseSection}/messages')->name('class-messages.')->middleware('role.any:teacher,student,super_administrator')->group(function () {
        Route::get('/', [ClassMessageController::class, 'index'])->name('index');
        Route::post('/', [ClassMessageController::class, 'store'])->name('store');
        Route::get('/thread', [ClassMessageController::class, 'thread'])->name('thread');
        Route::get('/{message}/attachment', [ClassMessageController::class, 'attachment'])->name('attachment');
    });
    Route::get('/students/archived', [StudentController::class, 'archived'])->name('students.archived');
    Route::patch('/students/{studentId}/restore', [StudentController::class, 'restore'])->name('students.restore');
    Route::post('/students/{student}/reset-password', [StudentController::class, 'resetPassword'])
        ->middleware('role.any:super_administrator,department_administrator')
        ->middleware('permission.any:students.update')
        ->name('students.reset-password');
    Route::resource('students', StudentController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::post('/students/{student}/guardians', [StudentController::class, 'storeGuardian'])->name('students.guardians.store');
    Route::patch('/student-guardians/{guardian}', [StudentController::class, 'updateGuardian'])->name('student-guardians.update');
    Route::delete('/student-guardians/{guardian}', [StudentController::class, 'destroyGuardian'])->name('student-guardians.destroy');
    Route::post('/students/{student}/documents', [StudentController::class, 'storeDocument'])->name('students.documents.store');
    Route::get('/student-documents/{document}/download', [StudentController::class, 'downloadDocument'])->name('student-documents.download');
    Route::delete('/student-documents/{document}', [StudentController::class, 'destroyDocument'])->name('student-documents.destroy');
    Route::get('/teachers/archived', [TeacherController::class, 'archived'])->name('teachers.archived');
    Route::patch('/teachers/{teacherId}/restore', [TeacherController::class, 'restore'])->name('teachers.restore');
    Route::resource('teachers', TeacherController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/course-records/archived', [CourseController::class, 'archived'])->name('course-records.archived');
    Route::patch('/course-records/{courseId}/restore', [CourseController::class, 'restore'])->name('course-records.restore');
    Route::resource('course-records', CourseController::class)
        ->parameters(['course-records' => 'course'])
        ->only(['index', 'create', 'store', 'show', 'edit', 'update', 'destroy']);
    Route::get('/enrollments', [EnrollmentController::class, 'index'])
        ->middleware('permission.any:enrollments.view')
        ->name('enrollments.index');
    Route::get('/module-offerings', [EnrollmentController::class, 'moduleOfferings'])
        ->name('module-offerings.index');
    Route::get('/course-sections/create', [EnrollmentController::class, 'createSection'])
        ->name('course-sections.create');
    Route::get('/course-sections/archived', [EnrollmentController::class, 'archived'])
        ->name('course-sections.archived');
    Route::patch('/course-sections/{sectionId}/restore', [EnrollmentController::class, 'restoreSection'])
        ->name('course-sections.restore');
    Route::post('/course-sections', [EnrollmentController::class, 'storeSection'])
        ->name('course-sections.store');
    Route::get('/course-sections/{courseSection}', [EnrollmentController::class, 'show'])
        ->name('course-sections.show');
    Route::patch('/course-sections/{courseSection}', [EnrollmentController::class, 'updateSection'])
        ->name('course-sections.update');
    Route::delete('/course-sections/{courseSection}', [EnrollmentController::class, 'destroySection'])
        ->name('course-sections.destroy');
    Route::post('/course-sections/{courseSection}/bulk-enroll', [EnrollmentController::class, 'bulkEnroll'])
        ->middleware('permission.any:enrollments.manage')
        ->name('course-sections.bulk-enroll');
    Route::post('/course-sections/{courseSection}/import-roster', [EnrollmentController::class, 'importRoster'])
        ->middleware('permission.any:enrollments.manage')
        ->name('course-sections.import-roster');
    Route::get('/course-sections/{courseSection}/export-roster', [EnrollmentController::class, 'exportRoster'])
        ->middleware('permission.any:enrollments.view')
        ->name('course-sections.export-roster');
    Route::post('/enrollments', [EnrollmentController::class, 'enrollStudent'])
        ->middleware('permission.any:enrollments.manage')
        ->name('enrollments.store');
    Route::patch('/enrollments/{enrollment}/drop', [EnrollmentController::class, 'dropEnrollment'])
        ->middleware('permission.any:enrollments.manage')
        ->name('enrollments.drop');
    Route::patch('/enrollments/{enrollment}/promote', [EnrollmentController::class, 'promoteWaitlist'])
        ->middleware('permission.any:enrollments.manage')
        ->name('enrollments.promote');
    Route::patch('/enrollments/{enrollment}/transfer', [EnrollmentController::class, 'transferEnrollment'])
        ->middleware('permission.any:enrollments.manage')
        ->name('enrollments.transfer');
    Route::get('/timetables', [TimetableController::class, 'index'])->name('timetables.index');
    Route::post('/timetables', [TimetableController::class, 'store'])
        ->middleware('permission.any:timetable.manage')
        ->name('timetables.store');
    Route::patch('/timetables/{timetable}', [TimetableController::class, 'update'])
        ->middleware('permission.any:timetable.manage')
        ->name('timetables.update');
    Route::delete('/timetables/{timetable}', [TimetableController::class, 'destroy'])
        ->middleware('permission.any:timetable.manage')
        ->name('timetables.destroy');
    Route::post('/classrooms', [TimetableController::class, 'storeRoom'])
        ->middleware('permission.any:timetable.manage')
        ->name('classrooms.store');
    Route::resource('colleges', CollegeController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage');
    Route::resource('semesters', SemesterController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage');
    Route::get('/academic-years', [SemesterController::class, 'academicYears'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('academic-years.index');
    Route::get('/academic-years/create', [SemesterController::class, 'createAcademicYear'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-years.create');
    Route::get('/academic-years/{academicYear}', [SemesterController::class, 'showAcademicYear'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('academic-years.show');
    Route::get('/academic-years/{academicYear}/edit', [SemesterController::class, 'editAcademicYear'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-years.edit');
    Route::post('/academic-years', [SemesterController::class, 'storeAcademicYear'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-years.store');
    Route::put('/academic-years/{academicYear}', [SemesterController::class, 'updateAcademicYear'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-years.update');
    Route::resource('universities', UniversityController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage');
    Route::resource('departments', DepartmentController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage');
    Route::resource('stages', StageController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage');
    Route::get('/users/archived', [UserManagementController::class, 'archived'])
        ->middleware('role.any:super_administrator')
        ->name('users.archived');
    Route::patch('/users/{userId}/restore', [UserManagementController::class, 'restore'])
        ->middleware('role.any:super_administrator')
        ->name('users.restore');
    Route::post('/users/{user}/reset-password', [UserManagementController::class, 'resetPassword'])
        ->middleware('role.any:super_administrator,it_support')
        ->name('users.reset-password');
    Route::get('/users/{user}/permissions', [UserManagementController::class, 'editPermissions'])
        ->middleware('role.any:super_administrator')
        ->name('users.permissions.edit');
    Route::patch('/users/{user}/permissions', [UserManagementController::class, 'updatePermissions'])
        ->middleware('role.any:super_administrator')
        ->name('users.permissions.update');
    Route::delete('/users/{user}', [UserManagementController::class, 'destroy'])
        ->middleware('role.any:super_administrator')
        ->name('users.destroy');
    Route::resource('users', UserManagementController::class)
        ->only(['index', 'create', 'store', 'edit', 'update'])
        ->middleware('role.any:super_administrator,it_support');
    Route::get('/activity-log', [ActivityLogController::class, 'index'])
        ->middleware('role.any:super_administrator')
        ->name('activity-log');
    Route::get('/exams', [ErpController::class, 'exams'])
        ->middleware('role.any:administrator,super_administrator,university_administrator,college_administrator,department_administrator,examination_administrator,examination_committee')
        ->middleware('permission.any:marks.view,marks.review,marks.approve,marks.publish')
        ->name('exams');
    Route::get('/exams/export', [ErpController::class, 'exportResults'])
        ->middleware('role.any:administrator,super_administrator,university_administrator,college_administrator,department_administrator,examination_administrator,examination_committee')
        ->middleware('permission.any:marks.view,marks.review,marks.approve,marks.publish')
        ->name('exams.export');
    Route::get('/assessments', [AssessmentController::class, 'index'])->name('assessments.index');
    Route::get('/assessments/export', [AssessmentController::class, 'export'])->name('assessments.export');
    Route::post('/assessment-items', [AssessmentController::class, 'storeItem'])
        ->middleware('permission.any:lms.create_assignment,marks.enter')
        ->name('assessment-items.store');
    Route::get('/assessment-items/{assessmentItem}/edit', [AssessmentController::class, 'edit'])
        ->middleware('permission.any:lms.create_assignment,marks.enter')
        ->name('assessment-items.edit');
    Route::patch('/assessment-items/{assessmentItem}', [AssessmentController::class, 'update'])
        ->middleware('permission.any:lms.create_assignment,marks.enter')
        ->name('assessment-items.update');
    Route::post('/assessment-items/{assessmentItem}/rubric', [AssessmentController::class, 'storeRubric'])
        ->middleware('permission.any:lms.create_assignment,lms.grade_assignment,marks.enter')
        ->name('assessment-items.rubric.store');
    Route::get('/assessment-items/{assessmentItem}/file', [AssessmentController::class, 'downloadItemFile'])
        ->name('assessment-items.file');
    Route::post('/assessment-items/{assessmentItem}/submissions', [AssessmentController::class, 'submitWork'])
        ->middleware('role.any:student')
        ->name('assessment-items.submissions.store');
    Route::patch('/assessment-submissions/{submission}/grade', [AssessmentController::class, 'gradeSubmission'])
        ->middleware('permission.any:lms.grade_assignment,marks.enter')
        ->name('assessment-submissions.grade');
    Route::get('/assessment-submissions/{submission}/file', [AssessmentController::class, 'downloadSubmissionFile'])
        ->name('assessment-submissions.file');
    Route::get('/finance/dashboard', [FinanceController::class, 'dashboard'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view')
        ->name('finance.dashboard');
    Route::get('/finance/approvals', [FinanceController::class, 'approvals'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.approve_payment,permission:finance.approve_expense')
        ->name('finance.approvals.index');
    Route::get('/finance', [FinanceController::class, 'index'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view')
        ->name('finance');
    Route::get('/finance/export', [FinanceController::class, 'export'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view')
        ->middleware('permission.any:reports.financial')
        ->name('finance.export');
    Route::get('/finance/tuition-reminders', [FinanceController::class, 'tuitionReminders'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view,permission:finance.create_invoice,permission:finance.record_payment')
        ->name('finance.tuition-reminders.index');
    Route::get('/finance/students/{student}', [FinanceController::class, 'showStudent'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view')
        ->name('finance.students.show');
    Route::get('/finance/students/{student}/records/create', [FinanceController::class, 'createStudentRecord'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.create_invoice,permission:finance.record_payment,permission:finance.record_expense,permission:finance.refund')
        ->name('finance.students.records.create');
    Route::post('/finance/students/{student}/tuition-charges', [FinanceController::class, 'generateTuitionCharge'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.create_invoice')
        ->name('finance.students.tuition-charges.store');
    Route::get('/finance/students/{student}/statement', [FinanceController::class, 'statement'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view')
        ->name('finance.statement');
    Route::get('/finance/transactions/{financeTransaction}/receipt', [FinanceController::class, 'receipt'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view')
        ->name('finance.transactions.receipt');
    Route::post('/finance/students/{student}/account-block', [FinanceController::class, 'blockStudentAccount'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.create_invoice,permission:finance.record_payment,permission:finance.approve_payment')
        ->name('finance.students.account-block.store');
    Route::delete('/finance/students/{student}/account-block', [FinanceController::class, 'unblockStudentAccount'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.create_invoice,permission:finance.record_payment,permission:finance.approve_payment')
        ->name('finance.students.account-block.destroy');
    Route::post('/finance/transactions', [FinanceController::class, 'store'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.create_invoice,permission:finance.record_payment,permission:finance.record_expense,permission:finance.refund')
        ->name('finance.transactions.store');
    Route::post('/finance/transactions/{financeTransaction}/approve', [FinanceController::class, 'approve'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.approve_payment,permission:finance.approve_expense')
        ->name('finance.transactions.approve');
    Route::post('/finance/transactions/{financeTransaction}/void', [FinanceController::class, 'void'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.refund,permission:finance.approve_payment,permission:finance.approve_expense')
        ->name('finance.transactions.void');
    Route::post('/finance/tuition-reminders', [FinanceController::class, 'sendTuitionReminders'])
        ->middleware('access.any:role:super_administrator,role:chief_accountant,role:accountant,permission:finance.view,permission:finance.create_invoice,permission:finance.record_payment')
        ->name('finance.tuition-reminders.store');
    Route::redirect('/courses', '/course-records')
        ->middleware('permission.any:courses.view')
        ->name('courses');
    Route::get('/bologna-definition', [ErpController::class, 'bolognaDefinition'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('bologna-definition');
    Route::get('/bologna-definition/student-rankings', [ErpController::class, 'studentRankings'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('bologna-definition.student-rankings');
    Route::get('/bologna-definition/student-rankings/export', [ErpController::class, 'exportStudentRankings'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('bologna-definition.student-rankings.export');
    Route::get('/bologna-definition/semester-credit-policy', [ErpController::class, 'semesterCreditPolicy'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('bologna-definition.semester-credit-policy');
    Route::post('/bologna-definition/semester-credit-policy', [ErpController::class, 'storeSemesterCreditPolicy'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('bologna-definition.semester-credit-policy.store');
    Route::get('/bologna-definition/tuition-rates', [ErpController::class, 'tuitionRates'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('bologna-definition.tuition-rates');
    Route::post('/bologna-definition/tuition-rates', [ErpController::class, 'storeTuitionRates'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('bologna-definition.tuition-rates.store');
    Route::get('/academic-year-closing', [AcademicYearClosureController::class, 'index'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('academic-year-closures.index');
    Route::get('/academic-year-archive', [AcademicYearClosureController::class, 'archive'])
        ->middleware('access.any:role:super_administrator,role:administrator,role:university_administrator,role:college_administrator,role:department_administrator,role:examination_administrator,role:examination_committee,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('academic-year-closures.archive');
    Route::get('/academic-year-archive/year', [AcademicYearClosureController::class, 'archiveYear'])
        ->middleware('access.any:role:super_administrator,role:administrator,role:university_administrator,role:college_administrator,role:department_administrator,role:examination_administrator,role:examination_committee,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('academic-year-closures.archive.show');
    Route::get('/academic-year-archive/year/export', [AcademicYearClosureController::class, 'exportArchiveYear'])
        ->middleware('access.any:role:super_administrator,role:administrator,role:university_administrator,role:college_administrator,role:department_administrator,role:examination_administrator,role:examination_committee,permission:academic_setup.view,permission:academic_setup.manage')
        ->name('academic-year-closures.archive.export');
    Route::get('/academic-year-archive/year:academic_year={academicYear}', [AcademicYearClosureController::class, 'archiveYearShortcut'])
        ->where('academicYear', '.*')
        ->middleware('access.any:role:super_administrator,role:administrator,role:university_administrator,role:college_administrator,role:department_administrator,role:examination_administrator,role:examination_committee,permission:academic_setup.view,permission:academic_setup.manage');
    Route::post('/academic-year-closing', [AcademicYearClosureController::class, 'store'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-year-closures.store');
    Route::post('/academic-year-closing/exceptions', [AcademicYearClosureController::class, 'storeProgressionException'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-year-closures.exceptions.store');
    Route::delete('/academic-year-closing/exceptions/{exception}', [AcademicYearClosureController::class, 'destroyProgressionException'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-year-closures.exceptions.destroy');
    Route::patch('/academic-year-closing/students/{student}/stage', [AcademicYearClosureController::class, 'assignStudentStage'])
        ->middleware('access.any:role:super_administrator,role:administrator,permission:academic_setup.manage')
        ->name('academic-year-closures.students.stage');
    Route::get('/access-matrix', [ErpController::class, 'accessMatrix'])
        ->middleware('role.any:super_administrator')
        ->name('access-matrix');
    Route::patch('/access-matrix/roles/{role}/permissions', [ErpController::class, 'updateRolePermissions'])
        ->middleware('role.any:super_administrator')
        ->name('access-matrix.roles.permissions.update');
    Route::get('/attendance', [ErpController::class, 'attendance'])
        ->middleware('role.any:administrator,super_administrator,university_administrator,college_administrator,department_administrator,registrar')
        ->middleware('permission.any:attendance.view,attendance.create,attendance.update')
        ->name('attendance');

    Route::post('/marks/submit', [MarkSubmissionController::class, 'submitMarks'])
        ->middleware('role.any:teacher')
        ->name('marks.submit');

    // Examination review and publication routes
    Route::prefix('marks')->name('marks.')->middleware('role.any:super_administrator,examination_administrator,examination_committee')->group(function () {
        Route::get('/submission-queue', [MarkSubmissionController::class, 'index'])->name('submission-queue');
        Route::get('/final-exam', [MarkSubmissionController::class, 'finalExamEntry'])->name('final-exam.index');
        Route::get('/final-exam/courses/{course}', [MarkSubmissionController::class, 'finalExamCourse'])->name('final-exam.course');
        Route::post('/final-exam', [MarkSubmissionController::class, 'storeFinalExam'])->name('final-exam.store');
        Route::post('/approve', [MarkSubmissionController::class, 'approveMarks'])->name('approve');
        Route::post('/reject', [MarkSubmissionController::class, 'rejectMarks'])->name('reject');
        Route::post('/publish', [MarkSubmissionController::class, 'publishMarks'])->name('publish');
        Route::patch('/prefinal-window/{semester}', [MarkSubmissionController::class, 'togglePrefinalWindow'])->name('prefinal-window.toggle');
    });

    // Attendance Routes
    Route::prefix('courses/{course}/attendance')->name('attendance.')
        ->middleware('role.any:teacher,student,administrator,super_administrator,university_administrator,college_administrator,department_administrator,registrar')
        ->middleware('permission.any:attendance.view,attendance.create,attendance.update')
        ->group(function () {
            Route::get('/', [AttendanceController::class, 'index'])->name('index');
            Route::post('/', [AttendanceController::class, 'store'])->name('store');
            Route::get('/report', [AttendanceController::class, 'report'])->name('report');
            Route::get('/history', [AttendanceController::class, 'history'])->name('history');
            Route::get('/student/{student}/report', [AttendanceController::class, 'studentReport'])->name('student-report');
        });

    // Course Materials Routes
    Route::prefix('courses/{course}/materials')->name('materials.')->middleware('role.any:teacher,student,administrator,super_administrator,university_administrator,college_administrator,department_administrator,lms_administrator')->group(function () {
        Route::get('/', [CourseMaterialController::class, 'index'])->name('index');
        Route::get('/create', [CourseMaterialController::class, 'create'])->name('create');
        Route::post('/', [CourseMaterialController::class, 'store'])->name('store');
        Route::get('/{material}/edit', [CourseMaterialController::class, 'edit'])->name('edit');
        Route::put('/{material}', [CourseMaterialController::class, 'update'])->name('update');
        Route::delete('/{material}', [CourseMaterialController::class, 'destroy'])->name('destroy');
        Route::get('/{material}/download', [CourseMaterialController::class, 'download'])->name('download');
        Route::post('/{material}/publish', [CourseMaterialController::class, 'publish'])->name('publish');
        Route::post('/{material}/unpublish', [CourseMaterialController::class, 'unpublish'])->name('unpublish');
    });

    // Transcript Routes
    Route::prefix('transcripts')->name('transcripts.')->group(function () {
        Route::get('/{student}', [TranscriptController::class, 'show'])->name('show');
        Route::get('/{student}/download', [TranscriptController::class, 'download'])->name('download');
        Route::get('/{student}/preview', [TranscriptController::class, 'preview'])->name('preview');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
