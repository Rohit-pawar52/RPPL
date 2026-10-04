@extends('layouts.admin')

@section('title', 'Advertisements')

@section('subtitle', 'Sponsor images and videos for the public site. The sponsor level decides where each one appears. Click a status to switch it.')

@section('actions')
    <x-admin.button href="{{ route('admin.advertisements.create') }}" variant="primary">+ New advertisement</x-admin.button>
@endsection

@section('content')
    <details class="mb-3">
        <summary class="cursor-pointer text-xs font-medium text-green-700">Picture size for each spot</summary>
        <div class="mt-2 max-w-2xl">
            @include('admin.advertisements._size-guide')
        </div>
    </details>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Preview</th>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="px-4 py-2 font-medium">Spot</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 font-medium sm:table-cell">Dates</th>
                    <th class="hidden px-4 py-2 text-right font-medium md:table-cell">How often</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($advertisements as $advertisement)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <div class="flex h-10 w-20 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 text-slate-300">
                                {{-- The picture (or a clip's preview picture); the default one when there is none or it is gone. --}}
                                <x-media-image :path="$advertisement->isVideo() ? $advertisement->poster_path : $advertisement->media_path" kind="image" alt="" class="h-full w-full object-contain" />
                            </div>
                        </td>
                        <td class="max-w-xs px-4 py-2 font-medium text-slate-800">
                            {{ Illuminate\Support\Str::limit($advertisement->title, 60) }}
                            @if($advertisement->isVideo())
                                <span class="ml-1 text-[11px] font-normal text-slate-400">video</span>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-slate-600">{{ $advertisement->spotLabel() }}</td>
                        <td class="px-4 py-2">
                            <x-status-toggle :action="route('admin.advertisements.toggle-status', $advertisement)" :status="$advertisement->status" noun="advertisement" />
                        </td>
                        <td class="hidden px-4 py-2 text-slate-500 sm:table-cell">{{ $advertisement->scheduleLabel() }}</td>
                        <td class="hidden px-4 py-2 text-right text-slate-600 md:table-cell">
                            {{ $advertisement->tier === 'normal' ? $advertisement->weight : '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.advertisements.edit', $advertisement) }}"
                                    title="Edit"
                                    aria-label="Edit advertisement"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.advertisements.destroy', $advertisement) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this advertisement?"
                                    data-confirm-text="This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete advertisement"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="7">No advertisements yet.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $advertisements->links() }}
    </div>
@endsection
