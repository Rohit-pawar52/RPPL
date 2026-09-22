<?php

namespace Tests\Unit\Services\Registration;

use App\Services\Registration\TransactionIdExtractor;
use Tests\TestCase;

/**
 * Phase B — pure, deterministic parsing only (no DB/Storage/Tesseract),
 * so these are plain unit tests. Covers only the high-value behaviors
 * that actually distinguish a real transaction ID from unrelated noise
 * in OCR'd payment-screenshot text — not exhaustive regex permutations.
 */
class TransactionIdExtractorTest extends TestCase
{
    /**
     * One combined test across several real-world label variants
     * (UPI/plain Transaction ID, bare UTR, UTR Number, Reference No/ID,
     * Txn ID, Transaction Number) rather than a separate test method
     * per label — the extraction rule being proven is the same in each
     * case, just anchored on a different phrase from the same label list.
     */
    public function test_it_extracts_the_value_following_a_recognized_label_across_common_variants(): void
    {
        $cases = [
            "Payment successful\nUPI Transaction ID: 402912345678\nAmount: Rs 400" => '402912345678',
            'Transaction ID 8A9F31C207' => '8A9F31C207',
            'UTR: 231500987654' => '231500987654',
            'UTR Number 998877665544' => '998877665544',
            'Reference No: REF20260115XYZ' => 'REF20260115XYZ',
            'Reference ID - 55AABBCCDD' => '55AABBCCDD',
            'Txn ID: txn12345678' => 'txn12345678',
            'Transaction Number 1122334455' => '1122334455',
        ];

        foreach ($cases as $text => $expected) {
            $this->assertSame($expected, TransactionIdExtractor::extract($text), "Failed asserting extraction for: {$text}");
        }
    }

    /**
     * The whole point of label-anchoring: an amount, a phone number, a
     * date/time, and an RPPL registration number on the same screenshot
     * must never be mistaken for the transaction ID just because they
     * are numeric and nearby — none of them follow a recognized label.
     */
    public function test_it_does_not_extract_unrelated_numbers(): void
    {
        $text = "Paid to Ramesh Joshi\nAmount: Rs 400.00\nPhone: 9876543210\nDate: 15 Jan 2026, 14:32\n"
            .'Payment for registration RPPL-2026-000125 successful.';

        $this->assertNull(TransactionIdExtractor::extract($text));
    }

    /**
     * A label immediately followed by ordinary prose (no digit) must be
     * skipped in favor of a real, later match — rather than either
     * crashing or returning the non-numeric word as if it were a value.
     */
    public function test_it_skips_a_non_numeric_match_and_finds_a_real_one_elsewhere(): void
    {
        $text = "Your transaction ID could not be displayed here.\nUTR Number: 445566778899";

        $this->assertSame('445566778899', TransactionIdExtractor::extract($text));
    }

    public function test_it_rejects_a_candidate_shorter_than_the_minimum_length(): void
    {
        $this->assertNull(TransactionIdExtractor::extract('UTR: 12345'));
    }

    public function test_it_returns_null_for_text_with_no_recognized_label_at_all(): void
    {
        $this->assertNull(TransactionIdExtractor::extract('Thank you for using PhonePe.'));
    }
}
