<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">{{ $assessmentPageTitle }}</h2>
            <p class="text-sm text-gray-600">{{ $isAdminOversight ? __('Institution-wide assessment status, submission progress, and grading risk.') : __('Assignments, exams, submissions, rubrics, and grade weights.') }}</p>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if ($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    {{ $errors->first() }}
                </div>
            @endif

            @if($isAdminOversight)
                @include('assessments.partials.admin-oversight')
            @else
            @if($canManageAssessments)
                <div x-data="{ showCreateAssessment: {{ $errors->any() ? 'true' : 'false' }} }" class="space-y-4">
                    <div class="flex justify-end">
                        <button
                            type="button"
                            @click="showCreateAssessment = ! showCreateAssessment"
                            class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                        >
                            {{ __('Add Assessment') }}
                        </button>
                    </div>

                    <form
                        x-show="showCreateAssessment"
                        x-transition
                        method="POST"
                        action="{{ route('assessment-items.store') }}"
                        enctype="multipart/form-data"
                        class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"
                    >
                        @csrf
                        <div class="mb-5">
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Create Assessment') }}</h3>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Course section') }}</label>
                                <select name="course_section_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <option value="">{{ __('Select section') }}</option>
                                    @foreach($sections as $section)
                                        <option value="{{ $section->id }}" @selected(old('course_section_id') == $section->id)>
                                            {{ $section->course->code }} {{ $section->section_code }} - {{ $section->semester->name }} {{ $section->semester->academic_year }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Title') }}</label>
                                <input type="text" name="title" value="{{ old('title') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Type') }}</label>
                                <select name="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    @foreach(['assignment' => 'Assignment', 'exam' => 'Exam', 'quiz' => 'Quiz', 'project' => 'Project', 'lab' => 'Lab'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('type', 'assignment') === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    @foreach(['draft' => 'Draft', 'published' => 'Published', 'closed' => 'Closed'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ __($label) }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Max score') }}</label>
                                <input type="number" min="1" max="1000" step="0.01" name="max_score" value="{{ old('max_score', 100) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Weight percent') }}</label>
                                <input type="number" min="0" max="100" step="0.01" name="weight_percent" value="{{ old('weight_percent', 10) }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Opens at') }}</label>
                                <input type="datetime-local" name="opens_at" value="{{ old('opens_at') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700">{{ __('Due at') }}</label>
                                <input type="datetime-local" name="due_at" value="{{ old('due_at') }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                            <textarea name="description" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description') }}</textarea>
                        </div>

                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">{{ __('Instruction file') }}</label>
                            <input type="file" name="instruction_file" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <label class="mt-4 flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" name="allow_submissions" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" checked>
                            {{ __('Accept submissions') }}
                        </label>

                        <div class="mt-5 flex justify-end gap-3">
                            <button type="button" @click="showCreateAssessment = false" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</button>
                            <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Create Assessment') }}</button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="grid gap-6 xl:grid-cols-[1.35fr_0.65fr]">
                <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                    <div class="border-b border-gray-200 px-6 py-4">
                        <h3 class="text-base font-semibold text-gray-900">{{ __('Assessment Items') }}</h3>
                    </div>

                    @if($student)
                        <div class="divide-y divide-gray-200">
                            @forelse($assessmentItems as $item)
                                @include('assessments.partials.item', ['item' => $item])
                            @empty
                                <div class="px-6 py-10 text-center text-sm text-gray-500">{{ __('No assessment items yet.') }}</div>
                            @endforelse
                        </div>
                    @else
                        <div class="space-y-4 bg-gray-50 p-4">
                            @forelse($assessmentGroups as $group)
                                <details class="group rounded-lg border border-gray-200 bg-white shadow-sm" @if($selectedAssessmentId && $group['items']->contains('id', $selectedAssessmentId)) open @endif>
                                    <summary class="flex cursor-pointer list-none flex-col gap-3 p-5 md:flex-row md:items-center md:justify-between">
                                        <div>
                                            <h4 class="text-base font-semibold text-gray-900">{{ $group['course_code'] }} - {{ $group['course_name'] }}</h4>
                                            <p class="mt-1 text-sm text-gray-500">{{ __('Click to open assigned assessments.') }}</p>
                                        </div>
                                        <div class="flex items-center gap-3">
                                            <span class="rounded-md bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-700">{{ __(':count assessments', ['count' => $group['items']->count()]) }}</span>
                                            <span class="text-sm font-semibold text-blue-600 group-open:hidden">{{ __('Open') }}</span>
                                            <span class="hidden text-sm font-semibold text-blue-600 group-open:inline">{{ __('Close') }}</span>
                                        </div>
                                    </summary>

                                    <div class="border-t border-gray-200 bg-white">
                                        @foreach($group['items'] as $item)
                                            <details id="assessment-{{ $item->id }}" class="group/assessment scroll-mt-24 border-b border-gray-100 last:border-b-0" @if($selectedAssessmentId === $item->id) open @endif>
                                                <summary class="flex cursor-pointer list-none flex-col gap-3 px-5 py-4 hover:bg-gray-50 md:flex-row md:items-center md:justify-between">
                                                    <div>
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <h5 class="text-sm font-semibold text-gray-900">{{ $item->title }}</h5>
                                                            <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold uppercase text-gray-700">{{ __(ucfirst($item->type)) }}</span>
                                                            <span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ $item->weight_percent }}%</span>
                                                        </div>
                                                        <p class="mt-1 text-sm text-gray-500">
                                                            {{ __('Section :code', ['code' => $item->courseSection->section_code]) }} - {{ __(ucfirst($item->status)) }} - {{ __(':count submissions', ['count' => $item->submissions_count]) }}
                                                        </p>
                                                    </div>
                                                    <div class="text-sm font-semibold text-blue-600">
                                                        <span class="group-open/assessment:hidden">{{ __('Open submissions') }}</span>
                                                        <span class="hidden group-open/assessment:inline">{{ __('Hide submissions') }}</span>
                                                    </div>
                                                </summary>

                                                <div class="border-t border-gray-100 bg-white">
                                                    @include('assessments.partials.item', ['item' => $item, 'compactHeader' => true])
                                                </div>
                                            </details>
                                        @endforeach
                                    </div>
                                </details>
                            @empty
                                <div class="px-6 py-10 text-center text-sm text-gray-500">{{ __('No assessment items yet.') }}</div>
                            @endforelse
                        </div>
                    @endif

                    @if($assessmentItems->hasPages())
                        <div class="border-t border-gray-200 px-6 py-4">{{ $assessmentItems->links() }}</div>
                    @endif
                </section>

                <div class="space-y-6">
                    <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
                        <div class="border-b border-gray-200 px-5 py-4">
                            <h3 class="text-base font-semibold text-gray-900">{{ __('Weighted Gradebook') }}</h3>
                        </div>
                        <div class="divide-y divide-gray-100">
                            @forelse($gradebook as $row)
                                <div class="px-5 py-4">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-medium text-gray-900">{{ $row['title'] }}</p>
                                        <span class="text-xs font-semibold text-gray-500">{{ $row['weight'] }}%</span>
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500">{{ $row['section'] }} - {{ __(':count graded', ['count' => $row['graded']]) }}</p>
                                    <p class="mt-2 text-sm text-gray-700">{{ is_null($row['weighted_average']) ? __('No graded average') : __(':points weighted points', ['points' => number_format($row['weighted_average'], 2)]) }}</p>
                                </div>
                            @empty
                                <div class="px-5 py-8 text-center text-sm text-gray-500">{{ __('No weighted items yet.') }}</div>
                            @endforelse
                        </div>
                    </section>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
