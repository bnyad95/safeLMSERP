<?php

return [
    'pass_mark' => 50.0,
    'prefinal_mark_max' => (float) env('PREFINAL_MARK_MAX', 100),
    'final_exam_mark_max' => (float) env('FINAL_EXAM_MARK_MAX', 100),
    'total_mark_max' => (float) env('TOTAL_MARK_MAX', 100),
    'attendance_warning_percent' => (float) env('ATTENDANCE_WARNING_PERCENT', 75),
    'attended_statuses' => ['present', 'late', 'excused'],
    'archive_inline_record_limit' => (int) env('ARCHIVE_INLINE_RECORD_LIMIT', 5000),
];
