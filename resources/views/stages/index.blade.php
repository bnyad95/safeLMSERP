<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bologna Definition</p>
                <h2 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">Stages</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">Reusable study stages defined under each department.</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('bologna-definition') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800">Bologna Definition</a>
                @if($canManageStages)<a href="{{ route('stages.create') }}" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-600">Add Stage</a>@endif
            </div>
        </div>
    </x-slot>
    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <form method="GET" class="flex flex-col gap-3 rounded-lg border border-gray-200 bg-white p-4 shadow-sm sm:flex-row sm:items-end dark:border-gray-800 dark:bg-gray-900">
                <div class="min-w-0 flex-1"><label class="text-sm font-medium text-gray-700 dark:text-gray-300">Department</label><select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-950"><option value="">All departments</option>@foreach($departments as $department)<option value="{{ $department->id }}" @selected($departmentId === $department->id)>{{ $department->college?->name }} / {{ $department->name }}</option>@endforeach</select></div>
                <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white dark:bg-indigo-600">Apply</button>
                <a href="{{ route('stages.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-center text-sm font-semibold text-gray-700 dark:border-gray-700 dark:text-gray-200">Reset</a>
            </form>
            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 dark:divide-gray-800"><thead class="bg-gray-50 dark:bg-gray-950"><tr><th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Stage</th><th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Department</th><th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Order</th><th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Modules</th>@if($canManageStages)<th class="px-5 py-3 text-left text-xs font-semibold uppercase text-gray-500">Actions</th>@endif</tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">@forelse($stages as $stage)<tr><td class="px-5 py-4 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $stage->name }} <span class="block text-xs font-normal text-gray-500">{{ $stage->code ?: 'No code' }}</span></td><td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage->department?->college?->name }} / {{ $stage->department?->name }}</td><td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage->sequence }}</td><td class="px-5 py-4 text-sm text-gray-600 dark:text-gray-300">{{ $stage->course_sections_count }}</td>@if($canManageStages)<td class="px-5 py-4 text-sm"><a href="{{ route('stages.edit', $stage) }}" class="font-semibold text-blue-600 dark:text-indigo-300">Edit</a><form method="POST" action="{{ route('stages.destroy', $stage) }}" class="ml-3 inline">@csrf @method('DELETE')<button class="font-semibold text-red-600" onclick="return confirm('Delete this unused stage?')">Delete</button></form></td>@endif</tr>@empty<tr><td colspan="{{ $canManageStages ? 5 : 4 }}" class="px-5 py-10 text-center text-sm text-gray-500">No stages defined.</td></tr>@endforelse</tbody></table></div>
            </section>{{ $stages->links() }}
        </div>
    </div>
</x-app-layout>
