{{--
    The text the admin panel's JavaScript shows, in the signed-in user's language, as window.RPPL_T
    (read through t() in resources/js/i18n.js). Each admin area lists the English phrases its scripts use
    in lang/admin/<area>/js.php; their Hindi is in the same area's hi.json. English pages get an
    identity map, so the script output is unchanged.
--}}
@php
    $jsStrings = collect(glob(lang_path('admin/*/js.php')) ?: [])
        ->flatMap(fn ($file) => require $file)
        ->unique()
        ->mapWithKeys(fn ($phrase) => [$phrase => __($phrase)])
        ->all();
@endphp
<script>
    window.RPPL_T = @json($jsStrings);
</script>
