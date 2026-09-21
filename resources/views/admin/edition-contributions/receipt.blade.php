<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $contribution->receiptReference() }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 13px;
            margin: 0;
            padding: 32px;
        }
        .receipt {
            max-width: 560px;
            margin: 0 auto;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            padding: 24px;
        }
        .header {
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 16px;
            margin-bottom: 16px;
        }
        .header .brand {
            font-size: 18px;
            font-weight: bold;
            color: #1d4ed8;
        }
        .header .subtitle {
            font-size: 12px;
            color: #6b7280;
            margin-top: 2px;
        }
        .meta {
            width: 100%;
            margin-bottom: 16px;
        }
        .meta td {
            padding: 2px 0;
            font-size: 12px;
        }
        .meta td.label {
            color: #6b7280;
            width: 120px;
        }
        .meta td.value {
            color: #111827;
            font-weight: bold;
        }
        .details {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .details td {
            padding: 8px 0;
            border-bottom: 1px solid #f3f4f6;
            font-size: 12px;
        }
        .details td.label {
            color: #6b7280;
        }
        .details td.value {
            text-align: right;
            color: #111827;
            font-weight: bold;
        }
        .amount-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
            padding: 12px 16px;
            text-align: center;
            margin-bottom: 20px;
        }
        .amount-box .label {
            font-size: 11px;
            color: #1d4ed8;
            text-transform: uppercase;
        }
        .amount-box .value {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 4px;
        }
        .footer {
            text-align: center;
            font-size: 11px;
            color: #9ca3af;
            margin-top: 20px;
        }
        .footer p {
            margin: 2px 0;
        }
    </style>
</head>
<body>
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
            <div class="value">&#8377;{{ number_format($contribution->amount, 2) }}</div>
        </div>

        <div class="footer">
            <p>Thank you for your contribution to {{ $branding->shortName }}.</p>
            <p>This is a computer-generated receipt.</p>
        </div>
    </div>
</body>
</html>
