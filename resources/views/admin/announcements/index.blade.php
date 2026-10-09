@extends('layouts.admin')

@section('title', __('Announcements'))

@section('subtitle', ($announcements->total() === 1
    ? __('1 notice for the public ticker.')
    : __(':count notices for the public ticker.', ['count' => number_format($announcements->total())])).' '.__('Only Active and Enabled announcements show right now.'))

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.announcements.create')" variant="primary">+ {{ __('New announcement') }}</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th>{{ __('Message') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('Push') }}</th>
                        <th class="hidden md:table-cell">{{ __('Starts') }}</th>
                        <th class="hidden md:table-cell">{{ __('Ends') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('Order') }}</th>
                        <th class="hidden lg:table-cell">{{ __('Created By') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($announcements as $announcement)
                        @php $pushStatus = $announcement->notificationStatusLabel(); @endphp
                        <tr class="crud-row">
                            <td class="c-title max-w-md">
                                <a href="{{ route('admin.announcements.edit', $announcement) }}" class="crud-row-link" aria-label="{{ __('Edit announcement') }}">{{ Illuminate\Support\Str::limit($announcement->message, 70) }}</a>
                                <span class="crud-meta md:hidden">
                                    @if($announcement->starts_at || $announcement->ends_at)
                                        {{ $announcement->starts_at ? display_datetime($announcement->starts_at, 'd M Y') : __('Now') }} &rarr; {{ $announcement->ends_at ? display_datetime($announcement->ends_at, 'd M Y') : __('no end') }}
                                    @else
                                        {{ __('No dates set') }}
                                    @endif
                                </span>
                            </td>
                            <td class="c-sub">
                                <x-status-badge :status="$announcement->computedStatus()" />
                            </td>
                            <td class="max-md:hidden">
                                @if($pushStatus === 'not_scheduled')
                                    <span class="text-slate-400">—</span>
                                @else
                                    <x-status-badge :status="match($pushStatus) { 'scheduled' => 'scheduled', 'queued' => 'queued', default => 'completed' }" />
                                    @if($pushStatus === 'scheduled' && $announcement->notification_scheduled_at)
                                        <span class="crud-meta">{{ display_datetime($announcement->notification_scheduled_at, 'd M Y, h:i A') }}</span>
                                    @endif
                                @endif
                            </td>
                            <td class="hidden whitespace-nowrap md:table-cell">{{ $announcement->starts_at ? display_datetime($announcement->starts_at, 'd M Y, h:i A') : '—' }}</td>
                            <td class="hidden whitespace-nowrap md:table-cell">{{ $announcement->ends_at ? display_datetime($announcement->ends_at, 'd M Y, h:i A') : '—' }}</td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $announcement->sort_order }}</td>
                            <td class="hidden lg:table-cell">{{ $announcement->creator?->name ?? '—' }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :edit="route('admin.announcements.edit', $announcement)"
                                    :delete="route('admin.announcements.destroy', $announcement)"
                                    :name="__('announcement')"
                                    :confirm-title="__('Delete this announcement?')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="8" icon="megaphone">
                            {{ __('No announcements yet.') }}
                            <x-slot:action>
                                <x-admin.button :href="route('admin.announcements.create')" size="sm">+ {{ __('New announcement') }}</x-admin.button>
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $announcements->links() }}
    </div>

    <x-crud.fab :href="route('admin.announcements.create')" :label="__('New announcement')" />
@endsection
