{{-- 503: the application is down (e.g. php artisan down). Standalone - no database. --}}
@include('errors._standalone', ['code' => 503, 'icon' => 'wrench'])
