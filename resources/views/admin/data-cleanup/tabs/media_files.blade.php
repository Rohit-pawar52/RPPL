{{--
    Media Files tab — removes ORPHANED uploaded files only: files sitting
    in a known upload folder that no database record references any more.
    Videos, photos, news, rules, players, teams and contributors keep every
    file their records point to (active or inactive); this never deletes a
    record, and branding and registration documents are not covered at all.
    The scan below is read-only; nothing is deleted until a category is
    confirmed.
--}}
<div>
    <p class="mb-2 text-xs text-slate-500">
        Lists uploaded files that no record refers to any more (for example a file left behind after its record was removed). Files that are still referenced are never listed, even if their video, photo, news post or rule is inactive or unpublished.
    </p>
    <p class="mb-4 text-xs text-slate-500">
        Files uploaded in the last {{ $mediaMinAgeHours }} hours are never treated as orphans, so an upload that is still being saved is safe. Deleting cannot be undone.
    </p>

    <div class="overflow-x-auto rounded-md border border-slate-200">
        <table class="w-full min-w-[520px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-3 py-2 font-medium">Category</th>
                    <th class="px-3 py-2 font-medium">Folder</th>
                    <th class="px-3 py-2 text-right font-medium">Files</th>
                    <th class="px-3 py-2 text-right font-medium">Orphaned</th>
                    <th class="px-3 py-2 text-right font-medium">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($mediaScan as $key => $scan)
                    @php $orphanCount = count($scan['orphans']); @endphp
                    <tr class="align-top">
                        <td class="px-3 py-2 font-medium text-slate-800">
                            {{ $scan['label'] }}
                            @if($scan['recent'] > 0)
                                <span class="block text-[11px] font-normal text-slate-400">{{ $scan['recent'] }} recent upload(s) protected</span>
                            @endif
                            @if($scan['skipped'] > 0)
                                <span class="block text-[11px] font-normal text-slate-400">{{ $scan['skipped'] }} unexpected file(s) ignored</span>
                            @endif
                        </td>
                        <td class="px-3 py-2 text-slate-500">{{ $scan['directory'] }}/</td>
                        <td class="px-3 py-2 text-right text-slate-600">{{ $scan['scanned'] }}</td>
                        <td class="px-3 py-2 text-right font-medium {{ $orphanCount > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $orphanCount }}</td>
                        <td class="px-3 py-2 text-right">
                            @if($orphanCount > 0)
                                <form
                                    method="POST"
                                    action="{{ route('admin.data-cleanup.media-files.destroy', $key) }}"
                                    data-confirm-action
                                    data-confirm-title="Delete {{ $orphanCount }} orphaned file(s)?"
                                    data-confirm-text="Only files that no record refers to are deleted, and this cannot be undone. No posts, photos, videos or other records are affected."
                                    data-confirm-button-text="Yes, delete"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        class="rounded-md border border-red-200 bg-red-50 px-2.5 py-1 text-[12px] font-medium text-red-700 hover:bg-red-100"
                                    >
                                        Delete orphans
                                    </button>
                                </form>
                            @else
                                <span class="text-[12px] text-slate-400">Nothing to clean</span>
                            @endif
                        </td>
                    </tr>
                    @if($orphanCount > 0)
                        <tr>
                            <td colspan="5" class="bg-slate-50 px-3 py-2">
                                <details>
                                    <summary class="cursor-pointer text-[12px] text-slate-500">Preview the {{ $orphanCount }} file(s) that would be deleted</summary>
                                    <ul class="mt-1.5 space-y-0.5 text-[11px] text-slate-500">
                                        @foreach(array_slice($scan['orphans'], 0, 25) as $path)
                                            <li class="break-all">{{ $path }}</li>
                                        @endforeach
                                        @if($orphanCount > 25)
                                            <li>&hellip; and {{ $orphanCount - 25 }} more</li>
                                        @endif
                                    </ul>
                                </details>
                            </td>
                        </tr>
                    @endif
                @endforeach
            </tbody>
        </table>
    </div>
</div>
