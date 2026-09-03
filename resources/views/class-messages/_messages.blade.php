@forelse($messages as $message)
    @php $mine = $message->sender_id === auth()->id(); @endphp
    <div class="flex {{ $mine ? 'justify-end' : 'justify-start' }}">
        <div class="max-w-[85%] rounded-lg px-4 py-3 {{ $mine ? 'bg-blue-700 text-white' : 'border border-gray-200 bg-white text-gray-800' }} sm:max-w-[70%]">
            @if($message->body)<p class="whitespace-pre-line text-sm leading-6">{{ $message->body }}</p>@endif
            @if($message->attachment_path)
                <a href="{{ route('class-messages.attachment', [$courseSection, $message]) }}" class="mt-2 block truncate text-sm font-semibold underline {{ $mine ? 'text-blue-50' : 'text-blue-700' }}">{{ $message->attachment_name ?? __('Download attachment') }}</a>
            @endif
            <p class="mt-1 text-right text-xs {{ $mine ? 'text-blue-100' : 'text-gray-400' }}">{{ $message->created_at->timezone('Asia/Baghdad')->format('M d, H:i') }}{{ $mine && $message->read_at ? ' - '.__('Read') : '' }}</p>
        </div>
    </div>
@empty
    <div class="flex h-full items-center justify-center text-center"><div><p class="text-sm font-semibold text-gray-700">{{ __('Start the conversation') }}</p><p class="mt-1 text-sm text-gray-500">{{ __('Messages in this thread are private.') }}</p></div></div>
@endforelse
