<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Edit Student</h2>
                <p class="text-sm text-gray-600">Update the student profile.</p>
            </div>
            <a href="{{ route('students.show', $student) }}" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">View Profile</a>
        </div>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form action="{{ route('students.update', $student) }}" method="POST">
                @csrf
                @method('PUT')
                @include('students.form')
            </form>

            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Guardians</h3>
                        <span class="text-sm text-gray-500">{{ $student->guardians->count() }} saved</span>
                    </div>

                    <form action="{{ route('students.guardians.store', $student) }}" method="POST" class="mt-4 grid gap-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Full name</label>
                                <input type="text" name="full_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Relationship</label>
                                <input type="text" name="relationship" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="text" name="phone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" name="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Occupation</label>
                                <input type="text" name="occupation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                            <label class="mt-7 flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="is_primary" value="1" class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                Primary guardian
                            </label>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea name="address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Add Guardian</button>
                        </div>
                    </form>

                    <div class="mt-6 space-y-4">
                        @forelse($student->guardians as $guardian)
                            <div class="rounded-md border border-gray-200 bg-gray-50 p-4">
                                <form action="{{ route('student-guardians.update', $guardian) }}" method="POST" class="grid gap-3">
                                    @csrf
                                    @method('PATCH')
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <input type="text" name="full_name" value="{{ $guardian->full_name }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                        <input type="text" name="relationship" value="{{ $guardian->relationship }}" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                        <input type="text" name="phone" value="{{ $guardian->phone }}" placeholder="Phone" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <input type="email" name="email" value="{{ $guardian->email }}" placeholder="Email" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <input type="text" name="occupation" value="{{ $guardian->occupation }}" placeholder="Occupation" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <label class="flex items-center gap-2 text-sm text-gray-700">
                                            <input type="checkbox" name="is_primary" value="1" @checked($guardian->is_primary) class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                            Primary
                                        </label>
                                    </div>
                                    <textarea name="address" rows="2" placeholder="Address" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $guardian->address }}</textarea>
                                    <div class="flex justify-end">
                                        <button type="submit" class="text-sm font-medium text-blue-600 hover:underline">Save</button>
                                    </div>
                                </form>
                                <form action="{{ route('student-guardians.destroy', $guardian) }}" method="POST" class="mt-2 flex justify-end">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-sm font-medium text-red-600 hover:underline" onclick="return confirm('Remove this guardian?')">Remove</button>
                                </form>
                            </div>
                        @empty
                            <p class="rounded-md border border-dashed border-gray-300 p-4 text-sm text-gray-500">No guardians have been added yet.</p>
                        @endforelse
                    </div>
                </div>

                <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">Documents</h3>
                        <span class="text-sm text-gray-500">{{ $student->documents->count() }} uploaded</span>
                    </div>

                    <form action="{{ route('students.documents.store', $student) }}" method="POST" enctype="multipart/form-data" class="mt-4 grid gap-4">
                        @csrf
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Type</label>
                                <select name="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    @foreach(['Admission form','ID document','Transcript','Medical record','Guardian consent','Other'] as $documentType)
                                        <option value="{{ $documentType }}">{{ $documentType }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    @foreach(['Submitted','Verified','Rejected','Expired'] as $documentStatus)
                                        <option value="{{ $documentStatus }}">{{ $documentStatus }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Title</label>
                            <input type="text" name="title" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">File</label>
                            <input type="file" name="document" class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea name="notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="flex justify-end">
                            <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-gray-800">Upload Document</button>
                        </div>
                    </form>

                    <div class="mt-6 divide-y divide-gray-200 rounded-md border border-gray-200">
                        @forelse($student->documents as $document)
                            <div class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $document->title }}</p>
                                    <p class="text-xs text-gray-500">{{ $document->type }} / {{ $document->status }} / {{ $document->created_at->format('M d, Y') }}</p>
                                    @if($document->notes)
                                        <p class="mt-1 text-xs text-gray-500">{{ $document->notes }}</p>
                                    @endif
                                </div>
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('student-documents.download', $document) }}" class="text-sm font-medium text-blue-600 hover:underline">Download</a>
                                    <form action="{{ route('student-documents.destroy', $document) }}" method="POST">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm font-medium text-red-600 hover:underline" onclick="return confirm('Remove this document?')">Remove</button>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <p class="p-4 text-sm text-gray-500">No documents uploaded yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
