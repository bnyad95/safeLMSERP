@forelse($participants as $participant)
    <a href="{{ route('class-messages.index', ['courseSection' => $courseSection, 'recipient_id' => $participant->id]) }}" class="flex items-center gap-3 px-4 py-4 {{ $selectedUser?->id === $participant->id ? 'bg-blue-50' : 'hover:bg-gray-50' }}">
        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-700">{{ strtoupper(substr($participant->name ?? 'U', 0, 1)) }}</div>
        <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-semibold text-gray-900">{{ $participant->name }}</p>
            <p class="mt-0.5 text-xs text-gray-500">{{ auth()->user()->hasRole('student') ? __('Teacher') : ($participant->academic_id ?: __('Student')) }}</p>
        </div>
        @if($participant->unread_count)
            <span class="flex h-6 min-w-6 items-center justify-center rounded-full bg-blue-700 px-1.5 text-xs font-semibold text-white">{{ $participant->unread_count }}</span>
        @endif
    </a>
@empty
    <p class="px-4 py-8 text-sm text-gray-500">{{ __('No messaging contacts are available for this class.') }}</p>
@endforelse
