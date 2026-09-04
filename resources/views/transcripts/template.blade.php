<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('Academic Transcript') }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .header {
            text-align: center;
            border-bottom: 3px solid #1e40af;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .institution-name {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
            margin-bottom: 5px;
        }
        
        .subtitle {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .student-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        
        .info-group {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            font-weight: bold;
            color: #1e40af;
            font-size: 12px;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 14px;
            color: #333;
        }
        
        .marks-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        
        .marks-table thead {
            background-color: #1e40af;
            color: white;
        }
        
        .marks-table th {
            padding: 12px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            border: 1px solid #ddd;
        }
        
        .marks-table td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        
        .marks-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        
        .marks-table tbody tr:hover {
            background-color: #f0f0f0;
        }
        
        .mark-value {
            font-weight: bold;
            color: #1e40af;
        }
        
        .grade-excellent {
            color: #10b981;
            font-weight: bold;
        }
        
        .grade-good {
            color: #3b82f6;
            font-weight: bold;
        }
        
        .grade-fair {
            color: #f59e0b;
            font-weight: bold;
        }
        
        .grade-poor {
            color: #ef4444;
            font-weight: bold;
        }
        
        .summary {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            padding: 15px;
            background-color: #f5f5f5;
            border-left: 4px solid #1e40af;
            border-radius: 4px;
        }
        
        .summary-label {
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .summary-value {
            font-size: 24px;
            font-weight: bold;
            color: #1e40af;
        }
        
        .footer {
            border-top: 1px solid #ddd;
            padding-top: 20px;
            text-align: center;
            font-size: 11px;
            color: #666;
        }
        
        .timestamp {
            margin-top: 10px;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="institution-name">{{ $institution }}</div>
            <div class="subtitle">{{ __('Academic Transcript') }}</div>
        </div>

        <div class="student-info">
            <div class="info-group">
                <span class="info-label">{{ __('Student Name') }}</span>
                <span class="info-value">{{ $student->full_name }}</span>
            </div>
            <div class="info-group">
                <span class="info-label">{{ __('Student ID') }}</span>
                <span class="info-value">{{ $student->student_id }}</span>
            </div>
            <div class="info-group">
                <span class="info-label">{{ __('Email') }}</span>
                <span class="info-value">{{ $student->email }}</span>
            </div>
            <div class="info-group">
                <span class="info-label">{{ __('Department') }}</span>
                <span class="info-value">{{ $student->department?->name ?? __('N/A') }}</span>
            </div>
        </div>

        <div class="summary">
            <div class="summary-card">
                <div class="summary-label">{{ __('Cumulative GPA') }}</div>
                <div class="summary-value">{{ number_format($gpa, 2) }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">{{ __('Total Credits') }}</div>
                <div class="summary-value">{{ $total_credits }}</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">{{ __('Courses Completed') }}</div>
                <div class="summary-value">{{ $completed_courses }}</div>
            </div>
        </div>

        @if($marks->isNotEmpty())
            <table class="marks-table">
                <thead>
                    <tr>
                        <th>{{ __('Course Code') }}</th>
                        <th>{{ __('Course Name') }}</th>
                        <th>{{ __('Department') }}</th>
                        <th>{{ __('Semester') }}</th>
                        <th>{{ __('Credits') }}</th>
                        <th>{{ __('Mark') }}</th>
                        <th>{{ __('Grade') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($marks as $mark)
                        <tr>
                            <td>{{ $mark->course->code ?? __('N/A') }}</td>
                            <td>{{ $mark->course->name ?? __('N/A') }}</td>
                            <td>{{ $mark->course->department?->name ?? __('N/A') }}</td>
                            <td>
                                {{ $mark->courseSection?->semester
                                    ? $mark->courseSection->semester->name.' '.$mark->courseSection->semester->academic_year
                                    : __('N/A') }}
                            </td>
                            <td>{{ $mark->course->credits ?? 0 }}</td>
                            <td>
                                <span class="mark-value">{{ $mark->final_mark ?? __('N/A') }}</span>
                            </td>
                            <td>
                                @php
                                    $grade = app('App\Services\TranscriptService')->getLetterGrade($mark->final_mark ?? 0);
                                    $gradeClass = match($grade) {
                                        'A' => 'grade-excellent',
                                        'B' => 'grade-excellent',
                                        'C' => 'grade-good',
                                        'D' => 'grade-fair',
                                        'E', 'F' => 'grade-poor',
                                        default => '',
                                    };
                                @endphp
                                <span class="{{ $gradeClass }}">{{ $grade }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div style="text-align: center; padding: 20px; color: #999;">
                {{ __('No published marks available yet.') }}
            </div>
        @endif

        <div class="footer">
            <div>{{ __('System-generated academic transcript issued by :institution', ['institution' => $institution]) }}</div>
            <div class="timestamp">{{ __('Generated on: :date', ['date' => $generated_at->format('F j, Y \a\t g:i A')]) }}</div>
            <div style="margin-top: 5px; color: #999; font-size: 10px;">
                {{ __('Document Reference: :reference', ['reference' => md5($student->id . $generated_at->timestamp)]) }}
            </div>
        </div>
    </div>
</body>
</html>
