{{-- A filter dropdown of a list toolbar (inside <x-table-filters>). The first
     option ("All ...") clears the filter; picking an option applies it at
     once, no extra Filter click.

        <x-crud.select name="status" all="All statuses" :value="$filters['status'] ?? ''"
            :options="['active' => 'Active', 'inactive' => 'Inactive']" />
--}}
@props(['name', 'options' => [], 'value' => '', 'all' => __('All'), 'auto' => true])

<select
    name="{{ $name }}"
    aria-label="{{ $all }}"
    @if($auto) onchange="this.form.submit()" @endif
    class="crud-field crud-select"
>
    <option value="">{{ $all }}</option>
    @foreach($options as $optionValue => $optionLabel)
        <option value="{{ $optionValue }}" @selected((string) $value === (string) $optionValue)>{{ $optionLabel }}</option>
    @endforeach
</select>
