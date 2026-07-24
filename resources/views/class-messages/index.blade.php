<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold text-gray-800">Class Messages</h2>
            <p class="text-sm text-gray-600">Private messages for {{ $courseSection->course->name ?? 'Class' }} - Group {{ $courseSection->section_code }}</p>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <a href="{{ auth()->user()->hasRole('student') ? route('class-stream.show', $courseSection) : route('teacher-dashboard', ['section_id' => $courseSection->id]) }}" class="mb-4 inline-flex text-sm font-semibold text-blue-700 hover:underline">Back to class</a>

            <div class="grid min-h-[620px] overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm lg:grid-cols-[280px_minmax(0,1fr)]" @if($selectedUser) data-class-messages data-thread-url="{{ route('class-messages.thread', ['courseSection' => $courseSection, 'recipient_id' => $selectedUser->id]) }}" @endif>
                <aside class="border-b border-gray-200 lg:border-b-0 lg:border-r">
                    <div class="border-b border-gray-200 px-4 py-4">
                        <h3 class="text-sm font-semibold text-gray-900">Conversations</h3>
                    </div>
                    <div class="divide-y divide-gray-100" data-conversation-list>
                        @include('class-messages._participants')
                    </div>
                </aside>

                <section class="flex min-w-0 flex-col">
                    @if($selectedUser)
                        <div class="flex items-center gap-3 border-b border-gray-200 px-5 py-4">
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-100 text-sm font-semibold text-blue-800">{{ strtoupper(substr($selectedUser->name ?? 'U', 0, 1)) }}</div>
                            <div><h3 class="text-sm font-semibold text-gray-900">{{ $selectedUser->name }}</h3><p class="text-xs text-gray-500">Private class conversation</p></div>
                        </div>

                        <div class="flex-1 space-y-3 overflow-y-auto bg-gray-50 p-5" data-message-thread data-signature="">
                            @include('class-messages._messages')
                        </div>

                        <form method="POST" action="{{ route('class-messages.store', $courseSection) }}" enctype="multipart/form-data" class="border-t border-gray-200 bg-white p-4" data-message-form>
                            @csrf
                            <input type="hidden" name="recipient_id" value="{{ $selectedUser->id }}">
                            <label for="message-body" class="sr-only">Message</label>
                            <textarea id="message-body" name="body" rows="2" class="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Write a message...">{{ old('body') }}</textarea>
                            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <input type="file" name="attachment" class="block max-w-full text-sm text-gray-600 file:mr-2 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold">
                                <button type="submit" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Send</button>
                            </div>
                            <p class="mt-2 hidden text-sm text-red-600" data-message-error></p>
                            @error('body')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                            @error('attachment')<p class="mt-2 text-sm text-red-600">{{ $message }}</p>@enderror
                        </form>
                    @else
                        <div class="flex flex-1 items-center justify-center p-8 text-center"><div><p class="text-sm font-semibold text-gray-700">No conversation selected</p><p class="mt-1 text-sm text-gray-500">Choose a student to view or begin a private conversation.</p></div></div>
                    @endif
                </section>
            </div>
        </div>
    </div>
</x-app-layout>
