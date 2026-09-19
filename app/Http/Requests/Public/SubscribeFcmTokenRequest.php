<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates only the SHAPE of a token (a non-empty string within a
 * generous length bound) — FCM registration tokens are opaque, and
 * their internal format is not part of any stable public contract, so
 * this deliberately never pattern-matches one. 512 covers real-world FCM
 * tokens (typically ~140-200 characters) with generous headroom, while
 * still bounding the column/unique-index size (see the fcm_tokens
 * migration).
 */
class SubscribeFcmTokenRequest extends FormRequest
{
    /**
     * This is the public site — no auth/policy exists for a guest.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
