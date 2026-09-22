<?php

namespace App\Services\Registration;

/**
 * Pulls a transaction/reference ID out of OCR'd payment-screenshot text
 * (Phase B — payment proof OCR assistance). Pure and deterministic: no
 * DB, no Storage, no Tesseract, no logging — just string parsing, so it
 * is trivially unit-testable without any I/O.
 *
 * Deliberately conservative: rather than grabbing the first/longest
 * number anywhere in the text (which would just as happily match an
 * amount, a phone number, a date, or an RPPL registration number), this
 * only captures a value that immediately follows one of a fixed, known
 * set of COMPLETE transaction/reference label phrases commonly seen on
 * UPI/payment confirmation screenshots. No match is a completely
 * normal, expected outcome — see PaymentProofOcrService. This is never
 * authoritative; it only feeds an admin-reviewed suggestion.
 */
class TransactionIdExtractor
{
    /**
     * Complete label phrases only — not a word1 x word2 cross-product.
     * A bare "Reference"/"Transaction"/"Txn"/"UTR" alone is NOT a
     * label here on purpose: matching on a bare word is exactly what
     * causes false positives in unrelated prose (e.g. "your transaction
     * is being processed").
     *
     * Longer/more-specific phrases are listed before shorter ones that
     * are textual prefixes of them (e.g. "UTR Number" before bare
     * "UTR", "Transaction Number" before "Transaction No") — PCRE tries
     * alternatives in order, so this ensures e.g. "UTR Number: 123..."
     * matches the whole label "UTR Number" rather than matching just
     * "UTR" and then mistakenly trying to capture "Number" as the
     * value.
     */
    private const LABELS = [
        'UPI\s+Transaction\s+ID',
        'UPI\s+Transaction\s+Id',
        'Transaction\s+Number',
        'Transaction\s+Reference',
        'Transaction\s+No',
        'Transaction\s+ID',
        'Transaction\s+Id',
        'Reference\s+Number',
        'Reference\s+No',
        'Reference\s+ID',
        'Ref\s+Number',
        'Ref\s+No',
        'UTR\s+Number',
        'UTR\s+No',
        'UTR',
        'Txn\s+ID',
        'Txn\s+Id',
    ];

    public static function extract(string $ocrText): ?string
    {
        $normalized = self::normalize($ocrText);

        $labels = implode('|', self::LABELS);
        $pattern = '/\b(?:'.$labels.')\b\.?[\s:\-]{0,5}([A-Za-z0-9]{6,30})\b/i';

        if (preg_match_all($pattern, $normalized, $matches) === 0) {
            return null;
        }

        // Check every label occurrence, not just the first: a
        // screenshot can genuinely contain a label word more than once
        // (e.g. explanatory UI text alongside the real field), and the
        // first occurrence isn't necessarily the real one.
        foreach ($matches[1] as $candidate) {
            // A real transaction/reference ID always contains at least
            // one digit; an English word following a label by
            // coincidence never does. This is what actually
            // distinguishes a real value from prose that happens to
            // contain a label phrase.
            if (preg_match('/\d/', $candidate) === 1) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * OCR output is noisy: inconsistent whitespace/line breaks around
     * otherwise-clean text. Collapsing whitespace is enough for the
     * regex above (\s already spans line breaks), without trying to
     * "fix" the OCR text itself.
     */
    private static function normalize(string $text): string
    {
        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }
}
