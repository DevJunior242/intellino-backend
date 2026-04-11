<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Facture_{{ $payment->id }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            color: #333;
            font-size: 13px;
            line-height: 1.5;
        }

        .invoice-box {
            max-width: 800px;
            margin: auto;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            border-bottom: 2px solid #eee;
            padding-bottom: 20px;
        }

        .company-info {
            float: left;
        }

        .invoice-info {
            float: right;
            text-align: right;
        }

        .clear {
            clear: both;
        }

        .billing-to {
            margin-top: 30px;
            margin-bottom: 40px;
        }

        table {
            width: 100%;
            line-height: inherit;
            text-align: left;
            border-collapse: collapse;
        }

        table th {
            background: #f8f9fa;
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        table td {
            padding: 12px;
            border: 1px solid #dee2e6;
        }

        .total {
            font-weight: bold;
            font-size: 16px;
            background: #eee;
        }

        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #777;
            border-top: 1px solid #eee;
            padding-top: 20px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 4px;
            background: #d4edda;
            color: #155724;
            font-weight: bold;
        }
    </style>
</head>

<body>
    <div class="invoice-box">
        <div class="header">
            <div class="company-info">
                <h1 style="margin: 0; color: #1976d2;">{{ $payment?->club?->name }}</h1>
                <p>Reçu de paiement officiel</p>
            </div>
            <div class="invoice-info">
                <strong>Référence :</strong> #{{ substr($payment->id, 0, 8) }}<br>
                <strong>Date :</strong> {{ $payment?->created_at?->format('d/m/Y') ?? '-' }}<br>
                <span class="badge">PAYÉ</span>
            </div>
            <div class="clear"></div>
        </div>

        <div class="billing-to">
            <table style="border: none;">
                <tr style="border: none;">
                    <td style="border: none; width: 50%; padding: 0;">
                        <h3 style="margin-bottom: 5px;">Émetteur :</h3>
                        <strong>{{ $payment->club->name }}</strong><br>
                        Contact Admin : {{ $payment?->recordedBy?->fullname  ?? 'admin' }}
                    </td>
                    <td style="border: none; width: 50%; padding: 0;">
                        <h3 style="margin-bottom: 5px;">Destinataire :</h3>
                        <strong>{{ $payment?->student?->fullname }}</strong><br>
                        ID Élève : {{ substr($payment?->student_id, 0, 8) }}
                    </td>
                </tr>
            </table>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Période de validité</th>
                    <th style="text-align: right;">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>{{ $payment?->pricingPlan?->label }}</strong><br>
                        <small>Type : {{ $payment?->pricingPlan?->paymentCategory?->name }}</small>
                    </td>
                    <td>
                        @if($payment->ends_at)
                        Du {{ \Carbon\Carbon::parse($payment->starts_at)->format('d/m/Y') }}<br>
                        au {{ \Carbon\Carbon::parse($payment->ends_at)->format('d/m/Y') }}
                        @else
                        Frais ponctuels / Inscription
                        @endif
                    </td>
                    <td style="text-align: right;">{{ number_format($payment->amount_paid, 2, ',', ' ') }} XOF</td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align: right;"><strong>TOTAL</strong></td>
                    <td style="text-align: right;" class="total">{{ number_format($payment->amount_paid, 2, ',', ' ') }}
                        XOF</td>
                </tr>
            </tbody>
        </table>

        <div class="footer">
            <p>Mode de paiement : <strong>{{ strtoupper($payment->payment_method) }}</strong></p>
            @if($payment->notes)
            <p><em>Note : {{ $payment->notes }}</em></p>
            @endif
            <p>Document généré électroniquement le {{ now()->format('d/m/Y H:i') }}.<br>
                Merci pour votre confiance au club <strong>{{ $payment?->club?->name }}</strong>.</p>
        </div>
    </div>
</body>

</html>