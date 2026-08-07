<?php

namespace App\Services;

use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Mark;
use App\Models\SemesterCreditPolicy;
use App\Models\Stage;
use App\Models\Student;
use App\Models\StudentProgressionException;
use App\Models\StudentStageProgression;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StudentProgressionService
{
    public function readiness(Collection $sectionIds, string $academicYear, int $detailLimit = 100): array
    {
        $sectionIds = $sectionIds->filter()->unique()->values();
        if ($sectionIds->isEmpty()) {
            return $this->emptyReadiness();
        }

        $sectionsWithoutStage = CourseSection::withTrashed()
            ->with(['course', 'semester'])
            ->whereIn('id', $sectionIds)
            ->whereNull('stage_id')
            ->orderBy('semester_id')
            ->orderBy('section_code')
            ->get();

        $missingResultQuery = $this->resultEnrollmentQuery($sectionIds, $academicYear)
            ->whereNotExists(fn (Builder $query) => $this->markExistsSubquery($query, false));
        $unusableResultQuery = $this->resultEnrollmentQuery($sectionIds, $academicYear)
            ->whereExists(fn (Builder $query) => $this->markExistsSubquery($query, false))
            ->whereNotExists(fn (Builder $query) => $this->markExistsSubquery($query, true));
        $missingCurrentStageQuery = $this->resultEnrollmentQuery($sectionIds, $academicYear)
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->whereNull('students.current_stage_id');
        $currentStageMismatchQuery = $this->resultEnrollmentQuery($sectionIds, $academicYear)
            ->join('students', 'students.id', '=', 'enrollments.student_id')
            ->join('course_sections', 'course_sections.id', '=', 'enrollments.course_section_id')
            ->join('stages as student_stages', 'student_stages.id', '=', 'students.current_stage_id')
            ->join('stages as module_stages', 'module_stages.id', '=', 'course_sections.stage_id')
            ->whereNotNull('students.current_stage_id')
            ->whereNotNull('course_sections.stage_id')
            ->where(function (Builder $query) {
                $query->where(function (Builder $ordinary) {
                    $ordinary->where('enrollments.is_retake', false)
                        ->whereColumn('students.current_stage_id', '!=', 'course_sections.stage_id');
                })->orWhere(function (Builder $retake) {
                    $retake->where('enrollments.is_retake', true)
                        ->whereColumn('module_stages.sequence', '>', 'student_stages.sequence');
                });
            });
        $conflictingStageQuery = $this->resultEnrollmentQuery($sectionIds, $academicYear)
            ->join('course_sections', 'course_sections.id', '=', 'enrollments.course_section_id')
            ->where('enrollments.is_retake', false)
            ->whereNotNull('course_sections.stage_id')
            ->groupBy('enrollments.student_id')
            ->havingRaw('COUNT(DISTINCT course_sections.stage_id) > 1');

        $unresolvedUnion = (clone $missingResultQuery)->select('enrollments.student_id')
            ->union((clone $unusableResultQuery)->select('enrollments.student_id'))
            ->union((clone $missingCurrentStageQuery)->select('enrollments.student_id'))
            ->union((clone $currentStageMismatchQuery)->select('enrollments.student_id'))
            ->union((clone $conflictingStageQuery)->select('enrollments.student_id'));
        $unresolvedQuery = DB::query()->fromSub($unresolvedUnion, 'unresolved_students');
        $unresolvedStudentCount = (clone $unresolvedQuery)->distinct()->count('student_id');
        $detailStudentIds = $detailLimit > 0
            ? (clone $unresolvedQuery)->distinct()->orderBy('student_id')->limit($detailLimit)->pluck('student_id')
            : collect();

        $students = Student::with(['currentStage', 'department.stages'])
            ->whereIn('id', $detailStudentIds)
            ->get()
            ->keyBy('id');
        $detailEnrollments = Enrollment::withTrashed()
            ->with(['courseSection.stage', 'courseSection.course'])
            ->whereIn('course_section_id', $sectionIds)
            ->whereIn('status', ['enrolled', 'completed'])
            ->whereIn('student_id', $detailStudentIds)
            ->get()
            ->groupBy('student_id');
        $detailMarks = Mark::withTrashed()
            ->whereIn('course_section_id', $sectionIds)
            ->whereIn('student_id', $detailStudentIds)
            ->get()
            ->groupBy(fn (Mark $mark) => $mark->student_id.':'.$mark->course_section_id);

        $unresolvedStudents = $detailStudentIds->map(function ($studentId) use ($students, $detailEnrollments, $detailMarks) {
            $student = $students->get($studentId);
            if (! $student) {
                return null;
            }

            $enrollments = $detailEnrollments->get($studentId, collect());
            $issues = collect();
            if (! $student->current_stage_id) {
                $issues->push('Current stage is not assigned.');
            }

            $ordinaryStageIds = $enrollments
                ->where('is_retake', false)
                ->pluck('courseSection.stage_id')
                ->filter()
                ->unique();
            if ($ordinaryStageIds->count() > 1) {
                $issues->push('Ordinary module enrollments use conflicting stages.');
            } elseif ($student->current_stage_id && $ordinaryStageIds->isNotEmpty() && ! $ordinaryStageIds->contains($student->current_stage_id)) {
                $issues->push('Current stage does not match the ordinary module stage.');
            }

            if ($student->currentStage && $enrollments
                ->where('is_retake', true)
                ->contains(fn (Enrollment $enrollment) => (int) ($enrollment->courseSection?->stage?->sequence ?? 0) > (int) $student->currentStage->sequence)) {
                $issues->push('A retake module is above the student current stage.');
            }

            foreach ($enrollments as $enrollment) {
                $marks = $detailMarks->get($studentId.':'.$enrollment->course_section_id, collect());
                $module = trim(($enrollment->courseSection?->course?->code ?? 'Module').' / Group '.($enrollment->courseSection?->section_code ?? '-'));
                if ($marks->isEmpty()) {
                    $issues->push('Missing result: '.$module.'.');
                } elseif (! $marks->contains(fn (Mark $mark) => $mark->visibility_status === 'published' && $mark->final_mark !== null)) {
                    $issues->push('Result is unpublished or incomplete: '.$module.'.');
                }
            }

            $inferredStage = $enrollments
                ->pluck('courseSection.stage')
                ->filter()
                ->sortByDesc('sequence')
                ->first();

            return [
                'student' => $student,
                'issues' => $issues->unique()->values(),
                'inferred_stage' => $inferredStage,
                'stage_options' => $student->department?->stages?->sortBy('sequence')->values() ?? collect(),
            ];
        })->filter()->values();

        $approvedExceptions = StudentProgressionException::with(['student.currentStage', 'approvedBy'])
            ->where('academic_year', $academicYear)
            ->whereIn('student_id', $this->expectedStudentQuery($sectionIds)->pluck('student_id'))
            ->latest('approved_at')
            ->limit(100)
            ->get();
        $approvedExceptionCount = StudentProgressionException::where('academic_year', $academicYear)
            ->whereIn('student_id', $this->expectedStudentQuery($sectionIds)->pluck('student_id'))
            ->count();

        return [
            'sections_without_stage' => $sectionsWithoutStage,
            'sections_without_stage_count' => $sectionsWithoutStage->count(),
            'missing_result_count' => (clone $missingResultQuery)->count(),
            'unusable_result_count' => (clone $unusableResultQuery)->count(),
            'missing_current_stage_count' => (clone $missingCurrentStageQuery)->distinct()->count('enrollments.student_id'),
            'current_stage_mismatch_count' => (clone $currentStageMismatchQuery)->distinct()->count('enrollments.student_id'),
            'conflicting_stage_count' => DB::query()->fromSub((clone $conflictingStageQuery)->select('enrollments.student_id'), 'conflicts')->count(),
            'unresolved_student_count' => $unresolvedStudentCount,
            'unresolved_students' => $unresolvedStudents,
            'unresolved_students_truncated' => $unresolvedStudentCount > $unresolvedStudents->count(),
            'approved_exceptions' => $approvedExceptions,
            'approved_exception_count' => $approvedExceptionCount,
            'blockers_count' => $sectionsWithoutStage->count() + $unresolvedStudentCount,
        ];
    }

    public function process(Collection $sectionIds, string $academicYear, User $actor): array
    {
        $sectionIds = $sectionIds->filter()->unique()->values();
        $readiness = $this->readiness($sectionIds, $academicYear, 0);
        if ($readiness['blockers_count'] > 0) {
            throw new RuntimeException('Every enrolled student must have a progression decision or approved exception before closing the academic year.');
        }

        $studentIds = $this->expectedStudentQuery($sectionIds)->pluck('student_id')->unique()->values();
        $exceptions = StudentProgressionException::where('academic_year', $academicYear)
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy('student_id');
        $policies = SemesterCreditPolicy::whereIn(
            'university_id',
            Student::whereIn('id', $studentIds)->pluck('university_id')->filter()->unique()
        )->get()->keyBy('university_id');
        $report = [
            'expected_students' => $studentIds->count(),
            'decided_students' => 0,
            'promoted' => 0,
            'promoted_with_retakes' => 0,
            'retake_only' => 0,
            'repeat_stage' => 0,
            'extended' => 0,
            'completed_program' => 0,
            'awaiting_stage_setup' => 0,
            'approved_exceptions' => 0,
        ];

        foreach ($studentIds->chunk(500) as $chunk) {
            $students = Student::with(['university', 'currentStage'])->whereIn('id', $chunk)->get()->keyBy('id');
            $marksByStudent = Mark::with(['course', 'courseSection.stage', 'courseSection.semester'])
                ->whereIn('course_section_id', $sectionIds)
                ->whereIn('student_id', $chunk)
                ->where('visibility_status', 'published')
                ->whereNotNull('final_mark')
                ->get()
                ->groupBy('student_id');

            foreach ($chunk as $studentId) {
                $student = $students->get($studentId);
                if (! $student) {
                    throw new RuntimeException('An enrolled student could not be loaded for progression.');
                }

                $exception = $exceptions->get($studentId);
                if ($exception) {
                    $student->update(['academic_standing' => $exception->status]);
                    $report['approved_exceptions']++;
                    $report['decided_students']++;
                    continue;
                }

                $outcome = $this->processStudent(
                    $student,
                    $marksByStudent->get($studentId, collect()),
                    $policies->get($student->university_id),
                    $academicYear,
                    $actor
                );
                $report[$outcome]++;
                $report['decided_students']++;
            }
        }

        if ($report['decided_students'] !== $report['expected_students']) {
            throw new RuntimeException('Academic-year closing stopped because not every enrolled student received a decision.');
        }

        return $report;
    }

    private function processStudent(Student $student, Collection $studentMarks, ?SemesterCreditPolicy $policy, string $academicYear, User $actor): string
    {
        $stageMarks = $studentMarks->filter(fn (Mark $mark) => $mark->courseSection?->stage);
        $currentStage = $student->currentStage;
        if (! $student->current_stage_id || ! $currentStage || $stageMarks->isEmpty()) {
            throw new RuntimeException('Student '.$student->student_id.' is missing a current stage or usable staged results.');
        }

        if ($stageMarks->contains(fn (Mark $mark) => (int) $mark->courseSection->stage->sequence > (int) $currentStage->sequence)) {
            throw new RuntimeException('Student '.$student->student_id.' has results from a stage above the recorded current stage.');
        }

        $latestMarks = $stageMarks
            ->groupBy('course_id')
            ->map(fn ($attempts) => $attempts->sortByDesc(fn (Mark $mark) => $mark->published_at?->getTimestamp() ?? $mark->created_at?->getTimestamp() ?? 0)->first());
        $currentStageMarks = $latestMarks
            ->filter(fn (Mark $mark) => (int) $mark->courseSection->stage_id === (int) $currentStage->id);

        $passMark = (float) config('academics.pass_mark', 50);
        $failedModules = $latestMarks->filter(fn (Mark $mark) => (float) $mark->final_mark < $passMark)->count();
        $attemptedCredits = (float) $latestMarks->sum(fn (Mark $mark) => (float) ($mark->course?->credits ?? 0));
        $earnedCredits = (float) $latestMarks
            ->filter(fn (Mark $mark) => (float) $mark->final_mark >= $passMark)
            ->sum(fn (Mark $mark) => (float) ($mark->course?->credits ?? 0));
        if ($currentStageMarks->isEmpty()) {
            $standing = $failedModules > 0 ? Student::STANDING_CARRYING_RETAKES : Student::STANDING_GOOD;
            $outcome = 'retake_only';
            $nextStage = null;
        } else {
            $currentStageEarnedCredits = (float) $currentStageMarks
                ->filter(fn (Mark $mark) => (float) $mark->final_mark >= $passMark)
                ->sum(fn (Mark $mark) => (float) ($mark->course?->credits ?? 0));
            $regularSemesterCount = max(1, $currentStageMarks
                ->filter(fn (Mark $mark) => ($mark->courseSection?->semester?->term_type ?? 'regular') === 'regular')
                ->pluck('courseSection.semester_id')
                ->filter()
                ->unique()
                ->count());
            $progressionEligible = $policy
                ? $currentStageEarnedCredits >= ((int) $policy->passing_credits * $regularSemesterCount)
                : $currentStageMarks->every(fn (Mark $mark) => (float) $mark->final_mark >= $passMark);

            [$standing, $outcome, $nextStage] = $this->outcome($student, $currentStage, $progressionEligible, $failedModules);
        }
        $existing = StudentStageProgression::where([
            'student_id' => $student->id,
            'academic_year' => $academicYear,
            'stage_id' => $currentStage->id,
        ])->first();
        $attemptNumber = $existing?->attempt_number
            ?? StudentStageProgression::where('student_id', $student->id)->where('stage_id', $currentStage->id)->count() + 1;

        StudentStageProgression::updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_year' => $academicYear,
                'stage_id' => $currentStage->id,
            ],
            [
                'next_stage_id' => $nextStage?->id,
                'attempt_number' => $attemptNumber,
                'outcome' => $outcome,
                'attempted_credits' => $attemptedCredits,
                'earned_credits' => $earnedCredits,
                'failed_modules' => $failedModules,
                'decided_by' => $actor->id,
                'decided_at' => now(),
            ]
        );

        $student->update([
            'current_stage_id' => $nextStage?->id ?? $currentStage->id,
            'academic_standing' => $standing,
        ]);

        return $outcome;
    }

    private function resultEnrollmentQuery(Collection $sectionIds, string $academicYear): Builder
    {
        return $this->excludeApprovedExceptions(
            DB::table('enrollments')
                ->whereIn('enrollments.course_section_id', $sectionIds)
                ->whereIn('enrollments.status', ['enrolled', 'completed'])
                ->whereNull('enrollments.deleted_at'),
            $academicYear
        );
    }

    private function expectedStudentQuery(Collection $sectionIds): Builder
    {
        return DB::table('enrollments')
            ->select('student_id')
            ->whereIn('course_section_id', $sectionIds)
            ->whereIn('status', ['enrolled', 'completed'])
            ->whereNull('deleted_at')
            ->distinct();
    }

    private function excludeApprovedExceptions(Builder $query, string $academicYear): Builder
    {
        return $query->whereNotExists(function (Builder $exceptions) use ($academicYear) {
            $exceptions->selectRaw('1')
                ->from('student_progression_exceptions')
                ->whereColumn('student_progression_exceptions.student_id', 'enrollments.student_id')
                ->where('student_progression_exceptions.academic_year', $academicYear);
        });
    }

    private function markExistsSubquery(Builder $query, bool $usable): Builder
    {
        $query->selectRaw('1')
            ->from('marks')
            ->whereColumn('marks.student_id', 'enrollments.student_id')
            ->whereColumn('marks.course_section_id', 'enrollments.course_section_id')
            ->whereNull('marks.deleted_at');

        if ($usable) {
            $query->where('marks.visibility_status', 'published')->whereNotNull('marks.final_mark');
        }

        return $query;
    }

    private function emptyReadiness(): array
    {
        return [
            'sections_without_stage' => collect(),
            'sections_without_stage_count' => 0,
            'missing_result_count' => 0,
            'unusable_result_count' => 0,
            'missing_current_stage_count' => 0,
            'current_stage_mismatch_count' => 0,
            'conflicting_stage_count' => 0,
            'unresolved_student_count' => 0,
            'unresolved_students' => collect(),
            'unresolved_students_truncated' => false,
            'approved_exceptions' => collect(),
            'approved_exception_count' => 0,
            'blockers_count' => 0,
        ];
    }

    private function outcome(Student $student, Stage $stage, bool $eligible, int $failedModules): array
    {
        $finalStage = (int) $stage->sequence >= $student->university->expectedStageCount();
        if ($finalStage) {
            return $eligible && $failedModules === 0
                ? [Student::STANDING_COMPLETED_PROGRAM, 'completed_program', null]
                : [Student::STANDING_EXTENDED, 'extended', null];
        }

        if (! $eligible) {
            return [Student::STANDING_REPEAT_STAGE, 'repeat_stage', null];
        }

        $nextStage = Stage::where('department_id', $student->department_id)
            ->where('sequence', (int) $stage->sequence + 1)
            ->first();
        if (! $nextStage) {
            return [Student::STANDING_AWAITING_STAGE_SETUP, 'awaiting_stage_setup', null];
        }

        return [
            $failedModules > 0 ? Student::STANDING_CARRYING_RETAKES : Student::STANDING_GOOD,
            $failedModules > 0 ? 'promoted_with_retakes' : 'promoted',
            $nextStage,
        ];
    }
}
