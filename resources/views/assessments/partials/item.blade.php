@php
    $studentSubmission = $student ? $item->submissions->firstWhere('student_id', $student->id) : null;
    $compactHeader = $compactHeader ?? false;
    $readOnlyOversight = $readOnlyOversight ?? false;
    $submissionOpen = $item->allow_submissions
        && (! $item->opens_at || $item->opens_at->isPast())
        && (! $item->due_at || $item->due_at->isFuture());
@endphp

<div class="{{ $compactHeader ? 'p-5' : 'p-6' }}">
    @unless($compactHeader)
        <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h4 class="text-base font-semibold text-gray-900">{{ $item->title }}</h4>
                    <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-700">{{ __(ucfirst($item->type)) }}</span>
                    <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $item->weight_percent }}%</span>
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $item->courseSection->course->code ?? __('Course') }} {{ $item->courseSection->section_code }} - {{ $item->courseSection->semester->name ?? __('Semester') }}
                </p>
                @if($item->description)
                    <p class="mt-2 text-sm text-gray-600">{{ $item->description }}</p>
                @endif
                @if($item->instruction_file_path)
                    <a href="{{ route('assessment-items.file', $item) }}" class="mt-2 inline-flex text-sm font-medium text-blue-600 hover:underline">{{ __('Download instruction file') }}</a>
                @endif
                @if($canManageAssessments)
                    <div class="mt-3">
                        <a href="{{ route('assessment-items.edit', $item) }}" class="inline-flex rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Edit Assessment') }}</a>
                    </div>
                @endif
            </div>
            <div class="text-sm text-gray-500 md:text-right">
                <p>{{ __(ucfirst($item->status)) }}</p>
                <p>{{ __('Max :score', ['score' => $item->max_score]) }}</p>
                <p>{{ $item->due_at ? __('Due :date', ['date' => $item->due_at->format('Y-m-d H:i')]) : __('No due date') }}</p>
            </div>
        </div>
    @else
        <div>
            @if($item->description)
                <p class="text-sm text-gray-600">{{ $item->description }}</p>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-2">
                @if($item->instruction_file_path)
                    <a href="{{ route('assessment-items.file', $item) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Download instruction file') }}</a>
                @endif
                @if($canManageAssessments)
                    <a href="{{ route('assessment-items.edit', $item) }}" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Edit Assessment') }}</a>
                @endif
            </div>
        </div>
    @endunless

    @if($student)
        <div class="mt-5 rounded-md border border-gray-200 bg-gray-50 p-4">
            @if($studentSubmission)
                <p class="text-sm font-medium text-gray-900">{{ __('Submission') }}: {{ __(ucfirst($studentSubmission->status)) }}</p>
                <p class="mt-1 text-sm text-gray-600">{{ __('Score') }}: {{ $studentSubmission->score ?? __('Not graded') }}</p>
                @if($studentSubmission->feedback)
                    <p class="mt-1 text-sm text-gray-600">{{ $studentSubmission->feedback }}</p>
                @endif
            @elseif($submissionOpen)
                <form method="POST" action="{{ route('assessment-items.submissions.store', $item) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <textarea name="content" rows="4" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    <input type="file" name="submission_file" class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('Submit Work') }}</button>
                </form>
            @else
                <p class="text-sm font-medium text-gray-700">{{ __('Submissions are currently closed.') }}</p>
            @endif
            @if($studentSubmission?->attachment_path)
                <a href="{{ route('assessment-submissions.file', $studentSubmission) }}" class="mt-2 inline-flex text-sm font-medium text-blue-600 hover:underline">{{ __('Download submitted file') }}</a>
            @endif
        </div>
    @endif

    @if($readOnlyOversight)
        <div class="mt-5 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-md bg-gray-50 p-3"><p class="text-xs text-gray-500">{{ __('Submitted') }}</p><p class="mt-1 text-lg font-semibold text-gray-900">{{ $item->submissions_count }}</p></div>
            <div class="rounded-md bg-green-50 p-3"><p class="text-xs text-green-700">{{ __('Graded') }}</p><p class="mt-1 text-lg font-semibold text-green-900">{{ $item->graded_submissions_count }}</p></div>
            <div class="rounded-md bg-amber-50 p-3"><p class="text-xs text-amber-700">{{ __('Pending grading') }}</p><p class="mt-1 text-lg font-semibold text-amber-900">{{ max($item->submissions_count - $item->graded_submissions_count, 0) }}</p></div>
            <div class="rounded-md bg-blue-50 p-3"><p class="text-xs text-blue-700">{{ __('Average score') }}</p><p class="mt-1 text-lg font-semibold text-blue-900">{{ is_null($item->average_score) ? __('N/A') : number_format((float) $item->average_score, 1).' / '.number_format((float) $item->max_score, 1) }}</p></div>
        </div>
        <div class="mt-4 rounded-md border border-gray-200 bg-gray-50 p-4">
            <h5 class="text-sm font-semibold text-gray-900">{{ __('Rubric') }}</h5>
            @if($item->rubric)
                <p class="mt-1 text-sm text-gray-600">{{ $item->rubric->title }} - {{ __(':points points', ['points' => number_format((float) $item->rubric->total_points, 1)]) }}</p>
                <div class="mt-3 space-y-2">
                    @foreach($item->rubric->criteria as $criterion)
                        <div class="flex items-center justify-between gap-4 text-sm"><span class="text-gray-700">{{ $criterion['label'] }}</span><span class="font-semibold text-gray-900">{{ $criterion['points'] ?? '-' }}</span></div>
                    @endforeach
                </div>
            @else
                <p class="mt-1 text-sm text-gray-500">{{ __('No rubric attached.') }}</p>
            @endif
        </div>
    @endif

    @if(! $readOnlyOversight && ($canManageAssessments || $canGrade))
        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('assessment-items.rubric.store', $item) }}" class="rounded-md border border-gray-200 p-4">
                @csrf
                <div class="grid gap-3">
                    <input type="text" name="title" value="{{ $item->rubric->title ?? __('Rubric for :title', ['title' => $item->title]) }}" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    <textarea name="criteria_text" rows="3" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>@if($item->rubric){{ collect($item->rubric->criteria)->map(fn ($criterion) => $criterion['label'].'|'.($criterion['points'] ?? ''))->implode("\n") }}@endif</textarea>
                    <button type="submit" class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Save Rubric') }}</button>
                </div>
            </form>

            <div class="rounded-md border border-gray-200 p-4">
                <h5 class="text-sm font-semibold text-gray-900">{{ __('Submissions') }}</h5>
                <div class="mt-3 space-y-3">
                    @forelse($item->submissions as $submission)
                        <form method="POST" action="{{ route('assessment-submissions.grade', $submission) }}" class="grid gap-2 rounded-md bg-gray-50 p-3">
                            @csrf
                            @method('PATCH')
                            <div class="flex items-center justify-between gap-3 text-sm">
                                <span class="font-medium text-gray-900">{{ $submission->student->full_name }}</span>
                                <span class="text-gray-500">{{ __(ucfirst($submission->status)) }}</span>
                            </div>
                            @if($submission->content)
                                <div class="rounded-md border border-gray-200 bg-white p-3 text-sm text-gray-700">
                                    {{ $submission->content }}
                                </div>
                            @endif
                            @if($submission->attachment_path)
                                <a href="{{ route('assessment-submissions.file', $submission) }}" class="text-sm font-medium text-blue-600 hover:underline">{{ __('Download submitted file') }}</a>
                            @endif
                            <input type="number" min="0" max="{{ $item->max_score }}" step="0.01" name="score" value="{{ $submission->score }}" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <input type="text" name="feedback" value="{{ $submission->feedback }}" class="rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <button type="submit" class="rounded-md bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-700">{{ __('Grade') }}</button>
                        </form>
                    @empty
                        <p class="text-sm text-gray-500">{{ __('No submissions yet.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>
