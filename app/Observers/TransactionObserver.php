<?php

namespace App\Observers;

use App\OperationLog;
use App\Transaction;

class TransactionObserver
{
    // Módulos relevantes a auditar
    private const AUDITED_TYPES = [
        'sell', 'purchase', 'expense', 'sell_return', 'purchase_return',
        'stock_adjustment', 'opening_stock',
    ];

    public function created(Transaction $transaction): void
    {
        if (!in_array($transaction->type, self::AUDITED_TYPES)) {
            return;
        }

        $module = $this->mapModule($transaction->type);
        OperationLog::record(
            $module,
            'created',
            $transaction,
            [],
            $transaction->invoice_no ?? $transaction->ref_no,
            $transaction->final_total ?? null,
            'USD'
        );
    }

    public function updated(Transaction $transaction): void
    {
        if (!in_array($transaction->type, self::AUDITED_TYPES)) {
            return;
        }

        $dirty = $transaction->getDirty();
        // Eliminar campos de timestamps que no son relevantes
        unset($dirty['updated_at']);

        if (empty($dirty)) {
            return;
        }

        $changes = [];
        foreach ($dirty as $field => $newValue) {
            $changes[$field] = [
                'before' => $transaction->getOriginal($field),
                'after'  => $newValue,
            ];
        }

        $module = $this->mapModule($transaction->type);
        OperationLog::record(
            $module,
            'updated',
            $transaction,
            $changes,
            $transaction->invoice_no ?? $transaction->ref_no,
            $transaction->final_total ?? null,
            'USD'
        );
    }

    public function deleted(Transaction $transaction): void
    {
        if (!in_array($transaction->type, self::AUDITED_TYPES)) {
            return;
        }

        $module = $this->mapModule($transaction->type);
        OperationLog::record(
            $module,
            'deleted',
            $transaction,
            [],
            $transaction->invoice_no ?? $transaction->ref_no,
            $transaction->final_total ?? null,
            'USD'
        );
    }

    private function mapModule(string $type): string
    {
        return match ($type) {
            'sell'          => 'sell',
            'purchase'      => 'purchase',
            'expense'       => 'expense',
            'sell_return'   => 'sell_return',
            'purchase_return' => 'purchase_return',
            default         => $type,
        };
    }
}
