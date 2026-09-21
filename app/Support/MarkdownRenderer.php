<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The one place Markdown (Phase 3.46 content pages) is converted to
 * HTML — centralized so the safe options below are never duplicated,
 * forgotten, or overridden by a call site.
 *
 * league/commonmark (already a transitive Laravel dependency, used
 * here via Illuminate\Support\Str::markdown()) does NOT default to
 * safe settings: `html_input` defaults to 'allow' (raw HTML passed
 * through untouched) and `allow_unsafe_links` defaults to true. Both
 * are explicitly overridden here: 'escape' turns any raw HTML in the
 * source (e.g. a stored <script> tag) into inert, visible text rather
 * than executable markup, and unsafe link schemes (javascript:, etc.)
 * are rejected. The output is safe to render with {!! !!} — this is
 * the one legitimate use of that syntax in this codebase, and only
 * ever on the return value of this method, never on raw stored input.
 */
final class MarkdownRenderer
{
    public static function toSafeHtml(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);
    }
}
