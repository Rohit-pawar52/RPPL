{{-- Shared by create.blade.php and edit.blade.php. $notification is null on create. --}}
@php
    $notification = $notification ?? null;
@endphp

<x-form.input name="title" label="Title" :value="$notification->title ?? ''" required autofocus maxlength="150" />

<div class="mb-3.5">
    <label for="message" class="mb-1 block text-xs font-medium text-neutral-700">Message</label>
    <textarea
        id="message"
        name="message"
        rows="3"
        maxlength="500"
        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('message') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 focus:ring-blue-100' }}"
    >{{ old('message', $notification->message ?? '') }}</textarea>
    @error('message')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

<x-form.input name="action_url" label="Action URL" :value="$notification->action_url ?? ''" maxlength="255" placeholder="/matches/12" />
<p class="-mt-3 mb-3.5 text-[11px] text-neutral-400">
    Optional internal RPPL path, e.g. /matches/12 or /player-registration. External links are not allowed.
</p>
