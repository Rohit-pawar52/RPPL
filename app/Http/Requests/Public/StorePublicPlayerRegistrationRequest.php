<?php

namespace App\Http\Requests\Public;

use App\Models\Player;
use App\Models\PlayerRegistration;
use App\Support\UploadLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates only the SHAPE/format of a guest submission — never
 * eligibility (open edition, identity conflicts, duplicate
 * registration), which is GuestPlayerRegistrationService's domain-layer
 * job. Deliberately has no fields for payment_status, registration_fee,
 * payment_reference, registration_number, or edition_id: those are
 * entirely server-controlled and never read from this request, even if
 * a malicious client includes them in the POST body.
 *
 * The questions are the ones the Google Form it replaces asked, made
 * stricter where that form had no checks at all: the mobile number, role,
 * hands, address, photo, UTR and payment screenshot are all required, and
 * age, mobile and UTR have to look like what they are. Aadhaar and date of
 * birth are no longer asked.
 */
class StorePublicPlayerRegistrationRequest extends FormRequest
{
    /**
     * What the form asks for per uploaded file — the Google Form it
     * replaces allowed 10 MB. The limit actually enforced is whatever this
     * server can take (see maxFileKilobytes()).
     */
    public const WANTED_FILE_KB = 10240;

    /**
     * Files in one submission (the photo and the payment screenshot).
     */
    public const FILES = 2;

    /**
     * This is the public site — no auth/policy exists for a guest.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Phone is normalized here (before the format rule runs) so the
     * validated regex applies to the same normalized form the service
     * layer will use for identity resolution/storage — matching common
     * Indian input variants (spaces, hyphens, a leading +91/91) to one
     * consistent 10-digit value. Email is trimmed/lowercased the same
     * way, and the UTR loses its spaces ("4029 1234 5678") and is
     * upper-cased so the same payment always looks the same. See
     * Player::normalizePhone()/normalizeEmail().
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'phone' => Player::normalizePhone($this->input('phone')),
            'email' => Player::normalizeEmail($this->input('email')),
            'submitted_utr' => self::normalizeUtr($this->input('submitted_utr')),
        ]);
    }

    public static function normalizeUtr(mixed $raw): ?string
    {
        $utr = strtoupper((string) preg_replace('/\s+/', '', (string) $raw));

        return $utr === '' ? null : $utr;
    }

    /**
     * The per-file limit in kilobytes: the 10 MB the form asks for, cut
     * down to what upload_max_filesize / post_max_size really allow, so the
     * hint, the browser check and this rule never promise more than the
     * server accepts.
     */
    public static function maxFileKilobytes(): int
    {
        return UploadLimits::perFileKilobytes(self::WANTED_FILE_KB, self::FILES);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxKilobytes = self::maxFileKilobytes();

        return [
            'name' => ['required', 'string', 'max:255'],
            'age' => ['required', 'integer', 'between:'.PlayerRegistration::AGE_MIN.','.PlayerRegistration::AGE_MAX],
            'phone' => ['required', 'string', 'regex:/^[6-9]\d{9}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'primary_role' => ['required', 'string', Rule::in(Player::PRIMARY_ROLES)],
            'batting_style' => ['required', 'string', Rule::in(Player::BATTING_STYLES)],
            'bowling_style' => ['required', 'string', Rule::in(Player::BOWLING_STYLES)],
            'village' => ['required', 'string', 'min:2', 'max:100'],
            'tehsil' => ['required', 'string', 'min:2', 'max:100'],
            'district' => ['required', 'string', 'min:2', 'max:100'],
            'photo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$maxKilobytes],
            // UPI transaction ids are 12 digits, a bank transfer's are
            // longer and mixed — letters and digits, with room either way.
            'submitted_utr' => ['required', 'string', 'regex:/^[A-Z0-9]{8,30}$/'],
            'payment_proof' => ['required', 'image', 'mimes:jpeg,jpg,png', 'max:'.$maxKilobytes],
        ];
    }

    /**
     * Resolved per request so the messages follow the public site
     * language (SetPublicLocale). Generic rule messages (required, max,
     * mimes, ...) come from lang/{locale}/validation.php as usual. The
     * `uploaded` lines cover a file PHP itself refused (over its own
     * size limit), which would otherwise read "failed to upload".
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $size = UploadLimits::megabytes(self::maxFileKilobytes());

        return [
            'phone.regex' => __('registration.validation.phone_regex'),
            'age.between' => __('registration.validation.age_between', ['min' => PlayerRegistration::AGE_MIN, 'max' => PlayerRegistration::AGE_MAX]),
            'submitted_utr.regex' => __('registration.validation.utr_regex'),
            'photo.max' => __('registration.validation.file_max', ['size' => $size]),
            'photo.uploaded' => __('registration.validation.file_max', ['size' => $size]),
            'payment_proof.max' => __('registration.validation.file_max', ['size' => $size]),
            'payment_proof.uploaded' => __('registration.validation.file_max', ['size' => $size]),
        ];
    }

    /**
     * Plain-language names for the new fields, in the public site
     * language (the older fields keep the names the shared validation
     * lang files already give them).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return (array) trans('registration.attributes');
    }
}
