{{--
    The Save / Cancel row at the end of a form.

    Props
      sticky   true (default) = pinned to the bottom of the screen while the form is taller
               than the window, so Save is never a long scroll away. Place it at the END of
               the <form>, OUTSIDE any card. false = an ordinary row.
    Slot: the buttons, e.g.
      <x-admin.form-actions>
          <x-admin.button>Save</x-admin.button>
          <x-admin.button :href="route('...')" variant="secondary">Cancel</x-admin.button>
      </x-admin.form-actions>
--}}
@props(['sticky' => true])

<div {{ $attributes->class(['adm-actionbar' => $sticky, 'adm-actionbar-inline' => ! $sticky, 'no-print']) }}>
    {{ $slot }}
</div>
