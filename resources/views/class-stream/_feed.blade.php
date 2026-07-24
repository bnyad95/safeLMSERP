@php
    $canInteract = $canInteract ?? true;
@endphp
<div class="space-y-4">
    @if($canCreatePost)
        <section class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm">
            <form method="POST" action="{{ route('class-stream.posts.store', $section) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <div>
                    <label for="stream-body" class="text-sm font-semibold text-gray-900">Share with your class</label>
                    <textarea id="stream-body" name="body" rows="3" class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Post an announcement, reminder, or class update...">{{ old('body') }}</textarea>
                    @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <label for="stream-attachment" class="block text-xs font-semibold text-gray-600">Attach file</label>
                        <input id="stream-attachment" type="file" name="attachment" class="mt-1 block max-w-full text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-gray-100 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-gray-700 hover:file:bg-gray-200">
                        @error('attachment')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="shrink-0 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800">Post</button>
                </div>
            </form>
        </section>
    @elseif(auth()->user()->hasRole('student'))
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm text-gray-600 shadow-sm">
            Your teacher has limited new stream posts to teachers. You can still comment and react below.
        </div>
    @endif

    <section class="space-y-4" aria-label="Class feed">
        @forelse($streamPosts as $post)
            @php
                $authorIsTeacher = $post->user?->roles?->contains('name', 'teacher') || $post->user?->roles?->contains('name', 'super_administrator');
            @endphp
            <article id="stream-post-{{ $post->id }}" class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <div class="p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-3">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $authorIsTeacher ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }} text-sm font-semibold">
                                {{ strtoupper(substr($post->user->name ?? 'U', 0, 1)) }}
                            </div>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $post->user->name ?? 'User' }}</p>
                                <p class="mt-0.5 text-xs text-gray-500">{{ $authorIsTeacher ? 'Teacher' : 'Student' }} - {{ $post->created_at->diffForHumans() }}</p>
                            </div>
                        </div>
                        @if($canInteract && (auth()->user()->hasAnyRole(['teacher', 'super_administrator']) || $post->user_id === auth()->id()))
                            <form method="POST" action="{{ route('class-stream.posts.destroy', [$section, $post]) }}">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs font-semibold text-red-600 hover:underline" onclick="return confirm('Delete this stream post?')">Delete</button>
                            </form>
                        @endif
                    </div>

                    @if($post->body)
                        <div class="mt-4 whitespace-pre-line text-sm leading-6 text-gray-800">{{ $post->body }}</div>
                    @endif

                    @if($post->attachment_path)
                        <a href="{{ route('class-stream.posts.attachment', [$section, $post]) }}" class="mt-4 flex items-center justify-between gap-4 rounded-md border border-gray-200 bg-gray-50 px-4 py-3 hover:border-blue-300 hover:bg-blue-50">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-900">{{ $post->attachment_name ?? 'Attachment' }}</p>
                                <p class="mt-1 text-xs text-gray-500">{{ $post->attachment_mime ?? 'File attachment' }}</p>
                            </div>
                            <span class="shrink-0 text-sm font-semibold text-blue-700">Download</span>
                        </a>
                    @endif
                </div>

                <div class="flex items-center gap-5 border-t border-gray-100 px-5 py-3">
                    @if($canInteract)
                        <form method="POST" action="{{ route('class-stream.reactions.toggle', [$section, $post]) }}">
                            @csrf
                            <button type="submit" class="text-sm font-semibold {{ $post->reacted_by_current_user ? 'text-blue-700' : 'text-gray-600 hover:text-blue-700' }}">
                                {{ $post->reacted_by_current_user ? 'Liked' : 'Like' }}{{ $post->reactions_count ? ' ('.$post->reactions_count.')' : '' }}
                            </button>
                        </form>
                    @else
                        <span class="text-sm text-gray-500">{{ $post->reactions_count }} {{ Str::plural('reaction', $post->reactions_count) }}</span>
                    @endif
                    <span class="text-sm text-gray-500">{{ $post->comments_count }} {{ Str::plural('comment', $post->comments_count) }}</span>
                </div>

                <div class="border-t border-gray-100 bg-gray-50 px-5 py-4">
                    @if($post->comments->isNotEmpty())
                        <div class="mb-4 space-y-3">
                            @foreach($post->comments as $comment)
                                @php $commenterIsTeacher = $comment->user?->roles?->contains('name', 'teacher') || $comment->user?->roles?->contains('name', 'super_administrator'); @endphp
                                <div class="flex gap-3">
                                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $commenterIsTeacher ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }} text-xs font-semibold">{{ strtoupper(substr($comment->user->name ?? 'U', 0, 1)) }}</div>
                                    <div class="min-w-0 rounded-lg bg-white px-3 py-2">
                                        <div class="flex flex-wrap items-baseline gap-x-2">
                                            <p class="text-xs font-semibold text-gray-900">{{ $comment->user->name ?? 'User' }}</p>
                                            <p class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</p>
                                        </div>
                                        <p class="mt-1 whitespace-pre-line text-sm text-gray-700">{{ $comment->body }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if($canInteract)
                        <form method="POST" action="{{ route('class-stream.comments.store', [$section, $post]) }}" class="flex gap-2">
                            @csrf
                            <label for="comment-{{ $post->id }}" class="sr-only">Add class comment</label>
                            <input id="comment-{{ $post->id }}" type="text" name="body" maxlength="2000" required class="min-w-0 flex-1 rounded-md border-gray-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" placeholder="Add class comment...">
                            <button type="submit" class="rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100">Comment</button>
                        </form>
                    @endif
                </div>
            </article>
        @empty
            <div class="rounded-lg border border-dashed border-gray-300 bg-white px-5 py-12 text-center">
                <p class="text-sm font-semibold text-gray-700">Nothing has been posted yet.</p>
                <p class="mt-1 text-sm text-gray-500">Class announcements and shared files will appear here.</p>
            </div>
        @endforelse
    </section>
</div>
