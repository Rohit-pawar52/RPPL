{{--
    Applies the visitor's saved language on pages that can be answered BEFORE
    the public language middleware has run: an unknown address (404), maintenance
    mode, a server error. There the cookie has not been decrypted yet, so it is
    read and decrypted by hand. Anything missing, tampered or unknown is ignored
    and the default language stays. Prints nothing.
--}}
@php
    try {
        $savedLocale = request()->cookies->get(\App\Http\Controllers\Public\LanguageController::COOKIE_NAME);

        if (is_string($savedLocale) && $savedLocale !== '' && ! in_array($savedLocale, \App\Http\Controllers\Public\LanguageController::LOCALES, true)) {
            $savedLocale = \Illuminate\Cookie\CookieValuePrefix::remove(\Illuminate\Support\Facades\Crypt::decryptString($savedLocale));
        }

        if (is_string($savedLocale) && in_array($savedLocale, \App\Http\Controllers\Public\LanguageController::LOCALES, true)) {
            app()->setLocale($savedLocale);
        }
    } catch (\Throwable) {
        // Keep the default language.
    }
@endphp
