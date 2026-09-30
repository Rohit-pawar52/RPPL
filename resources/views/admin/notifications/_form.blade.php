{{-- Shared by create.blade.php and edit.blade.php. $notification is null on create. --}}
@php
    $notification = $notification ?? null;
@endphp

<x-form.input name="title" label="Title" :value="$notification->title ?? ''" required autofocus maxlength="150" />

<x-form.textarea name="message" label="Message" :value="$notification->message ?? ''" rows="3" maxlength="500" />

<x-form.input name="action_url" label="Action URL" :value="$notification->action_url ?? ''" maxlength="255" placeholder="/matches/12" />
<p class="-mt-3 mb-3.5 text-[11px] text-slate-400">
    Optional internal RPPL path, e.g. /matches/12 or /player-registration. External links are not allowed.
</p>
