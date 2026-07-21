<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoiceData['invoice_number'] ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 40px;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 15px;
            line-height: 1.45;
        }
        table { width: 100%; border-collapse: collapse; }
        .header-table { border-bottom: 1px solid #e5e7eb; margin-bottom: 52px; }
        .header-table td { border: 0; vertical-align: top; padding: 0 0 26px; }
        .company-name { font-size: 26px; font-weight: 700; margin-bottom: 2px; }
        .invoice-title { font-size: 32px; color: #6b7280; font-weight: 400; }
        .muted { color: #6b7280; }
        .invoice-meta { text-align: right; white-space: nowrap; }
        .bill-to { margin-bottom: 36px; }
        .section-label { color: #6b7280; font-weight: 700; letter-spacing: .4px; }
        .bill-name { font-size: 20px; font-weight: 700; }
        .items th { background: #f3f4f6; padding: 12px; text-align: left; font-weight: 700; border: 0; }
        .items td { padding: 14px 12px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; direction: ltr; unicode-bidi: embed; }
        .summary { width: 49%; margin-left: auto; margin-top: 42px; }
        .summary td { border: 0; padding: 7px 0; }
        .summary .spacer td { border-top: 1px solid #e5e7eb; padding-top: 16px; }
        .summary .total-due td { font-size: 20px; font-weight: 700; }
        .footer { margin-top: 76px; padding-top: 30px; border-top: 1px solid #e5e7eb; text-align: center; color: #6b7280; font-size: 14px; }
        .rtl-cell { direction: rtl; unicode-bidi: embed; text-align: right; }
        .ltr-cell { direction: ltr; unicode-bidi: embed; text-align: left; }
    </style>
</head>
<body>
@php
    use App\Support\PdfText;

    $company = $invoiceData['company'] ?? [];
    $billTo = $invoiceData['bill_to'] ?? [];
    $items = collect($invoiceData['items'] ?? []);
    $companyContact = PdfText::oneLine([$company['phone'] ?? null, $company['email'] ?? null]);
@endphp

<table class="header-table">
    <tr>
        <td style="width: 56%;">
            <div class="company-name">{!! PdfText::span($company['name'] ?? 'Company') !!}</div>
            <div class="muted">{!! PdfText::span($company['address'] ?? null) !!}</div>
            <div class="muted">{!! PdfText::span($companyContact) !!}</div>
        </td>
        <td class="invoice-meta" style="width: 44%;">
            <div class="invoice-title">INVOICE</div>
            <div><strong>Invoice #:</strong> {{ $invoiceData['invoice_number'] ?? '' }}</div>
            <div><strong>Date:</strong> {{ $invoiceData['date'] ?? '' }}</div>
        </td>
    </tr>
</table>

<div class="bill-to">
    <div class="section-label">BILL TO</div>
    <div class="bill-name">{!! PdfText::span($billTo['name'] ?? '') !!}</div>
    <div>{!! PdfText::span($billTo['address'] ?? '') !!}</div>
    <div>{!! PdfText::span($billTo['phone'] ?? '') !!}</div>
</div>

<table class="items">
    <thead>
        <tr>
            <th>Description</th>
            <th class="num" style="width: 24%;">Amount</th>
        </tr>
    </thead>
    <tbody>
        @foreach($items as $item)
            <tr>
                <td class="{{ PdfText::hasArabic($item['description'] ?? '') ? 'rtl-cell' : 'ltr-cell' }}">{!! PdfText::span($item['description'] ?? '') !!}</td>
                <td class="num">{{ PdfText::money($item['amount'] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table class="summary">
    <tr class="spacer">
        <td>Total:</td>
        <td class="num">{{ PdfText::money($invoiceData['total'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Paid:</td>
        <td class="num">{{ PdfText::money($invoiceData['paid'] ?? 0) }}</td>
    </tr>
    <tr>
        <td>Remaining:</td>
        <td class="num">{{ PdfText::money($invoiceData['remaining'] ?? 0) }}</td>
    </tr>
    <tr class="total-due">
        <td>Total Due:</td>
        <td class="num">{{ PdfText::money($invoiceData['total_due'] ?? $invoiceData['remaining'] ?? 0) }}</td>
    </tr>
</table>

<div class="footer">
    <div>{{ $invoiceData['footer_message'] ?? 'Thank you for your visit!' }}</div>
    @if(!empty($invoiceData['due_date']))
        <div>Payment is due by {{ $invoiceData['due_date'] }}.</div>
    @endif
</div>
</body>
</html>
