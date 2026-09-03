<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div><p class="text-sm font-semibold text-blue-700">{{ $student->student_id }}</p><h2 class="mt-1 text-xl font-semibold text-gray-800">{{ $student->full_name }}</h2><p class="text-sm text-gray-600">{{ __('Read-only student profile and academic placement.') }}</p></div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('students.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">{{ __('Back') }}</a>
                @if($abilities['transcript'])<a href="{{ route('transcripts.show', $student) }}" class="rounded-md border border-violet-200 bg-violet-50 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-100">{{ __('Transcript') }}</a>@endif
                @if($abilities['update'])<a href="{{ route('students.edit', $student) }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ __('Edit Profile') }}</a>@endif
            </div>
        </div>
    </x-slot>

    <div class="py-10"><div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        @if($errors->any())<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><p class="font-semibold">{{ __('Please review the form.') }}</p><ul class="mt-2 list-inside list-disc space-y-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

        <section class="rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="grid gap-6 p-6 md:grid-cols-2 xl:grid-cols-4">
                <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Status') }}</p><p class="mt-2 font-semibold text-gray-900">{{ __($student->status) }}</p></div>
                <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Admission') }}</p><p class="mt-2 font-semibold text-gray-900">{{ $student->admission_status ?: __('Not recorded') }}</p></div>
                <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Current stage') }}</p><p class="mt-2 font-semibold text-gray-900">{{ $student->grade_labels->isNotEmpty() ? $student->grade_labels->join(', ') : __('No stage') }}</p></div>
                <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Academic standing') }}</p><p class="mt-2 font-semibold text-gray-900">{{ __(str($student->academic_standing ?: 'new')->replace('_', ' ')->title()->toString()) }}</p></div>
                <div><p class="text-xs font-medium uppercase text-gray-500">{{ __('Login account') }}</p><p class="mt-2 font-semibold text-gray-900">{{ $student->user ? __('Linked') : __('Not linked') }}</p></div>
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"><h3 class="font-semibold text-gray-900">{{ __('Contact and organization') }}</h3><dl class="mt-5 grid gap-5 sm:grid-cols-2"><div><dt class="text-xs text-gray-500">{{ __('Email') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->email }}</dd></div><div><dt class="text-xs text-gray-500">{{ __('Phone') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->phone ?: __('Not recorded') }}</dd></div><div><dt class="text-xs text-gray-500">{{ __('National ID / Passport') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->national_id ?: __('Not recorded') }}</dd></div><div><dt class="text-xs text-gray-500">{{ __('College') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->department->college->name ?? __('Not assigned') }}</dd></div><div><dt class="text-xs text-gray-500">{{ __('Department') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->department->name ?? __('Not assigned') }}</dd></div><div class="sm:col-span-2"><dt class="text-xs text-gray-500">{{ __('Address') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->address ?: __('Not recorded') }}</dd></div></dl></section>
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"><h3 class="font-semibold text-gray-900">{{ __('Admissions and emergency contact') }}</h3><dl class="mt-5 grid gap-5 sm:grid-cols-2"><div><dt class="text-xs text-gray-500">{{ __('Admission date') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->admission_date?->format('M j, Y') ?: __('Not recorded') }}</dd></div><div><dt class="text-xs text-gray-500">{{ __('Admission type') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->admission_type ?: __('Not recorded') }}</dd></div><div><dt class="text-xs text-gray-500">{{ __('Previous school') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->previous_school ?: __('Not recorded') }}</dd></div><div><dt class="text-xs text-gray-500">{{ __('Emergency contact') }}</dt><dd class="mt-1 text-sm font-medium text-gray-900">{{ $student->emergency_contact_name ?: __('Not recorded') }}</dd><dd class="mt-1 text-xs text-gray-500">{{ collect([$student->emergency_contact_relationship, $student->emergency_contact_phone])->filter()->join(' - ') }}</dd></div></dl></section>
        </div>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm"><div class="border-b border-gray-200 px-6 py-4"><h3 class="font-semibold text-gray-900">{{ __('Course registrations') }}</h3><p class="mt-1 text-sm text-gray-500">{{ __('Current and historical section enrollment') }}</p></div><div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Course') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Group') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Semester') }}</th><th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">{{ __('Status') }}</th></tr></thead><tbody class="divide-y divide-gray-100">@forelse($student->enrollments as $enrollment)<tr><td class="px-6 py-4 text-sm"><p class="font-medium text-gray-900">{{ $enrollment->courseSection->course->name ?? __('Unavailable course') }}</p><p class="mt-1 text-xs text-gray-500">{{ $enrollment->courseSection->course->code ?? '' }}</p></td><td class="px-6 py-4 text-sm text-gray-600">{{ $enrollment->courseSection->section_code ?? __('N/A') }}</td><td class="px-6 py-4 text-sm text-gray-600">{{ $enrollment->courseSection->semester->name ?? __('N/A') }}</td><td class="px-6 py-4 text-sm text-gray-600">{{ __(ucfirst($enrollment->status)) }}</td></tr>@empty<tr><td colspan="4" class="px-6 py-10 text-center text-sm text-gray-500">{{ __('No course registrations.') }}</td></tr>@endforelse</tbody></table></div></section>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><h3 class="font-semibold text-gray-900">{{ __('Guardians') }}</h3><span class="text-sm text-gray-500">{{ $student->guardians->count() }}</span></div><div class="mt-4 divide-y divide-gray-100">@forelse($student->guardians as $guardian)<div class="py-4 first:pt-0 last:pb-0"><div class="flex items-center gap-2"><p class="text-sm font-semibold text-gray-900">{{ $guardian->full_name }}</p>@if($guardian->is_primary)<span class="rounded-md bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700">{{ __('Primary') }}</span>@endif</div><p class="mt-1 text-sm text-gray-600">{{ $guardian->relationship }}{{ $guardian->phone ? ' - '.$guardian->phone : '' }}</p><p class="mt-1 text-xs text-gray-500">{{ $guardian->email }}</p></div>@empty<p class="py-6 text-center text-sm text-gray-500">{{ __('No guardians recorded.') }}</p>@endforelse</div></section>
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm"><div class="flex items-center justify-between"><h3 class="font-semibold text-gray-900">{{ __('Documents') }}</h3><span class="text-sm text-gray-500">{{ $student->documents->count() }}</span></div><div class="mt-4 divide-y divide-gray-100">@forelse($student->documents as $document)<div class="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0"><div><p class="text-sm font-semibold text-gray-900">{{ $document->title }}</p><p class="mt-1 text-xs text-gray-500">{{ __($document->type) }} - {{ __($document->status) }}</p></div><a href="{{ route('student-documents.download', $document) }}" class="shrink-0 text-sm font-semibold text-blue-700 hover:underline">{{ __('Download') }}</a></div>@empty<p class="py-6 text-center text-sm text-gray-500">{{ __('No documents uploaded.') }}</p>@endforelse</div></section>
        </div>

        @if($abilities['reset_password'])
            <section class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                    <div>
                        <h3 class="font-semibold text-gray-900">{{ __('Reset student password') }}</h3>
                        <p class="mt-1 text-sm text-gray-500">{{ __("Set a new password for this student's linked login account.") }}</p>
                    </div>
                </div>
                <form action="{{ route('students.reset-password', $student) }}" method="POST" class="mt-5 grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                    @csrf
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('New password') }}</label>
                        <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Confirm new password') }}</label>
                        <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">{{ __('Reset Password') }}</button>
                </form>
            </section>
        @endif

        @if($abilities['archive'])<section class="flex flex-col gap-4 rounded-lg border border-red-200 bg-red-50 p-5 sm:flex-row sm:items-center sm:justify-between"><div><h3 class="font-semibold text-red-900">{{ __('Archive student') }}</h3><p class="mt-1 text-sm text-red-700">{{ __('Removes the profile from active directories and suspends a student-only login. Records can be restored.') }}</p></div><form action="{{ route('students.destroy', $student) }}" method="POST">@csrf @method('DELETE')<button type="submit" class="rounded-md bg-red-700 px-4 py-2 text-sm font-semibold text-white hover:bg-red-800" onclick="return confirm('Archive this student?')">{{ __('Archive Student') }}</button></form></section>@endif
    </div></div>
</x-app-layout>
