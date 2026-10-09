@extends('layouts.admin')

@section('title', 'Announcements')

@section('subtitle', number_format($announcements->total()).' '.\Illuminate\Support\Str::plural('notice', $announcements->total()).' for the public ticker. Only Active and Enabled announcements show right now.')

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.announcements.create')" variant="primary">+ New announcement</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th>Message</th>
                        <th>Status</th>
                        <th>Push</th>
                        <th class="hidden md:table-cell">Starts</th>
                        <th class="hidden md:table-cell">Ends</th>
                        <th class="hidden text-right sm:table-cell">Order</th>
                        <th class="hidden lg:table-cell">Created By</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($announcements as $announcement)
                        @php $pushStatus = $announcement->notificationStatusLabel(); @endphp
                        <tr class="crud-row">
                            <td class="c-title max-w-md">
                                <a href="{{ route('admin.announcements.edit', $announcement) }}" class="crud-row-link" aria-label="Edit announcement">{{ Illuminate\Support\Str::limit($announcement->message, 70) }}</a>
                                <span class="crud-meta md:hidden">
                                    @if($announcement->starts_at || $announcement->ends_at)
                                        {{ $announcement->starts_at ? display_datetime($announcement->starts_at, 'd M Y') : 'Now' }} &rarr; {{ $announcement->ends_at ? display_datetime($announcement->ends_at, 'd M Y') : 'no end' }}
                                    @else
                                        No dates set
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
                                    name="announcement"
                                    confirm-title="Delete this announcement?"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="8" icon="megaphone">
                            No announcements yet.
                            <x-slot:action>
                                <x-admin.button :href="route('admin.announcements.create')" size="sm">+ New announcement</x-admin.button>
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

    <x-crud.fab :href="route('admin.announcements.create')" label="New announcement" />
@endsection
