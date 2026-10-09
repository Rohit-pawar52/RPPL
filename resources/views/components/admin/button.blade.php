{{--
    The one admin button. Follows the colours and corner shape set in
    Settings > General (the .btn family in css/ux/kit.css).

    Props
      href      renders an <a> instead of a <button>
      variant   primary (default) | secondary | soft | ghost | danger | danger-soft
      size      sm | md (default) | lg
      icon      an <x-icon> / <x-admin.icon> name shown before the label
      type      button type when there is no href (default "submit")
      block     true = full width
      iconOnly  true = a square icon button (give it aria-label)
    Any other attribute (class, id, data-*, aria-*, form, onclick...) is passed through.
--}}
@props(['href' => null, 'variant' => 'primary', 'size' => 'md', 'icon' => null, 'type' => 'submit', 'block' => false, 'iconOnly' => false])

@php
    $variants = [
        'primary' => 'btn-primary',
        'secondary' => 'btn-secondary',
        'soft' => 'btn-soft',
        'ghost' => 'btn-ghost',
        'danger' => 'btn-danger',
        'danger-soft' => 'btn-danger-soft',
    ];
    $sizes = ['sm' => 'btn-sm', 'md' => '', 'lg' => 'btn-lg'];

    $classes = trim('btn shrink-0 '
        . ($variants[$variant] ?? $variants['primary']) . ' '
        . ($sizes[$size] ?? '') . ' '
        . ($block ? 'btn-block ' : '')
        . ($iconOnly ? 'btn-icon' : ''));
    $iconClass = $size === 'sm' || $iconOnly ? 'h-4 w-4 shrink-0' : 'h-[18px] w-[18px] shrink-0';
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-admin.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if($icon)<x-admin.icon :name="$icon" :class="$iconClass" />@endif
        {{ $slot }}
    </button>
@endif
