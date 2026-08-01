<?php

namespace App\Services;

use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AttendanceService
{
    public function recordAttendance(int $courseId, array $studentAttendance, ?int $sectionId = null, ?string $date = null): array
    {
        $created = 0;
        $updated = 0;
        $attendanceDate = $date ? Carbon::parse($date)->toDateString() : now()->format('Y-m-d');

        foreach ($studentAttendance as $studentId => $status) {
            $attendance = Attendance::updateOrCreate(
                [
                    'course_id' => $courseId,
                    'course_section_id' => $sectionId,
                    'student_id' => $studentId,
                    'date' => $attendanceDate,
                ],
                [
                    'status' => $status,
                ]
            );

            if ($attendance->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            app(NotificationService::class)->notifyAttendanceAlert($attendance);
        }

        return ['created' => $created, 'updated' => $updated];
    }

    public function getAttendanceReport(int $courseId, int $studentId, ?int $sectionId = null): array
    {
        $attendances = Attendance::where('course_id', $courseId)
            ->where('student_id', $studentId)
            ->when($sectionId, fn ($query) => $query->where('course_section_id', $sectionId))
            ->orderBy('date', 'desc')
            ->get();

        $total = $attendances->count();
        $present = $attendances->where('status', 'present')->count();
        $absent = $attendances->where('status', 'absent')->count();
        $late = $attendances->where('status', 'late')->count();
        $excused = $attendances->where('status', 'excused')->count();

        $attended = $present + $late + $excused;
        $percentage = $total > 0 ? round(($attended / $total) * 100, 2) : 0;

        return [
            'total_classes' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'excused' => $excused,
            'attendance_percentage' => $percentage,
            'attendances' => $attendances,
        ];
    }

    public function getCourseAttendanceStats(int $courseId, ?int $sectionId = null): array
    {
        $attendances = Attendance::where('course_id', $courseId)
            ->when($sectionId, fn ($query) => $query->where('course_section_id', $sectionId))
            ->with('student')
            ->get()
            ->groupBy('student_id');

        $stats = [];
        foreach ($attendances as $studentId => $records) {
            $total = $records->count();
            $present = $records->where('status', 'present')->count();
            $attended = $records->whereIn('status', ['present', 'late', 'excused'])->count();
            $percentage = $total > 0 ? round(($attended / $total) * 100, 2) : 0;

            $stats[] = [
                'student_id' => $studentId,
                'student_name' => $records->first()->student->full_name,
                'total_classes' => $total,
                'present' => $present,
                'absent' => $records->where('status', 'absent')->count(),
                'late' => $records->where('status', 'late')->count(),
                'excused' => $records->where('status', 'excused')->count(),
                'percentage' => $percentage,
            ];
        }

        return $stats;
    }

    public function bulkImportAttendance(int $courseId, \DateTime $date, array $attendance, ?int $sectionId = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($attendance as $studentId => $status) {
            $record = Attendance::updateOrCreate(
                [
                    'course_id' => $courseId,
                    'course_section_id' => $sectionId,
                    'student_id' => $studentId,
                    'date' => $date->format('Y-m-d'),
                ],
                [
                    'status' => $status,
                ]
            );

            if ($record->wasRecentlyCreated) {
                $created++;
            } else {
                $updated++;
            }

            app(NotificationService::class)->notifyAttendanceAlert($record);
        }

        return ['created' => $created, 'updated' => $updated];
    }
}
