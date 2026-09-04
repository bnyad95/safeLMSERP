<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">{{ __('Edit Assessment') }}</h2>
                <p class="text-sm text-gray-600">{{ $assessmentItem->title }}</p>
            </div>
            <a href="{{ route('assessments.index') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Back') }}</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('assessment-items.update', $assessmentItem) }}" enctype="multipart/form-data" class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                @csrf
                @method('PATCH')

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Course section') }}</label>
                        <select name="course_section_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            @foreach($sections as $section)
                                <option value="{{ $section->id }}" @selected(old('course_section_id', $assessmentItem->course_section_id) == $section->id)>
                                    {{ $section->course->code }} {{ $section->section_code }} - {{ $section->semester->name }} {{ $section->semester->academic_year }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Title') }}</label>
                        <input type="text" name="title" value="{{ old('title', $assessmentItem->title) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Type') }}</label>
                        <select name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            @foreach(['assignment' => 'Assignment', 'exam' => 'Exam', 'quiz' => 'Quiz', 'project' => 'Project', 'lab' => 'Lab'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $assessmentItem->type) === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            @foreach(['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $assessmentItem->status) === $value)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Max score') }}</label>
                        <input type="number" min="1" max="1000" step="0.01" name="max_score" value="{{ old('max_score', $assessmentItem->max_score) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Weight percent') }}</label>
                        <input type="number" min="0" max="100" step="0.01" name="weight_percent" value="{{ old('weight_percent', $assessmentItem->weight_percent) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Opens at') }}</label>
                        <input type="datetime-local" name="opens_at" value="{{ old('opens_at', $assessmentItem->opens_at?->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Due at') }}</label>
                        <input type="datetime-local" name="due_at" value="{{ old('due_at', $assessmentItem->due_at?->format('Y-m-d\TH:i')) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                    <textarea name="description" rows="4" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $assessmentItem->description) }}</textarea>
                </div>

                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700">{{ __('Replace instruction file') }}</label>
                    <input type="file" name="instruction_file" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @if($assessmentItem->instruction_file_path)
                        <a href="{{ route('assessment-items.file', $assessmentItem) }}" class="mt-2 inline-flex text-sm font-medium text-blue-600 hover:underline">{{ __('Download current file') }}</a>
                    @endif
                </div>

                <label class="mt-4 flex items-center gap-2 text-sm text-gray-700">
                    <input type="checkbox" name="allow_submissions" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" @checked(old('allow_submissions', $assessmentItem->allow_submissions))>
                    {{ __('Accept submissions') }}
                </label>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <a href="{{ route('assessments.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</a>
                    <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Save Changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
