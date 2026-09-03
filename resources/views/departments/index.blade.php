<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">{{ __('Departments') }}</h2>
                <p class="text-sm text-gray-600 dark:text-gray-400">{{ __('Define and manage departments mapped to universities and colleges.') }}</p>
            </div>
            <div class="flex gap-2">
                <a href="{{ route('bologna-definition') }}" class="inline-flex items-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">{{ __('Bologna Hub') }}</a>
                @if($canManageDepartments)
                    <a href="{{ route('departments.create') }}" class="inline-flex items-center rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Add Department') }}</a>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm dark:border-gray-800">
                <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    <thead class="bg-gray-50 dark:bg-gray-950/60">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Department') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Code') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('University') }}</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('College') }}</th>
                            @if($canManageDepartments)<th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Actions') }}</th>@endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-800">
                        @forelse($departments as $department)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $department->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $department->code ?? __('N/A') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $department->university->name ?? __('N/A') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $department->college->name ?? __('N/A') }}</td>
                                @if($canManageDepartments)
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="{{ route('departments.edit', $department) }}" class="text-blue-600 hover:text-blue-900">{{ __('Edit') }}</a>
                                        <form action="{{ route('departments.destroy', $department) }}" method="POST" class="ml-3 inline-block">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900" onclick="return confirm('{{ __('Delete this department?') }}')">{{ __('Delete') }}</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $canManageDepartments ? 5 : 4 }}" class="px-6 py-8 text-center text-sm text-gray-500">{{ __('No departments defined yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                </div>
            </div>

            <div class="mt-6">{{ $departments->links() }}</div>
        </div>
    </div>
</x-app-layout>
