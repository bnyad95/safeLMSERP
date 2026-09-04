<x-app-layout>
    <x-slot name="header">
        <div>
            <p class="text-sm font-medium text-gray-500">{{ __('Front Desk') }}</p>
            <h2 class="mt-1 text-2xl font-semibold text-gray-900">{{ __('Reception Workspace') }}</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600">{{ __('Find students quickly and confirm contact, department, status, and guardian information.') }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 md:grid-cols-3">
                @foreach($stats as $stat)
                    <div class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                        <p class="text-sm font-medium text-gray-500">{{ $stat['label'] }}</p>
                        <p class="mt-3 text-3xl font-semibold text-gray-900">{{ $stat['value'] }}</p>
                        <p class="mt-2 text-sm text-gray-500">{{ $stat['detail'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
                <form method="GET" action="{{ route('reception.workspace') }}" class="grid gap-4 lg:grid-cols-[1fr_0.3fr_auto]">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Student lookup') }}</label>
                        <input name="q" value="{{ $filters['q'] }}" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="{{ __('Name, ID, email, phone') }}">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Status') }}</label>
                        <select name="status" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">{{ __('All statuses') }}</option>
                            @foreach(['Active', 'Inactive', 'Graduated'] as $status)
                                <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __($status) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-3">
                        <button class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('Search') }}</button>
                        <a href="{{ route('reception.workspace') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Reset') }}</a>
                    </div>
                </form>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4">
                    <h3 class="text-base font-semibold text-gray-900">{{ __('Student Contacts') }}</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Student') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Department') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Contact') }}</th>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Guardian') }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase text-gray-500">{{ __('Status') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($students as $student)
                                @php($guardian = $student->guardians->first())
                                <tr>
                                    <td class="px-5 py-4">
                                        <p class="text-sm font-semibold text-gray-900">{{ $student->full_name }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $student->student_id }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm text-gray-900">{{ $student->department?->name ?? __('No department') }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $student->department?->college?->name ?? __('No college') }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm text-gray-900">{{ $student->phone ?: __('No phone') }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $student->email ?: __('No email') }}</p>
                                    </td>
                                    <td class="px-5 py-4">
                                        <p class="text-sm text-gray-900">{{ $guardian?->full_name ?? __('No guardian') }}</p>
                                        <p class="mt-1 text-xs text-gray-500">{{ $guardian?->phone ?? $guardian?->email ?? __('No contact') }}</p>
                                    </td>
                                    <td class="px-5 py-4 text-right">
                                        <span class="rounded-md bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">{{ __($student->status) }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="px-5 py-10 text-center text-sm text-gray-500">{{ __('No students match the current lookup.') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if($students->hasPages())
                    <div class="border-t border-gray-100 px-5 py-4">{{ $students->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
