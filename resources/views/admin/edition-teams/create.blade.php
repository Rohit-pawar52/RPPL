@extends('layouts.admin')

@section('title', __('Add Team to Edition'))

@section('content')
    <x-crud.back :href="route('admin.edition-teams.index')">{{ __('Edition teams') }}</x-crud.back>

    <x-crud.form :action="route('admin.edition-teams.store')" :cancel="route('admin.edition-teams.index')" :submit="__('Add team')">
        <div class="crud-grid">
            <div class="crud-main">
                <x-admin.card :title="__('Who plays where')">
                    <x-form.select
                        name="edition_id"
                        :label="__('Edition')"
                        :placeholder="__('Select an edition')"
                        :options="$editions->pluck('name', 'id')"
                        :value="request('edition_id')"
                        required
                        autofocus
                    />
                    <x-form.select
                        name="team_id"
                        :label="__('Team')"
                        :placeholder="__('Select a team')"
                        :options="$teams->pluck('name', 'id')"
                        :value="request('team_id')"
                        required
                    />
                </x-admin.card>
            </div>

            <div class="crud-aside">
                <p class="crud-note crud-note-brand">{{ __('Add a team here to enter it into an edition. You can then build its squad from the Squads page.') }}</p>
            </div>
        </div>
    </x-crud.form>
@endsection
