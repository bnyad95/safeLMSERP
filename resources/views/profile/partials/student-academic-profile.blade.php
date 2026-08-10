<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900">
            {{ __('Academic Profile') }}
        </h2>
        <p class="mt-1 text-sm text-gray-600">
            {{ __('Your student record, admissions information, guardians, and documents.') }}
        </p>
    </header>

    @if (!$student)
        <div class="mt-6 rounded-lg border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
            No student profile is linked to your account yet. Ask administration to connect your login to a student record.
        </div>
    @else
        <div class="mt-6 grid gap-4 text-sm text-gray-700 md:grid-cols-2 lg:grid-cols-4">
            <div>
                <p class="text-xs uppercase text-gray-500">Full Name</p>
                <p class="font-medium text-gray-900">{{ $student->full_name }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Student ID</p>
                <p class="font-medium text-gray-900">{{ $student->student_id }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">University</p>
                <p class="font-medium text-gray-900">{{ $student->university->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Department</p>
                <p class="font-medium text-gray-900">{{ $student->department->name ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Admission</p>
                <p class="font-medium text-gray-900">{{ $student->admission_status ?? 'Admitted' }}</p>
                <p class="text-xs text-gray-500">{{ $student->admission_date ? $student->admission_date->format('M d, Y') : 'Date not set' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Admission Type</p>
                <p class="font-medium text-gray-900">{{ $student->admission_type ?? 'N/A' }}</p>
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Emergency Contact</p>
                <p class="font-medium text-gray-900">{{ $student->emergency_contact_name ?? 'N/A' }}</p>
                @if($student->emergency_contact_phone)
                    <p class="text-xs text-gray-500">{{ $student->emergency_contact_relationship }} / {{ $student->emergency_contact_phone }}</p>
                @endif
            </div>
            <div>
                <p class="text-xs uppercase text-gray-500">Address</p>
                <p class="font-medium text-gray-900">{{ $student->address ?? 'N/A' }}</p>
            </div>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Guardians</h3>
                <div class="mt-3 space-y-2">
                    @forelse($student->guardians as $guardian)
                        <div class="rounded-md border border-gray-100 bg-gray-50 p-3 text-sm">
                            <div class="flex items-center justify-between gap-3">
                                <p class="font-medium text-gray-900">{{ $guardian->full_name }}</p>
                                @if($guardian->is_primary)
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/30 dark:text-blue-200">Primary</span>
                                @endif
                            </div>
                            <p class="text-gray-500">{{ $guardian->relationship }}{{ $guardian->phone ? ' / '.$guardian->phone : '' }}</p>
                            @if($guardian->email)
                                <p class="text-gray-500">{{ $guardian->email }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No guardian details have been added yet.</p>
                    @endforelse
                </div>
            </div>

            <div>
                <h3 class="text-sm font-semibold text-gray-900">Documents</h3>
                <div class="mt-3 space-y-2">
                    @forelse($student->documents as $document)
                        <div class="flex flex-col gap-2 rounded-md border border-gray-100 bg-gray-50 p-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-medium text-gray-900">{{ $document->title }}</p>
                                <p class="text-gray-500">{{ $document->type }} / {{ $document->status }}</p>
                            </div>
                            <a href="{{ route('student-documents.download', $document) }}" class="text-sm font-medium text-blue-600 hover:underline">Download</a>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500">No documents are available yet.</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</section>
