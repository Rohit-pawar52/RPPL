@extends('layouts.admin')

@section('title', 'Advertisements')

@section('subtitle', number_format($advertisements->total()).' '.\Illuminate\Support\Str::plural('advertisement', $advertisements->total()).'. The sponsor level decides where each one appears. Click a status to switch it.')

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.advertisements.create')" variant="primary">+ New advertisement</x-admin.button></span>
@endsection

@section('content')
    <details class="crud-card mb-4">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-2 px-4 text-[13px] font-semibold text-brand [&::-webkit-details-marker]:hidden sm:px-5">
            <span class="inline-flex items-center gap-2"><x-crud.glyph name="image" /> Picture size for each spot</span>
            <x-icon name="chevron-down" class="h-4 w-4 text-slate-400" />
        </summary>
        <div class="border-t border-line p-3 sm:p-4">
            @include('admin.advertisements._size-guide')
        </div>
    </details>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-28">Preview</th>
                        <th>Title</th>
                        <th>Spot</th>
                        <th>Status</th>
                        <th class="hidden sm:table-cell">Dates</th>
                        <th class="hidden text-right md:table-cell">How often</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($advertisements as $advertisement)
                        <tr class="crud-row">
                            <td class="c-media w-28">
                                {{-- The picture (or a clip's preview picture); the default one when there is none or it is gone. --}}
                                <x-crud.thumb :path="$advertisement->isVideo() ? $advertisement->poster_path : $advertisement->media_path" kind="image" shape="wide" size="md" fit="contain" />
                            </td>
                            <td class="c-title max-w-sm">
                                <a href="{{ route('admin.advertisements.edit', $advertisement) }}" class="crud-row-link" aria-label="Edit advertisement">{{ Illuminate\Support\Str::limit($advertisement->title, 60) }}</a>
                                @if($advertisement->isVideo())
                                    <span class="crud-pill ml-1">video</span>
                                @endif
                                <span class="crud-meta md:hidden">{{ $advertisement->spotLabel() }}</span>
                            </td>
                            <td class="max-md:hidden">
                                <span class="crud-pill crud-pill-brand">{{ $advertisement->spotLabel() }}</span>
                            </td>
                            <td class="c-sub">
                                <x-status-toggle :action="route('admin.advertisements.toggle-status', $advertisement)" :status="$advertisement->status" noun="advertisement" />
                            </td>
                            <td class="hidden text-slate-500 sm:table-cell">{{ $advertisement->scheduleLabel() }}</td>
                            <td class="hidden text-right tabular-nums md:table-cell">
                                {{ $advertisement->tier === 'normal' ? $advertisement->weight : '—' }}
                            </td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :edit="route('admin.advertisements.edit', $advertisement)"
                                    :delete="route('admin.advertisements.destroy', $advertisement)"
                                    name="advertisement"
                                    confirm-title="Delete this advertisement?"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7" icon="megaphone">
                            No advertisements yet.
                            <x-slot:action>
                                <x-admin.button :href="route('admin.advertisements.create')" size="sm">+ Add the first sponsor</x-admin.button>
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $advertisements->links() }}
    </div>

    <x-crud.fab :href="route('admin.advertisements.create')" label="New advertisement" />
@endsection
