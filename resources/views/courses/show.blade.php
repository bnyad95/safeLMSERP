<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
            <div><p class="text-sm font-semibold text-blue-700">{{ $course->code }}</p><h2 class="mt-1 text-xl font-semibold text-gray-800">{{ $course->name }}</h2><p class="text-sm text-gray-600">Read-only course catalog profile and semester offerings.</p></div>
            <div class="flex gap-2"><a href="{{ route('course-records.index') }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Back</a>@if($abilities['update'])<a href="{{ route('course-records.edit', $course) }}" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">Edit Course</a>@endif</div>
        </div>
    </x-slot>
    <div class="py-10"><div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm"><p class="text-sm text-gray-500">All sections</p><p class="mt-2 text-2xl font-semibold text-gray-900">{{ $summary['sections'] }}</p></div>
            <div class="rounded-lg border border-blue-200 bg-blue-50 p-4"><p class="text-sm text-blue-800">Open sections</p><p class="mt-2 text-2xl font-semibold text-blue-950">{{ $summary['open_sections'] }}</p></div>
            <div class="rounded-lg border border-emerald-200 bg-emerald-50 p-4"><p class="text-sm text-emerald-800">Active students</p><p class="mt-2 text-2xl font-semibold text-emerald-950">{{ $summary['students'] }}</p></div>
            <div class="rounded-lg border border-violet-200 bg-violet-50 p-4"><p class="text-sm text-violet-800">Assessments</p><p class="mt-2 text-2xl font-semibold text-violet-950">{{ $summary['assessments'] }}</p></div>
        </section>

        <section class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(260px,0.4fr)]">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-200 px-5 py-4"><h3 class="font-semibold text-gray-900">Semester sections</h3></div>
                <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200"><thead class="bg-gray-50"><tr><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Semester / Section</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Teacher</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Students</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Learning</th><th class="px-5 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th></tr></thead><tbody class="divide-y divide-gray-100">
                    @forelse($course->sections as $section)<tr><td class="px-5 py-4 text-sm font-medium text-gray-900">{{ $section->semester->name ?? 'No semester' }} {{ $section->semester->academic_year ?? '' }}<p class="mt-1 text-xs text-gray-500">Group {{ $section->section_code }}{{ $section->grade_level ? ' / '.$section->grade_level : '' }}</p></td><td class="px-5 py-4 text-sm text-gray-600">{{ $section->teacher->full_name ?? 'Unassigned' }}</td><td class="px-5 py-4 text-sm text-gray-600">{{ $section->active_enrollments_count }} / {{ $section->capacity }}</td><td class="px-5 py-4 text-sm text-gray-600">{{ $section->assessment_items_count }} assessments<p class="mt-1 text-xs text-gray-500">{{ $section->materials_count }} files / {{ $section->timetables_count }} timetable entries</p></td><td class="px-5 py-4 text-sm text-gray-600">{{ ucfirst($section->status) }}</td></tr>
                    @empty<tr><td colspan="5" class="px-5 py-12 text-center text-sm text-gray-500">No semester sections have been created.</td></tr>@endforelse
                </tbody></table></div>
            </div>
            <div class="space-y-6">
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-gray-900">Catalog details</h3><dl class="mt-4 space-y-4 text-sm"><div><dt class="text-gray-500">University</dt><dd class="mt-1 font-medium text-gray-900">{{ $course->university->name ?? 'Not assigned' }}</dd></div><div><dt class="text-gray-500">College</dt><dd class="mt-1 font-medium text-gray-900">{{ $course->department->college->name ?? 'No college' }}</dd></div><div><dt class="text-gray-500">Department</dt><dd class="mt-1 font-medium text-gray-900">{{ $course->department->name ?? 'No department' }}</dd></div><div><dt class="text-gray-500">Credits</dt><dd class="mt-1 font-medium text-gray-900">{{ $course->credits }}</dd></div><div><dt class="text-gray-500">Status</dt><dd class="mt-1 font-medium text-gray-900">{{ ucfirst($course->status) }}</dd></div></dl></section>
                <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm"><h3 class="font-semibold text-gray-900">Historical records</h3><dl class="mt-4 space-y-4 text-sm"><div class="flex justify-between gap-4"><dt class="text-gray-500">Marks</dt><dd class="font-medium text-gray-900">{{ $course->marks_count }}</dd></div><div class="flex justify-between gap-4"><dt class="text-gray-500">Attendance records</dt><dd class="font-medium text-gray-900">{{ $course->attendances_count }}</dd></div><div class="flex justify-between gap-4"><dt class="text-gray-500">Course materials</dt><dd class="font-medium text-gray-900">{{ $course->materials_count }}</dd></div></dl></section>
            </div>
        </section>
    </div></div>
</x-app-layout>
