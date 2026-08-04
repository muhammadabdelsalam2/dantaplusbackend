<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Radiology Report {{ $report['reference_code'] ?? '' }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 36px 44px;
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 13px;
            line-height: 1.5;
        }

        /* ── Header ── */
        .header {
            border-bottom: 3px solid #0369a1;
            padding-bottom: 18px;
            margin-bottom: 24px;
        }
        .header-inner { width: 100%; }
        .header-inner td { border: 0; vertical-align: middle; padding: 0; }
        .clinic-name {
            font-size: 22px;
            font-weight: 700;
            color: #0369a1;
            margin-bottom: 2px;
        }
        .clinic-dept {
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .6px;
        }
        .report-title-block { text-align: right; }
        .report-title {
            font-size: 20px;
            font-weight: 700;
            color: #111827;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .report-ref {
            font-size: 11px;
            color: #6b7280;
            margin-top: 4px;
        }

        /* ── Info grid ── */
        .info-grid {
            width: 100%;
            margin-bottom: 22px;
            border-collapse: collapse;
        }
        .info-grid td {
            border: 0;
            vertical-align: top;
            padding: 0 8px 0 0;
            width: 50%;
        }
        .info-box {
            background: #f0f9ff;
            border-left: 4px solid #0369a1;
            border-radius: 4px;
            padding: 10px 14px;
        }
        .info-box .label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
            font-weight: 700;
        }
        .info-box .row { margin-bottom: 3px; }
        .info-box .key { color: #374151; font-weight: 700; }
        .info-box .val { color: #1f2937; }

        /* ── Section headers ── */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .6px;
            color: #0369a1;
            border-bottom: 1px solid #bfdbfe;
            padding-bottom: 5px;
            margin: 20px 0 10px;
        }

        /* ── Findings / Diagnosis ── */
        .content-box {
            background: #fafafa;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 12px 16px;
            min-height: 48px;
            color: #374151;
            margin-bottom: 14px;
            white-space: pre-wrap;
        }

        /* ── Images ── */
        .images-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        .images-table td { border: 0; vertical-align: top; padding: 0 8px 0 0; width: 50%; }
        .img-wrap {
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 8px;
            text-align: center;
        }
        .img-wrap img {
            max-width: 100%;
            max-height: 200px;
            display: block;
            margin: 0 auto 6px;
        }
        .img-label {
            font-size: 10px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .img-placeholder {
            height: 160px;
            background: #f3f4f6;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #9ca3af;
            font-size: 11px;
        }

        /* ── Signature & QR ── */
        .footer-table { width: 100%; border-collapse: collapse; margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 16px; }
        .footer-table td { border: 0; vertical-align: top; padding: 0; }
        .signature-block { padding-top: 16px; }
        .sig-line {
            border-bottom: 1px solid #374151;
            width: 200px;
            margin-bottom: 6px;
        }
        .sig-label { font-size: 11px; color: #6b7280; }
        .sig-name { font-size: 13px; font-weight: 700; color: #111827; margin-top: 2px; }
        .qr-block { text-align: right; padding-top: 16px; }
        .qr-text { font-size: 10px; color: #9ca3af; margin-top: 4px; }

        .generated-at {
            text-align: center;
            font-size: 10px;
            color: #9ca3af;
            margin-top: 18px;
            padding-top: 10px;
            border-top: 1px solid #f3f4f6;
        }

        .badge {
            display: inline-block;
            background: #dbeafe;
            color: #1e40af;
            font-size: 10px;
            border-radius: 999px;
            padding: 2px 8px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .4px;
        }
    </style>
</head>
<body>

@php
    $clinic   = $report['clinic'] ?? [];
    $patient  = $report['patient_information'] ?? [];
    $clinician= $report['ordering_clinician'] ?? [];
    $sig      = $report['electronic_signature'] ?? [];
    $images   = $report['images'] ?? [];
@endphp

{{-- ── HEADER ── --}}
<div class="header">
    <table class="header-inner">
        <tr>
            <td style="width:60%;">
                <div class="clinic-name">{{ $clinic['name'] ?? 'Dental Clinic' }}</div>
                <div class="clinic-dept">{{ $clinic['department'] ?? 'Radiology Department' }}</div>
            </td>
            <td class="report-title-block" style="width:40%;">
                <div class="report-title">Radiology Report</div>
                <div class="report-ref">Ref: {{ $report['reference_code'] ?? '—' }}</div>
                <div class="report-ref" style="margin-top:2px;">
                    Format: <span class="badge">{{ str_replace('_', ' ', $report['report_format'] ?? 'clinical summary') }}</span>
                </div>
            </td>
        </tr>
    </table>
</div>

{{-- ── PATIENT / CLINICIAN INFO ── --}}
<table class="info-grid">
    <tr>
        <td>
            <div class="info-box">
                <div class="label">Patient Information</div>
                <div class="row">
                    <span class="key">Name:</span>
                    <span class="val">{{ $patient['name'] ?? '—' }}</span>
                </div>
                <div class="row">
                    <span class="key">File ID:</span>
                    <span class="val">{{ $patient['file_id'] ?? '—' }}</span>
                </div>
                <div class="row">
                    <span class="key">Gender:</span>
                    <span class="val">{{ ucfirst($patient['gender'] ?? '—') }}</span>
                </div>
                <div class="row">
                    <span class="key">Age:</span>
                    <span class="val">{{ $patient['age'] ?? '—' }}</span>
                </div>
            </div>
        </td>
        <td>
            <div class="info-box">
                <div class="label">Ordering Clinician</div>
                <div class="row">
                    <span class="key">Name:</span>
                    <span class="val">{{ $clinician['name'] ?? '—' }}</span>
                </div>
                <div class="row">
                    <span class="key">Department:</span>
                    <span class="val">{{ $clinician['department'] ?? '—' }}</span>
                </div>
                <div class="row">
                    <span class="key">Record Date:</span>
                    <span class="val">{{ $report['created_at'] ? \Carbon\Carbon::parse($report['created_at'])->format('d M Y') : '—' }}</span>
                </div>
            </div>
        </td>
    </tr>
</table>

{{-- ── BEFORE / AFTER IMAGES ── --}}
@if(!empty($images['before_image_url']) || !empty($images['after_image_url']))
<div class="section-title">Radiographic Images</div>
<table class="images-table">
    <tr>
        <td>
            <div class="img-wrap">
                @if(!empty($images['before_image_url']))
                    <img src="{{ $images['before_image_url'] }}" alt="Before">
                @else
                    <div class="img-placeholder">No image</div>
                @endif
                <div class="img-label">Before</div>
            </div>
        </td>
        <td>
            <div class="img-wrap">
                @if(!empty($images['after_image_url']))
                    <img src="{{ $images['after_image_url'] }}" alt="After">
                @else
                    <div class="img-placeholder">No image</div>
                @endif
                <div class="img-label">After</div>
            </div>
        </td>
    </tr>
</table>
@endif

{{-- ── FINDINGS ── --}}
<div class="section-title">Findings</div>
<div class="content-box">{{ $report['findings'] ?? 'No findings recorded.' }}</div>

{{-- ── DIAGNOSIS ── --}}
<div class="section-title">Diagnosis</div>
<div class="content-box">{{ $report['diagnosis'] ?? 'No diagnosis recorded.' }}</div>

{{-- ── SIGNATURE & QR ── --}}
<table class="footer-table">
    <tr>
        <td style="width:55%;">
            <div class="signature-block">
                <div class="sig-line"></div>
                <div class="sig-name">{{ $sig['doctor_name'] ?? '—' }}</div>
                <div class="sig-label">
                    Electronic Signature
                    @if(!empty($sig['signed_at']))
                        &nbsp;·&nbsp; {{ \Carbon\Carbon::parse($sig['signed_at'])->format('d M Y, H:i') }}
                    @endif
                </div>
            </div>
        </td>
        <td style="width:45%;">
            {{-- QR code: use simplesoftwareio/simple-qrcode if installed, else show URL text --}}
            @if(!empty($report['qr_code_data']))
                <div class="qr-block">
                    @php
                        $qrAvailable = class_exists(\SimpleSoftwareIO\QrCode\Facades\QrCode::class);
                    @endphp
                    @if($qrAvailable)
                        {!! \SimpleSoftwareIO\QrCode\Facades\QrCode::size(80)->generate($report['qr_code_data']) !!}
                    @else
                        {{-- TODO: install simplesoftwareio/simple-qrcode for QR rendering --}}
                        <div style="font-size:9px; color:#9ca3af; word-break:break-all;">
                            Verify: {{ $report['qr_code_data'] }}
                        </div>
                    @endif
                    <div class="qr-text">Scan to verify report</div>
                </div>
            @endif
        </td>
    </tr>
</table>

<div class="generated-at">
    Generated on {{ now()->format('d M Y, H:i') }} &nbsp;·&nbsp; DentaPlus Radiology System
</div>

</body>
</html>
