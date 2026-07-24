<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>Enrollment Confirmation</title></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #1e40af;">Enrollment Confirmation</h2>
    <p>Dear {{ $student->full_name }},</p>
    <p>You have been successfully enrolled in the following course:</p>
    <div style="background: #f0f7ff; border-left: 4px solid #1e40af; padding: 16px; margin: 16px 0;">
        <strong>Course:</strong> {{ $course->name }}<br>
        <strong>Code:</strong> {{ $course->code }}<br>
        <strong>Department:</strong> {{ $course->department?->name ?? 'N/A' }}<br>
        <strong>Credits:</strong> {{ $course->credits }}
    </div>
    <p>Please ensure you attend all classes and submit assignments on time.</p>
    <p>Best regards,<br>{{ config('app.name') }}</p>
</body>
</html>
