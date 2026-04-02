<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OperationLog extends Model
{
    public $timestamps = false;

    protected $table = 'operation_logs';

    protected $guarded = ['id'];

    protected $casts = [
        'changes'     => 'array',
        'occurred_at' => 'datetime',
    ];

    // -------------------------------------------------------
    // Helper: registra un evento de auditoría
    // -------------------------------------------------------
    public static function record(string $module, string $action, $entity, array $changes = [], ?string $entityRef = null, ?float $amount = null, ?string $currency = null): void
    {
        try {
            $businessId = session('user.business_id');
            if (empty($businessId)) {
                return;
            }

            $user     = auth()->user();
            $location = null;

            // Intentar obtener la sede del modelo
            if ($entity && method_exists($entity, 'location') && !empty($entity->location_id)) {
                $location = BusinessLocation::find($entity->location_id);
            }

            static::create([
                'business_id'   => $businessId,
                'user_id'       => $user ? $user->id : null,
                'user_name'     => $user ? $user->first_name . ' ' . $user->last_name : null,
                'module'        => $module,
                'action'        => $action,
                'entity_id'     => $entity ? $entity->id : null,
                'entity_ref'    => $entityRef ?? ($entity ? ($entity->ref_no ?? $entity->invoice_no ?? null) : null),
                'location_name' => $location ? $location->name : null,
                'amount'        => $amount,
                'currency'      => $currency,
                'changes'       => empty($changes) ? null : $changes,
                'ip_address'    => request()->ip(),
                'occurred_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            // La auditoría nunca debe bloquear operaciones de negocio
            \Log::warning('OperationLog::record failed: ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------
    // Scope helpers
    // -------------------------------------------------------
    public function scopeForBusiness($query, int $businessId)
    {
        return $query->where('business_id', $businessId);
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    // -------------------------------------------------------
    // Labels helpers
    // -------------------------------------------------------
    public static function moduleLabel(string $module): string
    {
        return [
            'sell'          => 'Venta',
            'purchase'      => 'Compra',
            'payment'       => 'Pago',
            'cash_register' => 'Caja',
            'expense'       => 'Gasto',
            'sell_return'   => 'Dev. Venta',
            'purchase_return' => 'Dev. Compra',
            'stock_adj'     => 'Ajuste Inv.',
        ][$module] ?? ucfirst($module);
    }

    public static function actionLabel(string $action): string
    {
        return [
            'created' => 'Creado',
            'updated' => 'Modificado',
            'deleted' => 'Eliminado',
        ][$action] ?? ucfirst($action);
    }

    public static function actionBadgeClass(string $action): string
    {
        return [
            'created' => 'label-success',
            'updated' => 'label-warning',
            'deleted' => 'label-danger',
        ][$action] ?? 'label-default';
    }
}
