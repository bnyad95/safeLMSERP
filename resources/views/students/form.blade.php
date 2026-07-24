@php $isEdit = isset($student); @endphp

<div class="space-y-6 bg-white p-6 shadow-sm rounded-lg border border-gray-200">
    <div class="grid gap-6 md:grid-cols-2">
        <div>
            <label class="block text-sm font-medium text-gray-700">Full name</label>
            <input type="text" name="full_name" value="{{ old('full_name', $student->full_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Student ID</label>
            <input type="text" name="student_id" value="{{ old('student_id', $student->student_id ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Email</label>
            <input type="email" name="email" value="{{ old('email', $student->email ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Phone</label>
            <input type="text" name="phone" value="{{ old('phone', $student->phone ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Department</label>
            <select name="department_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                <option value="">Select department</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" @selected(old('department_id', $student->department_id ?? '') == $department->id)>{{ $department->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <select name="status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                @foreach(['Active','Inactive','Graduated'] as $statusOption)
                    <option value="{{ $statusOption }}" @selected(old('status', $student->status ?? '') === $statusOption)>{{ $statusOption }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @unless($isEdit)
        <div class="border-t border-gray-200 pt-6">
            <h3 class="text-base font-semibold text-gray-900">Student Login</h3>
            <p class="mt-1 text-sm text-gray-600">Create a temporary password. The student must replace it after the first login.</p>
            <div class="mt-4 grid gap-6 md:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700">Temporary password</label>
                    <input type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required autocomplete="new-password">
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Confirm temporary password</label>
                    <input type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required autocomplete="new-password">
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
                </div>
            </div>
        </div>
    @endunless

    <div class="border-t border-gray-200 pt-6">
        <h3 class="text-base font-semibold text-gray-900">Admissions</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium text-gray-700">Admission status</label>
                <select name="admission_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    @foreach(['Applicant','Admitted','Enrolled','Deferred','Withdrawn','Graduated'] as $admissionStatus)
                        <option value="{{ $admissionStatus }}" @selected(old('admission_status', $student->admission_status ?? 'Admitted') === $admissionStatus)>{{ $admissionStatus }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Admission date</label>
                <input type="date" name="admission_date" value="{{ old('admission_date', isset($student) && $student->admission_date ? $student->admission_date->format('Y-m-d') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Admission type</label>
                <input type="text" name="admission_type" value="{{ old('admission_type', $student->admission_type ?? '') }}" placeholder="Regular, transfer, scholarship" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Previous school</label>
                <input type="text" name="previous_school" value="{{ old('previous_school', $student->previous_school ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium text-gray-700">Address</label>
                <textarea name="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('address', $student->address ?? '') }}</textarea>
            </div>
        </div>
    </div>

    <div class="border-t border-gray-200 pt-6">
        <h3 class="text-base font-semibold text-gray-900">Emergency Contact</h3>
        <div class="mt-4 grid gap-6 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium text-gray-700">Name</label>
                <input type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $student->emergency_contact_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Relationship</label>
                <input type="text" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $student->emergency_contact_relationship ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700">Phone</label>
                <input type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $student->emergency_contact_phone ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            </div>
        </div>
    </div>

    <div class="flex items-center justify-end gap-3">
        <a href="{{ route('students.index') }}" class="rounded-md border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $isEdit ? 'Update Student' : 'Create Student' }}</button>
    </div>
</div>
