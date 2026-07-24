<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold text-gray-800">
            Attendance - {{ $student->full_name }} in {{ $course->name }}
        </h2>
    </x-slot>

    <div class="py-10">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <div class="mb-6 grid grid-cols-2 gap-4 md:grid-cols-5">
                @foreach(['Total' => $report['total_classes'], 'Present' => $report['present'], 'Absent' => $report['absent'], 'Late' => $report['late'], 'Excused' => $report['excused']] as $label => $val)
                    <div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm">
                        <p class="text-xs text-gray-500">{{ $label }}</p>
                        <p class="mt-1 text-2xl font-bold text-gray-800">{{ $val }}</p>
                    </div>
                @endforeach
            </div>

            @php $pct = $report['attendance_percentage']; $color = $pct >= 75 ? 'bg-green-500' : ($pct >= 50 ? 'bg-yellow-500' : 'bg-red-500'); @endphp
            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="mb-2 flex items-center justify-between">
                    <span class="text-sm font-medium text-gray-700">Attendance Rate</span>
                    <span class="text-xl font-bold {{ $pct >= 75 ? 'text-green-700' : ($pct >= 50 ? 'text-yellow-700' : 'text-red-700') }}">
                        {{ $pct }}%
                    </span>
                </div>
                <div class="h-3 w-full rounded-full bg-gray-200">
                    <div class="{{ $color }} h-3 rounded-full transition-all" style="width: {{ $pct }}%"></div>
                </div>
                @if($pct < 75)
                    <p class="mt-2 text-xs text-red-600">Below 75% threshold - student may face restrictions.</p>
                @endif
            </div>

            <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                <div class="border-b border-gray-100 px-6 py-4">
                    <h3 class="text-sm font-semibold text-gray-800">Attendance Records</h3>
                </div>
                <table class="min-w-full divide-y divide-gray-100">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium uppercase text-gray-500">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($report['attendances'] as $record)
                            <tr>
                                <td class="px-6 py-3 text-sm text-gray-600">{{ $record->date?->format('d M Y') }}</td>
                                <td class="px-6 py-3">
                                    @php $colors = ['present'=>'bg-green-100 text-green-700','absent'=>'bg-red-100 text-red-700','late'=>'bg-yellow-100 text-yellow-700','excused'=>'bg-blue-100 text-blue-700']; @endphp
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $colors[$record->status] ?? 'bg-gray-100 text-gray-700' }}">
                                        {{ ucfirst($record->status) }}
                                    </span>
                                </td>
                                <td class="px-6 py-3 text-sm text-gray-500">{{ $record->remarks ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500">No attendance records.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
