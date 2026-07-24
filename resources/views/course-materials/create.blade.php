<x-app-layout>
<div class="max-w-2xl mx-auto px-4 py-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Upload Course Material</h1>
    <p class="text-gray-500 mb-4">{{ $course->name }} ({{ $course->code }}){{ $section ? ' - Group '.$section->section_code : '' }}</p>

    <form method="POST" action="{{ route('materials.store', ['course' => $course->id, 'section_id' => $section?->id]) }}" enctype="multipart/form-data">
        @csrf
        <div class="bg-white rounded-lg shadow p-6 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700">Title *</label>
                <input type="text" name="title" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Description</label>
                <textarea name="description" rows="3" class="mt-1 block w-full rounded border-gray-300 shadow-sm"></textarea>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">File Type *</label>
                <select name="file_type" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                    <option value="pdf">PDF</option>
                    <option value="doc">Document</option>
                    <option value="video">Video</option>
                    <option value="image">Image</option>
                    <option value="presentation">Presentation</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Upload File</label>
                <input type="file" name="file" class="mt-1 block w-full text-sm text-gray-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Visibility *</label>
                <select name="visibility" required class="mt-1 block w-full rounded border-gray-300 shadow-sm">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>
            <div class="flex justify-end space-x-3">
                <a href="{{ route('materials.index', ['course' => $course->id, 'section_id' => $section?->id]) }}" class="px-4 py-2 border rounded text-gray-600 hover:bg-gray-50">Cancel</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Upload</button>
            </div>
        </div>
    </form>
</div>
</x-app-layout>
