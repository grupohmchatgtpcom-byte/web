<?php

namespace App\Observers;

use App\OperationLog;
use App\TransactionPayment;

class TransactionPaymentObserver
{
    public function created(TransactionPayment $payment): void
    {
        $transaction = $payment->transaction;
        $ref = $transaction ? ($transaction->invoice_no ?? $transaction->ref_no) : null;

        OperationLog::record(
            'payment',
            'created',
            $payment,
            [],
            $ref ? "Pago/{$ref}" : "Pago #{$payment->id}",
            $payment->amount ?? null,
            $payment->payment_for === 'sell' ? 'USD' : 'USD'
        );
    }

    public function deleted(TransactionPayment $payment): void
    {
        $transaction = $payment->transaction;
        $ref = $transaction ? ($transaction->invoice_no ?? $transaction->ref_no) : null;

        OperationLog::record(
            'payment',
            'deleted',
            $payment,
            [],
            $ref ? "Pago/{$ref}" : "Pago #{$payment->id}",
            $payment->amount ?? null,
            'USD'
        );
    }
}
