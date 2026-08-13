<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt {{ $financeTransaction->receipt_number }}</title>
    <style>
        body { margin: 0; background: #f3f4f6; color: #111827; font-family: Arial, sans-serif; }
        .sheet { width: min(760px, calc(100% - 32px)); margin: 32px auto; background: white; border: 1px solid #d1d5db; padding: 32px; box-sizing: border-box; }
        .header, .row { display: flex; justify-content: space-between; gap: 24px; }
        .header { border-bottom: 2px solid #111827; padding-bottom: 20px; }
        h1 { margin: 0; font-size: 26px; }
        h2 { margin: 4px 0 0; font-size: 16px; font-weight: normal; color: #4b5563; }
        .meta { margin-top: 24px; border-top: 1px solid #e5e7eb; }
        .row { padding: 12px 0; border-bottom: 1px solid #e5e7eb; }
        .label { color: #6b7280; }
        .amount { margin: 28px 0; padding: 20px; background: #ecfdf5; border: 1px solid #a7f3d0; text-align: center; }
        .amount strong { display: block; margin-top: 6px; font-size: 28px; }
        .actions { width: min(760px, calc(100% - 32px)); margin: 24px auto 0; text-align: right; }
        button { border: 0; background: #111827; color: white; padding: 10px 16px; font-weight: bold; cursor: pointer; }
        @media print { body { background: white; } .sheet { width: 100%; margin: 0; border: 0; } .actions { display: none; } }
        @media (max-width: 560px) { .sheet { margin: 16px auto; padding: 20px; } .header, .row { flex-direction: column; gap: 6px; } }
    </style>
</head>
<body>
    <div class="actions"><button type="button" onclick="window.print()">Print Receipt</button></div>
    <main class="sheet">
        <header class="header">
            <div>
                <h1>Payment Receipt</h1>
                <h2>{{ $student->university->name ?? config('app.name') }}</h2>
            </div>
            <div>
                <strong>{{ $financeTransaction->receipt_number }}</strong><br>
                <span>{{ $financeTransaction->transaction_date?->format('Y-m-d') }}</span>
            </div>
        </header>

        <div class="amount">
            <span>Amount received</span>
            <strong>{{ money($financeTransaction->amount, $financeTransaction->currency) }} {{ $financeTransaction->currency }}</strong>
        </div>

        <section class="meta">
            <div class="row"><span class="label">Student</span><strong>{{ $student->full_name }}</strong></div>
            <div class="row"><span class="label">Student ID</span><span>{{ $student->student_id }}</span></div>
            <div class="row"><span class="label">College / Department</span><span>{{ $student->department->college->name ?? '-' }} / {{ $student->department->name ?? '-' }}</span></div>
            <div class="row"><span class="label">Payment type</span><span>{{ ucfirst($financeTransaction->type) }}</span></div>
            <div class="row"><span class="label">Applied invoice</span><span>{{ $financeTransaction->invoice?->invoice_number ?? 'General account credit' }}</span></div>
            <div class="row"><span class="label">Reference</span><span>{{ $financeTransaction->reference ?: '-' }}</span></div>
            <div class="row"><span class="label">Recorded by</span><span>{{ $financeTransaction->recorder->name ?? '-' }}</span></div>
            <div class="row"><span class="label">Approved by</span><span>{{ $financeTransaction->approver->name ?? 'Posted at entry' }}</span></div>
        </section>
    </main>
</body>
</html>
