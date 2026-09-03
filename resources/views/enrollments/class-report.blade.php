<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Class Report - {{ $section->course->code }} {{ $section->section_code }}</title>

        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @else
            <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        @endif

        <style>
            @page {
                size: A4 landscape;
                margin: 15mm;
            }
            .a4-width {
                width: 277mm;
            }
            .a4-page {
                width: 277mm;
                min-height: 190mm;
            }
            @media print {
                .print-actions { display: none !important; }
                body { background: #fff !important; }
                .a4-page {
                    width: auto;
                    min-height: 0;
                    margin: 0 !important;
                    box-shadow: none !important;
                }
            }
        </style>
    </head>
    <body class="bg-gray-200 font-sans text-gray-900">
        <main class="px-4 py-8">
            @unless(request()->boolean('embed'))
                <div class="a4-width print-actions mx-auto mb-5 flex flex-wrap justify-between gap-3">
                    <a href="{{ route('course-sections.show', $section) }}" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        {{ __('Back to Class') }}
                    </a>
                    <button type="button" onclick="window.print()" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                        {{ __('Print Report') }}
                    </button>
                </div>
            @endunless

            <section class="a4-page mx-auto rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-row items-start justify-between gap-5 border-b border-gray-200 pb-6">
                    <div>
                        <p class="text-sm font-semibold uppercase text-gray-500">{{ __('Class Report') }}</p>
                        <h1 class="mt-2 text-2xl font-semibold text-gray-900">{{ $section->course->code }} - {{ $section->course->name }}</h1>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Group :code', ['code' => $section->section_code]) }} / {{ $section->grade_level ?: __('No stage') }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ $section->semester->name ?? '' }} {{ $section->semester->academic_year ?? '' }} / {{ $section->course->department->college->name ?? __('No college') }} / {{ $section->course->department->name ?? __('No department') }}</p>
                        <p class="mt-1 text-sm text-gray-600">{{ __('Teacher') }}: {{ $section->teacher->full_name ?? __('Not assigned') }}</p>
                    </div>
                    <div class="shrink-0 text-right text-sm text-gray-600">
                        <p>{{ __('Generated') }}: {{ now()->format('Y-m-d H:i') }}</p>
                        <p>{{ __('Students') }}: <span class="font-semibold text-gray-900">{{ $stats['total'] }}</span></p>
                        <p>{{ __('Published') }}: <span class="font-semibold text-gray-900">{{ $stats['published'] }}</span></p>
                    </div>
                </div>

                <div class="mt-5 grid grid-cols-4 gap-4 border-b border-gray-200 pb-6">
                    <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Total Students') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['total'] }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Passed') }}</p><p class="mt-1 text-xl font-semibold text-emerald-700">{{ $stats['passed'] }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Failed') }}</p><p class="mt-1 text-xl font-semibold text-red-700">{{ $stats['failed'] }}</p></div>
                    <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Avg Published Mark') }}</p><p class="mt-1 text-xl font-semibold text-gray-900">{{ $stats['average'] }}</p></div>
                </div>

                <div class="mt-6 overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Student ID') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Student') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Prefinal') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Final Exam') }}</th>
                                <th class="px-4 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Final Mark') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Result') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Status') }}</th>
                                <th class="px-4 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Visibility') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($rows as $row)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ $row['student_id'] }}</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ $row['student_name'] }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600">{{ $row['prefinal_mark'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-sm text-gray-600">{{ $row['final_exam'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-right text-sm font-semibold text-gray-900">{{ $row['final_mark'] ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm">
                                        <span class="{{ $row['result'] === 'Passed' ? 'rounded-md bg-emerald-50 px-2 py-1 text-xs font-semibold text-emerald-800' : ($row['result'] === 'Failed' ? 'rounded-md bg-red-50 px-2 py-1 text-xs font-semibold text-red-800' : 'rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-600') }}">{{ __($row['result']) }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ __($row['submission_status']) }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-600">{{ __($row['visibility_status']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-sm text-gray-500">{{ __('No marks recorded for this class yet.') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </main>
    </body>
</html>
