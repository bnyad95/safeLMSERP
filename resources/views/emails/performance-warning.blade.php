<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Performance Warning</title></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #dc2626;">Academic Performance Warning</h2>
    <p>Dear {{ $student->full_name }},</p>

    @if($reason === 'low_attendance')
        <p>We are writing to inform you that your attendance in <strong>{{ $details['course'] }}</strong> has fallen below the required 75% threshold.</p>
        <div style="background: #fff5f5; border-left: 4px solid #dc2626; padding: 16px; margin: 16px 0;">
            <strong>Course:</strong> {{ $details['course'] }}<br>
            <strong>Attendance:</strong> {{ $details['percentage'] }}% ({{ $details['present'] }}/{{ $details['total'] }} classes)
        </div>
        <p>Please ensure regular attendance to avoid academic penalties.</p>
    @elseif($reason === 'low_mark')
        <p>We are writing to inform you that your final mark in <strong>{{ $details['course'] }}</strong> is below the passing threshold.</p>
        <div style="background: #fff5f5; border-left: 4px solid #dc2626; padding: 16px; margin: 16px 0;">
            <strong>Course:</strong> {{ $details['course'] }}<br>
            <strong>Final Mark:</strong> {{ $details['mark'] }}/100
        </div>
        <p>Please contact your department advisor for guidance.</p>
    @endif

    <p>Best regards,<br>{{ config('app.name') }}</p>
</body>
</html>
