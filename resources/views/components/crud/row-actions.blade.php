{{--
    The icon buttons at the end of a list row: view, edit, delete (with the
    usual confirm dialog). Pass only the ones the row has; anything in the slot
    comes first (a "send" or "receipt" button).

        <x-crud.row-actions
            :view="route('admin.players.show', $player)"
            :edit="route('admin.players.edit', $player)"
            :delete="route('admin.players.destroy', $player)"
            :name="$player->name"
            confirm-text="This cannot be undone." />
--}}
@props([
    'view' => null,
    'edit' => null,
    'delete' => null,
    'name' => __('this item'),
    'confirmTitle' => null,
    'confirmText' => __('This cannot be undone.'),
    'viewLabel' => __('View'),
    'editLabel' => __('Edit'),
    'deleteLabel' => __('Delete'),
    'viewHiddenOnPhone' => true,
])

@php
    // The default "Delete" reads best as a whole sentence in Hindi ("Delete Rahul?"); a custom word (Remove...) keeps the old join.
    $deleteTitle = $confirmTitle ?? ($deleteLabel === __('Delete') ? __('Delete :name?', ['name' => $name]) : $deleteLabel.' '.$name.'?');
@endphp

<div class="crud-actions">
    {{ $slot }}

    @if($view)
        <a
            href="{{ $view }}"
            title="{{ $viewLabel }}"
            aria-label="{{ $viewLabel }} {{ $name }}"
            @class(['crud-icon-btn', 'max-md:hidden' => $viewHiddenOnPhone])
        >
            <x-icon name="eye" class="h-4 w-4" />
        </a>
    @endif

    @if($edit)
        <a
            href="{{ $edit }}"
            title="{{ $editLabel }}"
            aria-label="{{ $editLabel }} {{ $name }}"
            class="crud-icon-btn crud-icon-btn-brand"
        >
            <x-icon name="pencil" class="h-4 w-4" />
        </a>
    @endif

    @if($delete)
        <form
            method="POST"
            action="{{ $delete }}"
            class="inline"
            data-confirm-delete
            data-confirm-title="{{ $deleteTitle }}"
            data-confirm-text="{{ $confirmText }}"
        >
            @csrf
            @method('DELETE')
            <button
                type="submit"
                title="{{ $deleteLabel }}"
                aria-label="{{ $deleteLabel }} {{ $name }}"
                class="crud-icon-btn crud-icon-btn-danger"
            >
                <x-icon name="trash" class="h-4 w-4" />
            </button>
        </form>
    @endif
</div>
