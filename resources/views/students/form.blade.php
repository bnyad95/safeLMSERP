@php
    $isEdit = isset($student);
    $selectedUniversityId = old('university_id', $student->university_id ?? '');
    $selectedCollegeId = old('college_id', $student->department?->college_id ?? '');
    $selectedDepartmentId = old('department_id', $student->department_id ?? '');
@endphp

<div class="space-y-6 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900 sm:p-6">
    <section>
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Student identity</h3>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div>
                <label for="student-full-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Full name</label>
                <input id="student-full-name" type="text" name="full_name" value="{{ old('full_name', $student->full_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                <x-input-error :messages="$errors->get('full_name')" class="mt-2" />
            </div>
            <div>
                <span class="block text-sm font-medium text-gray-700 dark:text-gray-300">Student ID</span>
                <p class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200">{{ $isEdit ? $student->student_id : ($suggestedStudentId ?? 'Generated on save') }}</p>
            </div>
            <div>
                <label for="student-email" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email</label>
                <input id="student-email" type="email" name="email" value="{{ old('email', $student->email ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <label for="student-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                <input id="student-phone" type="text" name="phone" value="{{ old('phone', $student->phone ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
        </div>
    </section>

    <section class="border-t border-gray-200 pt-6 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Academic organization</h3>
        <div class="mt-4 grid gap-5 md:grid-cols-3">
            <div>
                <label for="student-university" class="block text-sm font-medium text-gray-700 dark:text-gray-300">University</label>
                <select id="student-university" name="university_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                    <option value="">Select university</option>
                    @foreach($universities as $university)
                        <option value="{{ $university->id }}" @selected((string) $selectedUniversityId === (string) $university->id)>{{ $university->name }}{{ $university->code ? ' / '.$university->code : '' }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('university_id')" class="mt-2" />
            </div>
            <div>
                <label for="student-college" class="block text-sm font-medium text-gray-700 dark:text-gray-300">College</label>
                <select id="student-college" name="college_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                    <option value="">Select college</option>
                    @foreach($colleges as $college)
                        <option value="{{ $college->id }}" data-university-id="{{ $college->university_id }}" @selected((string) $selectedCollegeId === (string) $college->id)>{{ $college->name }}{{ $college->code ? ' / '.$college->code : '' }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('college_id')" class="mt-2" />
            </div>
            <div>
                <label for="student-department" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Department</label>
                <select id="student-department" name="department_id" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                    <option value="">Select department</option>
                    @foreach($departments as $department)
                        <option value="{{ $department->id }}" data-university-id="{{ $department->university_id }}" data-college-id="{{ $department->college_id }}" @selected((string) $selectedDepartmentId === (string) $department->id)>{{ $department->name }}{{ $department->code ? ' / '.$department->code : '' }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('department_id')" class="mt-2" />
            </div>
            <div>
                <label for="student-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Student status</label>
                <select id="student-status" name="status" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required>
                    @foreach(['Active', 'Inactive', 'Graduated'] as $statusOption)
                        <option value="{{ $statusOption }}" @selected(old('status', $student->status ?? 'Active') === $statusOption)>{{ $statusOption }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('status')" class="mt-2" />
            </div>
        </div>
    </section>

    @unless($isEdit)
        <section class="border-t border-gray-200 pt-6 dark:border-gray-700">
            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Student login</h3>
            <div class="mt-4 grid gap-5 md:grid-cols-2">
                <div>
                    <label for="student-password" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Temporary password</label>
                    <input id="student-password" type="password" name="password" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required autocomplete="new-password">
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>
                <div>
                    <label for="student-password-confirmation" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Confirm temporary password</label>
                    <input id="student-password-confirmation" type="password" name="password_confirmation" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100" required autocomplete="new-password">
                </div>
            </div>
        </section>
    @endunless

    <section class="border-t border-gray-200 pt-6 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Admissions</h3>
        <div class="mt-4 grid gap-5 md:grid-cols-2">
            <div>
                <label for="student-admission-status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Admission status</label>
                <select id="student-admission-status" name="admission_status" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                    @foreach(['Applicant', 'Admitted', 'Enrolled', 'Deferred', 'Withdrawn', 'Graduated'] as $admissionStatus)
                        <option value="{{ $admissionStatus }}" @selected(old('admission_status', $student->admission_status ?? 'Admitted') === $admissionStatus)>{{ $admissionStatus }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('admission_status')" class="mt-2" />
            </div>
            <div>
                <label for="student-admission-date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Admission date</label>
                <input id="student-admission-date" type="date" name="admission_date" value="{{ old('admission_date', isset($student) && $student->admission_date ? $student->admission_date->format('Y-m-d') : '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
                <x-input-error :messages="$errors->get('admission_date')" class="mt-2" />
            </div>
            <div>
                <label for="student-admission-type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Admission type</label>
                <input id="student-admission-type" type="text" name="admission_type" value="{{ old('admission_type', $student->admission_type ?? '') }}" placeholder="Regular, transfer, scholarship" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label for="student-previous-school" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Previous school</label>
                <input id="student-previous-school" type="text" name="previous_school" value="{{ old('previous_school', $student->previous_school ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </div>
            <div class="md:col-span-2">
                <label for="student-address" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address</label>
                <textarea id="student-address" name="address" rows="3" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">{{ old('address', $student->address ?? '') }}</textarea>
            </div>
        </div>
    </section>

    <section class="border-t border-gray-200 pt-6 dark:border-gray-700">
        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Emergency contact</h3>
        <div class="mt-4 grid gap-5 md:grid-cols-3">
            <div>
                <label for="student-emergency-name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                <input id="student-emergency-name" type="text" name="emergency_contact_name" value="{{ old('emergency_contact_name', $student->emergency_contact_name ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label for="student-emergency-relationship" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Relationship</label>
                <input id="student-emergency-relationship" type="text" name="emergency_contact_relationship" value="{{ old('emergency_contact_relationship', $student->emergency_contact_relationship ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </div>
            <div>
                <label for="student-emergency-phone" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Phone</label>
                <input id="student-emergency-phone" type="text" name="emergency_contact_phone" value="{{ old('emergency_contact_phone', $student->emergency_contact_phone ?? '') }}" class="mt-1 block w-full rounded-md border-gray-300 bg-white text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100">
            </div>
        </div>
    </section>

    <div class="flex flex-col-reverse gap-3 border-t border-gray-200 pt-6 dark:border-gray-700 sm:flex-row sm:justify-end">
        <a href="{{ route('students.index') }}" class="inline-flex items-center justify-center rounded-md border border-gray-300 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">Cancel</a>
        <button type="submit" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">{{ $isEdit ? 'Update Student' : 'Create Student' }}</button>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const university = document.getElementById('student-university');
        const college = document.getElementById('student-college');
        const department = document.getElementById('student-department');

        if (!university || !college || !department) return;

        const filterOptions = (field, predicate) => {
            Array.from(field.options).forEach((option, index) => {
                if (index === 0) return;
                const visible = predicate(option);
                option.hidden = !visible;
                option.disabled = !visible;
                if (option.selected && !visible) field.value = '';
            });
        };

        const selectOnlyOption = (field) => {
            if (field.value) return;
            const options = Array.from(field.options).filter((option, index) => index > 0 && !option.disabled);
            if (options.length === 1) field.value = options[0].value;
        };

        const cascade = () => {
            const universityId = university.value;
            filterOptions(college, (option) => universityId !== '' && option.dataset.universityId === universityId);
            selectOnlyOption(college);

            const collegeId = college.value;
            filterOptions(department, (option) => universityId !== '' && collegeId !== ''
                && option.dataset.universityId === universityId
                && option.dataset.collegeId === collegeId);
            selectOnlyOption(department);
        };

        university.addEventListener('change', cascade);
        college.addEventListener('change', cascade);
        selectOnlyOption(university);
        cascade();
    });
</script>
