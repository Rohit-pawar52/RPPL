@extends('layouts.admin')

@section('title', 'Contributor Details')

@section('content')
    {{-- Needs contributors.view (admin by default): the phone may be shown here. --}}
    <x-crud.back :href="route('admin.contributors.index')">Contributors</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile
            :title="$contributor->name"
            :path="$contributor->photo_path"
            kind="user"
            :status="$contributor->is_active ? 'active' : 'inactive'"
            :subtitle="$contributor->phone ?: 'No phone on file'"
        >
            @if($isCurrentCommitteeMember)
                <span class="crud-pill crud-pill-blue">Committee Member{{ $currentEdition ? ' · '.$currentEdition->name : '' }}</span>
            @endif

            <x-slot:actions>
                @can('create', \App\Models\EditionContribution::class)
                    <x-admin.button :href="route('admin.edition-contributions.create', ['contributor_id' => $contributor->id])" variant="secondary" icon="currency">Record contribution</x-admin.button>
                @endcan
                <x-admin.button :href="route('admin.contributors.edit', $contributor)" icon="pencil">Edit</x-admin.button>
            </x-slot:actions>
        </x-crud.profile>

        <x-admin.card title="Summary">
            <dl class="crud-facts">
                <x-crud.fact big label="Contributions recorded">{{ $contributor->contributions_count }}</x-crud.fact>
                <x-crud.fact label="Committee{{ $currentEdition ? ' ('.$currentEdition->name.')' : '' }}" class="col-span-2">
                    @if($isCurrentCommitteeMember)
                        <span class="crud-pill crud-pill-blue">Committee Member</span>
                    @else
                        <span class="font-normal text-slate-500">Not a committee member{{ $currentEdition ? " of {$currentEdition->name}" : '' }}</span>
                    @endif
                </x-crud.fact>
            </dl>
            @if($contributor->contributions_count > 0)
                @can('viewAny', \App\Models\EditionContribution::class)
                    <p class="mt-4 border-t border-line pt-3">
                        <a href="{{ route('admin.edition-contributions.index', ['contributor_id' => $contributor->id]) }}" class="crud-link text-[13px]">See all contributions &rarr;</a>
                    </p>
                @endcan
            @endif
        </x-admin.card>
    </div>
@endsection
