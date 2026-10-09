@extends('layouts.admin')

@section('title', __('Contributor Details'))

@section('content')
    {{-- Needs contributors.view (admin by default): the phone may be shown here. --}}
    <x-crud.back :href="route('admin.contributors.index')">{{ __('Contributors') }}</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile
            :title="$contributor->name"
            :path="$contributor->photo_path"
            kind="user"
            :status="$contributor->is_active ? 'active' : 'inactive'"
            :subtitle="collect([$contributor->village, $contributor->phone ?: __('No phone on file')])->filter()->implode(' · ')"
        >
            @if($isCurrentCommitteeMember)
                <span class="crud-pill crud-pill-blue">{{ __('Committee Member') }}{{ $currentEdition ? ' · '.$currentEdition->name : '' }}</span>
            @endif

            <x-slot:actions>
                @can('create', \App\Models\EditionContribution::class)
                    <x-admin.button :href="route('admin.edition-contributions.create', ['contributor_id' => $contributor->id])" variant="secondary" icon="currency">{{ __('Record contribution') }}</x-admin.button>
                @endcan
                <x-admin.button :href="route('admin.contributors.edit', $contributor)" icon="pencil">{{ __('Edit') }}</x-admin.button>
            </x-slot:actions>
        </x-crud.profile>

        <x-admin.card :title="__('Summary')">
            <dl class="crud-facts">
                <x-crud.fact big :label="__('Contributions recorded')">{{ $contributor->contributions_count }}</x-crud.fact>
                <x-crud.fact :label="__('Village')">{{ $contributor->village ?: '—' }}</x-crud.fact>
                <x-crud.fact :label="__('Address')" class="col-span-2">{{ $contributor->address ?: '—' }}</x-crud.fact>
                <x-crud.fact :label="__('Committee').($currentEdition ? ' ('.$currentEdition->name.')' : '')" class="col-span-2">
                    @if($isCurrentCommitteeMember)
                        <span class="crud-pill crud-pill-blue">{{ __('Committee Member') }}</span>
                    @else
                        <span class="font-normal text-slate-500">{{ $currentEdition ? __('Not a committee member of :edition', ['edition' => $currentEdition->name]) : __('Not a committee member') }}</span>
                    @endif
                </x-crud.fact>
            </dl>
            @if($contributor->contributions_count > 0)
                @can('viewAny', \App\Models\EditionContribution::class)
                    <p class="mt-4 border-t border-line pt-3">
                        <a href="{{ route('admin.edition-contributions.index', ['contributor_id' => $contributor->id]) }}" class="crud-link text-[13px]">{{ __('See all contributions') }} &rarr;</a>
                    </p>
                @endcan
            @endif
        </x-admin.card>
    </div>
@endsection
