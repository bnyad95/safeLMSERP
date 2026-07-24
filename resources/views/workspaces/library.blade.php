<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500">Library</p>
            <h2 class="mt-1 text-2xl font-semibold text-gray-900">Library Workspace</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600">Monitor course resources and academic document availability from one workspace.</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-2 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('library.workspace') }}" class="grid gap-4 lg:grid-cols-5">
                    <div class="lg:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Search resources</label>
                        <input name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Title, course, code">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">File type</label>
                        <select name="file_type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All types</option>
                            @foreach($fileTypes as $value => $label)
                                <option value="{{ $value }}" @selected($filters['file_type'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Visibility</label>
                        <select name="visibility" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All visibility</option>
                            <option value="published" @selected($filters['visibility'] === 'published')>Published</option>
                            <option value="draft" @selected($filters['visibility'] === 'draft')>Draft</option>
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Apply</button>
                        <a href="{{ route('library.workspace') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Reset</a>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">Resource Catalog</h3>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($materials as $material)
                        <div class="grid gap-4 px-5 py-4 lg:grid-cols-[1fr_0.35fr_0.25fr]">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $material->title }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $material->course?->code ?? 'No course code' }} - {{ $material->course?->name ?? 'No course' }}</p>
                                @if($material->description)
                                    <p class="mt-2 text-sm text-gray-600">{{ Str::limit($material->description, 120) }}</p>
                                @endif
                            </div>
                            <div>
                                <p class="text-xs font-medium uppercase text-gray-500">Module</p>
                                <p class="mt-1 text-sm text-gray-700">{{ $material->courseSection ? 'Group '.$material->courseSection->section_code : 'General course resource' }}</p>
                            </div>
                            <div class="flex flex-wrap items-start gap-2 lg:justify-end">
                                <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ strtoupper($material->file_type ?: 'other') }}</span>
                                <span class="rounded-md {{ $material->visibility === 'published' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }} px-2.5 py-1 text-xs font-semibold">{{ ucfirst($material->visibility) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-10 text-center text-sm text-gray-500">No resources match the current filters.</p>
                    @endforelse
                </div>
                @if($materials->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4">{{ $materials->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
