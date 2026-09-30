{{--
    A row-selection "export/report the checked rows" action: an empty
    POST form (selected_ids[] checkboxes elsewhere on the page associate
    to it via the HTML `form="..."` attribute, so the table doesn't need
    to be wrapped in — and can't be, since per-row delete forms already
    live in its cells) plus a button that table-selection.js reveals
    once at least one row is checked.
--}}
@props(['id', 'action', 'label' => 'Export Selected ({count})'])

<form id="{{ $id }}" method="POST" action="{{ $action }}">
    @csrf
</form>
<button
    type="submit"
    form="{{ $id }}"
    id="{{ $id }}-button"
    data-label="{{ $label }}"
    hidden
    class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-green-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500 focus-visible:ring-offset-1"
>
    {{ str_replace('{count}', '', $label) }}
</button>
