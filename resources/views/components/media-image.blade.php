{{--
    A picture from storage that never shows as broken: when there is no path,
    the file is missing, or it fails to load in the browser, the default
    picture appears instead (see App\Support\Media and
    layouts/partials/image-fallback.blade.php).
        <x-media-image :path="$player->photo_path" kind="user" alt="" class="h-full w-full object-cover" />
    Give :url instead of :path for a picture that does not come from the
    public disk (a signed or private route, an already built URL). kind is
    "image" (default.png) or "user" (default-user.jpeg, for a person).
    Any other attribute (class, loading, ...) goes straight onto the picture.
    Use an empty alt for an avatar or logo that sits beside the name it shows.
--}}
@props(['path' => null, 'url' => null, 'kind' => 'image', 'alt' => ''])

<img src="{{ $url ?: media_url($path, $kind) }}" alt="{{ $alt }}" data-fallback="{{ $kind }}" {{ $attributes }} />
