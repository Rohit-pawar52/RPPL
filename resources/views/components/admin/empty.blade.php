{{--
    A designed empty state: icon, one line, and the next action.

    Props
      icon     an <x-icon> / <x-admin.icon> name (default: inbox)
      table    true = render as a full-width <tr><td> (inside a <tbody>)
      colspan  the column count for that row
      title    an optional bold line above the text
    Slots
      (default)  the explanation, e.g. "No users found."
      action     the next step: a button or link (optional)
--}}
@props(['icon' => null, 'table' => false, 'colspan' => 1, 'title' => null])

@php
    $icon = $icon ?: 'inbox';
@endphp

@if($table)
    <tr>
        <td colspan="{{ $colspan }}" class="p-0">
            <div class="adm-empty">
                <span class="adm-empty-ico"><x-admin.icon :name="$icon" class="h-6 w-6" /></span>
                @if($title)<p class="adm-empty-title">{{ $title }}</p>@endif
                <p class="adm-empty-text">{{ $slot }}</p>
                @isset($action)<div class="adm-empty-action">{{ $action }}</div>@endisset
            </div>
        </td>
    </tr>
@else
    <div {{ $attributes->merge(['class' => 'adm-empty']) }}>
        <span class="adm-empty-ico"><x-admin.icon :name="$icon" class="h-6 w-6" /></span>
        @if($title)<p class="adm-empty-title">{{ $title }}</p>@endif
        <p class="adm-empty-text">{{ $slot }}</p>
        @isset($action)<div class="adm-empty-action">{{ $action }}</div>@endisset
    </div>
@endif
