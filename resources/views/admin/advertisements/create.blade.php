@extends('layouts.admin')

@section('title', 'Add Advertisement')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.advertisements.index') }}" class="text-xs text-slate-500 hover:text-slate-700">
            &larr; Back to advertisements
        </a>
    </div>

    {{-- The form on the left; the picture-size guide beside it on wide
         screens (below the form on a phone), staying in view while scrolling. --}}
    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,34rem)_minmax(0,1fr)]">
        <div class="rounded-lg border border-slate-200 bg-white p-4">
            <form method="POST" action="{{ route('admin.advertisements.store') }}" enctype="multipart/form-data" novalidate>
                @csrf

                @include('admin.advertisements._form')

                <div class="mt-2 flex items-center gap-2">
                    <x-admin.button>Save advertisement</x-admin.button>
                    <x-admin.button href="{{ route('admin.advertisements.index') }}" variant="secondary">Cancel</x-admin.button>
                </div>
            </form>
        </div>

        <aside class="lg:sticky lg:top-4 lg:max-w-xl">
            @include('admin.advertisements._size-guide', ['highlight' => 'normal-banner'])
        </aside>
    </div>
@endsection
