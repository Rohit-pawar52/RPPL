{{--
    The header of a detail ("show") page: a banner, the picture, the name with
    its status, and quick actions on the right.

        <x-crud.profile :title="$player->name" :path="$player->photo_path" kind="user"
            :status="$player->is_active ? 'active' : 'inactive'" subtitle="Batter · Right hand">
            <x-slot:actions> ...buttons... </x-slot:actions>
            ...chips under the name (default slot)
        </x-crud.profile>

    icon   shows a big icon instead of a picture (venues, notifications ...)
    shape  circle (people) | square (logos, places)
--}}
@props(['title', 'subtitle' => null, 'path' => null, 'url' => null, 'kind' => 'user', 'shape' => 'circle', 'icon' => null, 'status' => null])

<section class="crud-profile">
    <div class="crud-profile-band" aria-hidden="true"></div>

    <div class="crud-profile-body">
        <div class="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-end sm:gap-4">
            <div @class(['crud-profile-avatar', 'crud-profile-avatar-square' => $shape !== 'circle' || $icon])>
                @if($icon)
                    <span class="flex h-24 w-24 items-center justify-center rounded-xl bg-brand-soft text-brand">
                        @if(in_array($icon, ['income', 'expense', 'receipt', 'clock', 'send', 'image', 'film', 'link', 'lock'], true))
                            <x-crud.glyph :name="$icon" class="h-10 w-10" />
                        @else
                            <x-icon :name="$icon" class="h-10 w-10" />
                        @endif
                    </span>
                @else
                    <x-crud.thumb :path="$path" :url="$url" :kind="$kind" :shape="$shape" size="xl" class="border-0" />
                @endif
            </div>

            <div class="min-w-0 pb-1">
                <div class="flex flex-wrap items-center gap-x-2.5 gap-y-1">
                    <h2 class="crud-profile-name">{{ $title }}</h2>
                    @if($status)
                        <x-status-badge :status="$status" />
                    @endif
                </div>
                @if($subtitle)
                    <p class="mt-1 text-sm text-slate-500">{{ $subtitle }}</p>
                @endif
                @if(trim((string) $slot) !== '')
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">{{ $slot }}</div>
                @endif
            </div>
        </div>

        @isset($actions)
            <div class="crud-profile-actions">{{ $actions }}</div>
        @endisset
    </div>
</section>
