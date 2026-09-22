{{--
    The per-contribution receipt markup — extracted verbatim from
    receipt.blade.php so the single-receipt PDF/preview and the
    receipts-batch (bulk) PDF render the exact same block per
    contribution. Expects $contribution in scope.
--}}
<div class="receipt">
    <div class="header">
        <div class="brand">{{ $branding->applicationName }}</div>
        <div class="subtitle">Contribution Receipt</div>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Receipt No.</td>
            <td class="value">{{ $contribution->receiptReference() }}</td>
        </tr>
        <tr>
            <td class="label">Date</td>
            <td class="value">{{ $contribution->contributed_at->format('d M Y') }}</td>
        </tr>
    </table>

    <table class="details">
        <tr>
            <td class="label">Received From</td>
            <td class="value">{{ $contribution->contributorName() }}</td>
        </tr>
        <tr>
            <td class="label">Edition</td>
            <td class="value">{{ $contribution->edition->name }}</td>
        </tr>
    </table>

    <div class="amount-box">
        <div class="label">Contribution Amount</div>
        <div class="value">{{ money($contribution->amount) }}</div>
    </div>

    <div class="footer">
        <p>Thank you for your contribution to {{ $branding->shortName }}.</p>
        <p>This is a computer-generated receipt.</p>
    </div>
</div>
