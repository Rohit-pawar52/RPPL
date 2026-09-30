@extends('layouts.public')

@section('title', __('directory.photos.title').' · '.$branding->shortName)

@section('content')
    <div class="mb-4">
        <h1 class="text-base font-semibold text-neutral-900">{{ __('directory.photos.title') }}</h1>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            @forelse($photos as $photo)
                @php $photoUrl = Illuminate\Support\Facades\Storage::url($photo->photo_path); @endphp
                <figure class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    {{-- A plain link to the full image; the script below upgrades
                         it to an in-page viewer, so it still works without JS. --}}
                    <a
                        href="{{ $photoUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="rppl-photo-link block aspect-video w-full bg-neutral-100"
                        data-title="{{ $photo->title }}"
                    >
                        <img
                            src="{{ $photoUrl }}"
                            alt="{{ $photo->title }}"
                            loading="lazy"
                            class="h-full w-full object-cover"
                            onerror="this.style.visibility='hidden'"
                        />
                    </a>
                    <figcaption class="p-2.5">
                        <h3 class="truncate text-[13px] font-medium text-neutral-800">{{ $photo->title }}</h3>
                        @if($photo->description)
                            <p class="mt-0.5 line-clamp-2 text-[11px] text-neutral-500">{{ $photo->description }}</p>
                        @endif
                    </figcaption>
                </figure>
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">{{ __('directory.photos.empty') }}</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $photos->links() }}
        </div>
    </div>

    <dialog id="rppl-photo-dialog" style="max-width:94vw;max-height:94vh;padding:0.75rem;border:0;border-radius:0.5rem;">
        <img id="rppl-photo-dialog-image" alt="" style="display:block;max-width:100%;max-height:80vh;margin:0 auto;" />
        <p id="rppl-photo-dialog-title" class="mt-2 text-center text-[13px] font-medium text-neutral-800"></p>
        <form method="dialog" class="mt-2 text-center">
            <button type="submit" class="rounded-md border border-neutral-300 px-3 py-1 text-[12px] font-medium text-neutral-600">{{ __('directory.photos.close') }}</button>
        </form>
    </dialog>

    <script>
        (function () {
            var dialog = document.getElementById('rppl-photo-dialog');
            if (! dialog || typeof dialog.showModal !== 'function') {
                return;
            }
            document.querySelectorAll('a.rppl-photo-link').forEach(function (link) {
                link.addEventListener('click', function (event) {
                    event.preventDefault();
                    document.getElementById('rppl-photo-dialog-image').src = link.href;
                    document.getElementById('rppl-photo-dialog-title').textContent = link.dataset.title || '';
                    dialog.showModal();
                });
            });
            // Clicking the dimmed area outside the image closes it.
            dialog.addEventListener('click', function (event) {
                if (event.target === dialog) {
                    dialog.close();
                }
            });
        })();
    </script>
@endsection
