<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800">Mark Attendance</h2>
                <p class="text-sm text-gray-600">{{ $course->name }} ({{ $course->code }}){{ $section ? ' - Group '.$section->section_code : '' }} - {{ \Illuminate\Support\Carbon::parse($selectedDate)->format('l, d F Y') }}</p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <form method="GET" action="{{ route('attendance.index', ['course' => $course->id]) }}" class="flex items-end gap-2">
                    @if($section)<input type="hidden" name="section_id" value="{{ $section->id }}">@endif
                    <label class="block">
                        <span class="text-xs font-semibold uppercase text-gray-500">Date</span>
                        <input id="attendance-date" type="date" name="date" value="{{ $selectedDate }}" class="mt-1 rounded-md border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                    </label>
                    <button type="submit" class="rounded-md bg-gray-900 px-3 py-2 text-sm font-semibold text-white hover:bg-gray-800">Open</button>
                </form>
                <a href="{{ route('attendance.report', ['course' => $course->id, 'section_id' => $section?->id]) }}"
                   class="inline-flex items-center rounded-md bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200">
                    View Report
                </a>
            </div>
        </div>
    </x-slot>

<div class="py-10">
    <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">

        <form id="attendance-form" class="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
            @csrf
            <!-- Quick select row -->
            @if($canEditAttendance)
            <div class="px-6 py-3 bg-gray-50 border-b flex items-center gap-4 flex-wrap">
                <span class="text-sm font-medium text-gray-600">Mark all as:</span>
                <button type="button" onclick="markAll('present')"
                        class="text-xs bg-green-100 text-green-700 px-3 py-1 rounded hover:bg-green-200">All Present</button>
                <button type="button" onclick="markAll('absent')"
                        class="text-xs bg-red-100 text-red-700 px-3 py-1 rounded hover:bg-red-200">All Absent</button>
                <button type="button" onclick="markAll('late')"
                        class="text-xs bg-yellow-100 text-yellow-700 px-3 py-1 rounded hover:bg-yellow-200">All Late</button>
            </div>
            @endif

            <table class="min-w-full divide-y divide-gray-100">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Student</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100" id="attendance-table">
                    @forelse($students as $student)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $student->full_name }}</td>
                            <td class="px-6 py-3 text-sm text-gray-500">{{ $student->student_id }}</td>
                            <td class="px-6 py-3">
                                <div class="flex gap-2">
                                    @foreach(['present' => ['bg-green-600','Present'], 'absent' => ['bg-red-600','Absent'], 'late' => ['bg-yellow-500','Late'], 'excused' => ['bg-blue-600','Excused']] as $status => [$color, $label])
                                        <label class="flex items-center gap-1 cursor-pointer">
                                            <input type="radio"
                                                   name="attendance[{{ $student->id }}]"
                                                   value="{{ $status }}"
                                                   class="attendance-radio"
                                                   data-student="{{ $student->id }}"
                                                   @disabled(!$canEditAttendance)
                                                   {{ ($attendance[$student->id] ?? 'present') === $status ? 'checked' : '' }}>
                                            <span class="text-xs px-2 py-0.5 rounded-full text-white {{ $color }}">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">No students are enrolled in this class.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>

            @if($canEditAttendance)
            <div class="px-6 py-4 border-t border-gray-100 flex justify-end gap-3">
                <button type="button" id="save-attendance"
                        class="bg-blue-600 text-white px-6 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">
                    Save Attendance
                </button>
            </div>
            @endif
        </form>

        <div id="attendance-feedback" class="mt-4 hidden rounded-lg px-4 py-3 text-sm"></div>
    </div>
</div>

<script>
function markAll(status) {
    document.querySelectorAll('[data-student]').forEach(radio => {
        if (radio.value === status) radio.checked = true;
    });
}

document.getElementById('save-attendance')?.addEventListener('click', function() {
    const radios = document.querySelectorAll('.attendance-radio:checked');
    const attendance = {};
    radios.forEach(r => attendance[r.dataset.student] = r.value);

    fetch("{{ route('attendance.store', ['course' => $course->id, 'section_id' => $section?->id]) }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
        },
        body: JSON.stringify({ attendance, date: document.getElementById('attendance-date')?.value }),
    })
    .then(async r => {
        const data = await r.json();
        if (!r.ok) throw data;
        return data;
    })
    .then(data => {
        const fb = document.getElementById('attendance-feedback');
        fb.className = 'mt-4 rounded-lg px-4 py-3 text-sm bg-green-50 border border-green-200 text-green-800';
        fb.textContent = data.message;
        fb.classList.remove('hidden');
        setTimeout(() => fb.classList.add('hidden'), 3000);
    })
    .catch((error) => {
        const fb = document.getElementById('attendance-feedback');
        fb.className = 'mt-4 rounded-lg px-4 py-3 text-sm bg-red-50 border border-red-200 text-red-800';
        fb.textContent = error?.message || 'Failed to save attendance. Please try again.';
        fb.classList.remove('hidden');
    });
});
</script>
</x-app-layout>
