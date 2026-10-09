{{-- 500: a server error. Standalone - it must not need the database or the settings. --}}
@include('errors._standalone', ['code' => 500, 'icon' => 'alert'])
