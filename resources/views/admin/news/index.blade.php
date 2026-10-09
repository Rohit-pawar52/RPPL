@extends('layouts.admin')

@section('title', __('News'))

@php
    $total = $newsItems->total();
    $count = number_format($total);
    $subtitle = $total === 1
        ? __(':count post for the public News page. Active, already-published posts appear, lowest priority first.', ['count' => $count])
        : __(':count posts for the public News page. Active, already-published posts appear, lowest priority first.', ['count' => $count]);
@endphp

@section('subtitle', $subtitle)

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.news.create')" variant="primary">{{ __('+ New news') }}</x-admin.button></span>
@endsection

@section('content')
    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th class="w-24">{{ __('Cover') }}</th>
                        <th>{{ __('Heading') }}</th>
                        <th class="hidden md:table-cell">{{ __('Published') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('Images') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('Priority') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($newsItems as $news)
                        @php $scheduled = $news->status === 'active' && $news->published_at?->isFuture(); @endphp
                        <tr class="crud-row">
                            <td class="c-media w-24">
                                <x-crud.thumb :path="$news->coverImage?->image_path" kind="image" shape="wide" size="sm" :alt="$news->title" />
                            </td>
                            <td class="c-title max-w-md">
                                <a href="{{ route('admin.news.edit', $news) }}" class="crud-row-link" aria-label="{{ __('Edit news') }}">{{ Illuminate\Support\Str::limit($news->title, 70) }}</a>
                                <span class="crud-meta md:hidden">
                                    {{ display_datetime($news->published_at, 'd M Y') }}
                                    @if($scheduled) &middot; <span class="font-medium text-amber-600">{{ __('Scheduled') }}</span> @endif
                                </span>
                            </td>
                            <td class="hidden whitespace-nowrap text-slate-500 md:table-cell">
                                {{ display_datetime($news->published_at, 'd M Y, h:i A') }}
                                @if($scheduled)
                                    <span class="block text-[11px] font-medium text-amber-600">{{ __('Scheduled') }}</span>
                                @endif
                            </td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $news->images_count }}</td>
                            <td class="c-sub">
                                <x-status-toggle :action="route('admin.news.toggle-status', $news)" :status="$news->status" :noun="__('news')" />
                            </td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $news->priority }}</td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :edit="route('admin.news.edit', $news)"
                                    :delete="route('admin.news.destroy', $news)"
                                    :name="__('news')"
                                    :confirm-title="__('Delete this news post?')"
                                    :confirm-text="__('Its images will be deleted too. This cannot be undone.')"
                                />
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7" icon="newspaper">
                            {{ __('No news yet.') }}
                            <x-slot:action>
                                <x-admin.button :href="route('admin.news.create')" size="sm">{{ __('+ Write the first post') }}</x-admin.button>
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $newsItems->links() }}
    </div>

    <x-crud.fab :href="route('admin.news.create')" :label="__('New news')" />
@endsection
