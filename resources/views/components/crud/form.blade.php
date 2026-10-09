{{--
    The frame of every create / edit page: the <form> itself plus a bar with
    Save and Cancel that stays at the bottom of the screen, so saving is one
    tap however long the form is. Put the fields inside as cards:

        <x-crud.form :action="route('admin.players.store')" :cancel="route('admin.players.index')" submit="Save player" files>
            @include('admin.players._form')
        </x-crud.form>

    method  POST (default) or PUT; files adds enctype for uploads.
    note    a small line shown left of the buttons on wide screens.
--}}
@props(['action', 'cancel', 'submit' => __('Save'), 'method' => 'POST', 'files' => false, 'note' => null])

<form method="POST" action="{{ $action }}" @if($files) enctype="multipart/form-data" @endif novalidate {{ $attributes }}>
    @csrf
    @if(strtoupper($method) !== 'POST')
        @method($method)
    @endif

    {{ $slot }}

    <x-admin.form-actions>
        <x-admin.button>{{ $submit }}</x-admin.button>
        <x-admin.button :href="$cancel" variant="secondary">{{ __('Cancel') }}</x-admin.button>
        @if($note)
            <p class="mr-auto hidden text-xs text-slate-500 md:block">{{ $note }}</p>
        @endif
    </x-admin.form-actions>
</form>
