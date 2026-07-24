# LMS Enhancement Implementation Guide

## ✅ Completed Features (Phase 1)

### 1. Activity Logging & Audit Trail
- **Model**: `App\Models\ActivityLog`
- **Trait**: `App\Traits\Loggable` (attach to any model for automatic logging)
- **Database**: `activity_logs` table tracks all create/update/delete operations
- **Tests**: 4 passing tests in `tests/Feature/ActivityLoggingTest.php`

### 2. Soft Deletes
- **Applied to**: User, Student, Course, Teacher, Mark, Department
- **Migration**: `database/migrations/2026_07_09_000002_add_soft_deletes_to_tables.php`
- **Usage**: `$user->delete()` soft deletes, `$user->forceDelete()` permanent delete
- **Query**: `User::withTrashed()` includes deleted records, `User::onlyTrashed()` only deleted
- **Tests**: 5 passing tests in `tests/Feature/SoftDeletesTest.php`

### 3. Role/Permission Change Logs
- **Service**: `App\Services\RolePermissionService`
- **Observer**: `App\Observers\RolePermissionObserver`
- **Methods**: `assignRoleToUser()`, `removeRoleFromUser()`, `syncUserRoles()`, `assignPermissionToRole()`, etc.
- **Logging**: All role/permission changes automatically logged to `activity_logs` table
- **Tests**: 5 passing tests in `tests/Feature/RolePermissionLoggingTest.php`

### Statistics
- **Total Tests**: 54 passing
- **Total Assertions**: 127
- **Test Duration**: ~3 seconds

---

## 📋 Remaining Features (Implementation Templates)

### 5. Mark Submission & Approval Workflow

Create migration to add mark status tracking:
```php
Schema::table('marks', function (Blueprint $table) {
    $table->enum('submission_status', ['draft', 'submitted', 'under_review', 'approved', 'rejected'])->default('draft');
    $table->enum('visibility_status', ['draft', 'published'])->default('draft');
    $table->text('reviewer_notes')->nullable();
    $table->timestamp('submitted_at')->nullable();
    $table->timestamp('reviewed_at')->nullable();
    $table->timestamp('published_at')->nullable();
    $table->unsignedBigInteger('reviewed_by')->nullable();
});
```

Create Mark service:
```php
class MarkService {
    public function submitMarks($markIds) { /* ... */ }
    public function reviewMarks($markIds, $status, $notes) { /* ... */ }
    public function publishMarks($courseId, $semesterId) { /* ... */ }
}
```

Create controller: `MarkController@submitForReview`, `MarkController@approveMarks`, `MarkController@publishMarks`

### 6. Attendance Tracking

Create migration:
```php
Schema::create('attendances', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('course_id');
    $table->unsignedBigInteger('student_id');
    $table->date('date');
    $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
    $table->text('remarks')->nullable();
    $table->timestamps();
    $table->softDeletes();
    $table->unique(['course_id', 'student_id', 'date']);
});
```

Create `Attendance` model and `AttendanceService`

### 7. Course Materials Management

Create migration:
```php
Schema::create('course_materials', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('course_id');
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('file_path')->nullable();
    $table->string('file_type'); // pdf, doc, video, etc
    $table->enum('visibility', ['draft', 'published'])->default('draft');
    $table->timestamps();
    $table->softDeletes();
});
```

### 8. Department Analytics

Create queries in `DepartmentAnalytics` service:
- Student enrollment by department
- Pass/fail rates by course
- GPA distribution
- Faculty load analysis
- Course capacity utilization

### 9. PDF Export (Transcripts)

Use `barryvdh/laravel-dompdf`:
```php
composer require barryvdh/laravel-dompdf

// Usage
$pdf = PDF::loadView('transcripts.template', $data)->download('transcript.pdf');
```

### 10. Bulk CSV Import

Create `CsvImportService` with:
- Student bulk import
- Mark bulk entry
- Attendance bulk import
- Validation using `Laravel Excel`

### 11. Bulk Mark Entry

Create modal/form for batch mark entry:
```php
// Route: POST /marks/bulk-submit
public function bulkSubmit(BulkMarkRequest $request) {
    // Validate array of marks
    // Save all at once
    // Log to activity table
}
```

### 12. Timetable Management

Create migration and models:
```php
Schema::create('timetables', function (Blueprint $table) {
    $table->id();
    $table->unsignedBigInteger('course_id');
    $table->unsignedBigInteger('teacher_id');
    $table->enum('day_of_week', ['Monday', ..., 'Friday']);
    $table->time('start_time');
    $table->time('end_time');
    $table->string('room_number')->nullable();
    $table->enum('type', ['lecture', 'lab', 'tutorial']);
    $table->timestamps();
});
```

### 13-15. Email Notifications

Setup Laravel Mail:
```php
// config/mail.php - SMTP configuration
Mail::send(new MarkPostedNotification($mark));
Mail::send(new CourseEnrollmentNotification($student, $course));
Mail::send(new LowPerformanceWarning($student, $course));
```

Create Mailable classes in `app/Mail/`

### 16. RESTful API

Use Laravel Sanctum for token authentication:
```php
composer require laravel/sanctum

// Create API routes in routes/api.php
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('marks', ApiMarkController::class);
    Route::apiResource('students', ApiStudentController::class);
    Route::apiResource('courses', ApiCourseController::class);
});
```

### 17. Sanctum Authentication

```php
// Token generation
$token = $user->createToken('api-token')->plainTextToken;

// Validate in middleware
$user = Auth::guard('sanctum')->user();
```

### 18. Database Caching

```php
// Cache queries
$courses = Cache::remember('courses.all', 3600, function () {
    return Course::with('department')->get();
});

// Cache tags
Cache::tags(['courses'])->put('courses.all', $courses, 3600);
```

### 19. Full-Text Search Indexing

Add to models:
```php
public function toSearchableArray() {
    return [
        'name' => $this->name,
        'code' => $this->code,
        'department' => $this->department->name,
    ];
}

// Use Scout for full-text search
```

### 20. Lazy Load Relationships

Update models with `LazilyLoadAfterCount`:
```php
// Prevent N+1 queries
$courses = Course::with(['department', 'students', 'marks'])->paginate();

// Use lazy loading
$marks = $course->marks()->lazy()->each(function ($mark) {
    // Process each mark
});
```

---

## Implementation Priority

1. **Quick Wins** (1-2 hours):
   - Mark submission & approval
   - Attendance tracking
   - Course materials management

2. **Medium Effort** (2-4 hours):
   - PDF export
   - Email notifications
   - Department analytics

3. **Advanced** (4+ hours):
   - Bulk CSV import (requires validation)
   - API layer with Sanctum
   - Full-text search indexing

---

## Testing Pattern

Each feature should have:
- Feature test in `tests/Feature/{Feature}Test.php`
- Unit tests in `tests/Unit/` if needed
- Migration tests to verify database changes
- Example: `php artisan test tests/Feature/MarkSubmissionTest.php`

---

## Database Migrations

Run pending migrations:
```bash
php artisan migrate
php artisan migrate:status  # Check status
```

All code should maintain:
- Soft delete support
- Activity logging (add `Loggable` trait)
- RBAC enforcement
- Comprehensive test coverage
