<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">{{ __('Upload Course Material') }}</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $course->name }} ({{ $course->code }}){{ $section ? ' - '.__('Group :code', ['code' => $section->section_code]) : '' }}</p>
        </div>
    </x-slot>

<div class="max-w-2xl mx-auto px-4 py-8">
    <form method="POST" action="{{ route('materials.store', ['course' => $course->id, 'section_id' => $section?->id]) }}" enctype="multipart/form-data">
        @csrf
        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Title') }} *</label>
                <input type="text" name="title" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Description') }}</label>
                <textarea name="description" rows="3" class="mt-1 block w-full rounded border-gray-300 shadow-sm"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('File Type') }} *</label>
                <select name="file_type" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                    <option value="pdf">{{ __('PDF') }}</option>
                    <option value="doc">{{ __('Document') }}</option>
                    <option value="video">{{ __('Video') }}</option>
                    <option value="image">{{ __('Image') }}</option>
                    <option value="presentation">{{ __('Presentation') }}</option>
                    <option value="other">{{ __('Other') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Upload File') }}</label>
                <input type="file" name="file" class="mt-1 block w-full text-sm text-gray-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">{{ __('Visibility') }} *</label>
                <select name="visibility" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                    <option value="draft">{{ __('Draft') }}</option>
                    <option value="published">{{ __('Published') }}</option>
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <a href="{{ route('materials.index', ['course' => $course->id, 'section_id' => $section?->id]) }}" class="px-4 py-2 border rounded text-gray-600 hover:bg-gray-50">{{ __('Cancel') }}</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">{{ __('Upload') }}</button>
            </div>
        </div>
    </form>
</div>
</x-app-layout>
