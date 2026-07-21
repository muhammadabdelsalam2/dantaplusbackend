@php
    $invoice->loadMissing(['clinic', 'patient.user', 'items']);
    $invoiceData = [
        'invoice_number' => $invoice->invoice_number,
        'date' => optional($invoice->issued_at)->format('m/d/Y'),
        'due_date' => optional($invoice->due_date)->format('m/d/Y'),
        'company' => [
            'name' => $invoice->clinic?->name,
            'address' => $invoice->clinic?->address,
            'phone' => $invoice->clinic?->phone,
            'email' => $invoice->clinic?->email,
        ],
        'bill_to' => [
            'name' => $invoice->patient?->user?->name,
            'address' => $invoice->patient?->address,
            'phone' => $invoice->patient?->phone,
        ],
        'items' => $invoice->items->isNotEmpty()
            ? $invoice->items->map(fn ($item) => [
                'description' => $item->description,
                'amount' => $item->amount,
            ])->values()->all()
            : [[
                'description' => $invoice->notes ?: 'Dental services',
                'amount' => $invoice->total,
            ]],
        'total' => (float) $invoice->total,
        'paid' => (float) $invoice->paid,
        'remaining' => (float) $invoice->remaining,
        'total_due' => (float) $invoice->remaining,
        'footer_message' => 'Thank you for your visit!',
    ];
@endphp

@include('pdf.simple-invoice', ['invoiceData' => $invoiceData])
