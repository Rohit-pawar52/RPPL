{{--
    Media Files tab — removes ORPHANED uploaded files only: files sitting
    in a known upload folder that no database record references any more.
    Videos, photos, news, rules, players, teams and contributors keep every
    file their records point to (active or inactive); this never deletes a
    record, and branding and registration documents are not covered at all.
    The scan below is read-only; nothing is deleted until a category is
    confirmed.
--}}
<section class="crud-card">
    <div class="crud-card-body">
        <p class="mb-2 text-xs text-slate-500">
            Lists uploaded files that no record refers to any more (for example a file left behind after its record was removed). Files that are still referenced are never listed, even if their video, photo, news post or rule is inactive or unpublished.
        </p>
        <p class="text-xs text-slate-500">
            Files uploaded in the last {{ $mediaMinAgeHours }} hours are never treated as orphans, so an upload that is still being saved is safe. Deleting cannot be undone.
        </p>
    </div>

    <div class="border-t border-line">
        <div class="crud-table-scroll">
            <table class="crud-table">
                <thead>
                    <tr>
                        <th>Category</th>
                        <th>Folder</th>
                        <th class="text-right">Files</th>
                        <th class="text-right">Orphaned</th>
                        <th class="text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($mediaScan as $key => $scan)
                        @php $orphanCount = count($scan['orphans']); @endphp
                        <tr class="align-top">
                            <td class="c-title">
                                {{ $scan['label'] }}
                                @if($scan['recent'] > 0)
                                    <span class="crud-meta">{{ $scan['recent'] }} recent upload(s) protected</span>
                                @endif
                                @if($scan['skipped'] > 0)
                                    <span class="crud-meta">{{ $scan['skipped'] }} unexpected file(s) ignored</span>
                                @endif
                            </td>
                            <td class="font-mono text-[12px] text-slate-500">{{ $scan['directory'] }}/</td>
                            <td class="text-right tabular-nums">{{ $scan['scanned'] }}</td>
                            <td class="text-right font-semibold tabular-nums {{ $orphanCount > 0 ? 'text-amber-700' : 'text-slate-400' }}">{{ $orphanCount }}</td>
                            <td class="text-right">
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
                                        <button type="submit" class="btn btn-danger-soft btn-sm">
                                            <x-icon name="trash" class="h-4 w-4" /> Delete orphans
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[12px] text-slate-400">Nothing to clean</span>
                                @endif
                            </td>
                        </tr>
                        @if($orphanCount > 0)
                            <tr>
                                <td colspan="5" class="bg-slate-50 px-4 py-2.5">
                                    <details>
                                        <summary class="cursor-pointer text-[12px] font-medium text-slate-600">Preview the {{ $orphanCount }} file(s) that would be deleted</summary>
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
</section>
