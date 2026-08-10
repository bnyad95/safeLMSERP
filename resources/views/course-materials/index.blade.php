<x-app-layout>
<div class="max-w-7xl mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <div>
            <h1 class="text-2xl font-bold text-gray-800">Course Materials</h1>
            <p class="text-gray-500 mt-1">{{ $course->name }} ({{ $course->code }}){{ $section ? ' - Group '.$section->section_code : '' }}</p>
        </div>
        @if(auth()->user()->hasAnyRole(['teacher', 'super_administrator']))
            <a href="{{ route('materials.create', ['course' => $course->id, 'section_id' => $section?->id]) }}"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                + Upload Material
            </a>
        @endif
    </div>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        @forelse($materials as $material)
            <div class="border-b last:border-0 p-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <div class="p-2 rounded-lg bg-gray-100 text-xs font-semibold text-gray-600 min-w-10 text-center">
                        @php
                            $icons = ['pdf' => 'PDF', 'video' => 'VID', 'image' => 'IMG', 'doc' => 'DOC', 'presentation' => 'PPT'];
                            echo $icons[$material->file_type] ?? 'FILE';
                        @endphp
                    </div>
                    <div>
                        <p class="font-medium text-gray-800">{{ $material->title }}</p>
                        @if($material->description)
                            <p class="text-sm text-gray-500">{{ $material->description }}</p>
                        @endif
                        <div class="flex items-center space-x-2 mt-1">
                            <span class="text-xs text-gray-400">{{ strtoupper($material->file_type) }}</span>
                            @if(auth()->user()->hasAnyRole(['teacher', 'super_administrator']))
                                <span class="text-xs px-2 py-0.5 rounded-full {{ $material->visibility === 'published' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-200' : 'bg-yellow-100 text-yellow-700' }}">
                                    {{ ucfirst($material->visibility) }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="flex items-center space-x-2">
                    @if($material->file_path)
                        <a href="{{ route('materials.download', ['course' => $course->id, 'material' => $material->id, 'section_id' => $section?->id]) }}"
                           class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1 rounded text-sm">
                            Download
                        </a>
                    @endif
                    @if(auth()->user()->hasAnyRole(['teacher', 'super_administrator']) && $material->uploaded_by === auth()->id())
                        @if($material->visibility === 'draft')
                            <form method="POST" action="{{ route('materials.publish', ['course' => $course->id, 'material' => $material->id, 'section_id' => $section?->id]) }}">
                                @csrf
                                <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm">Publish</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('materials.unpublish', ['course' => $course->id, 'material' => $material->id, 'section_id' => $section?->id]) }}">
                                @csrf
                                <button type="submit" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Unpublish</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('materials.destroy', ['course' => $course->id, 'material' => $material->id, 'section_id' => $section?->id]) }}">
                            @csrf @method('DELETE')
                            <button type="submit" onclick="return confirm('Delete this material?')"
                                    class="bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="p-8 text-center text-gray-500">
                No materials available for this course yet.
            </div>
        @endforelse
    </div>
</div>
</x-app-layout>
