<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\OperationLog;
use Illuminate\Http\Request;

class OperationLogController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Muestra la bitácora de auditoría operativa.
     * Solo accesible para superadmin / admin.
     */
    public function index(Request $request)
    {
        $business_id = request()->session()->get('user.business_id');

        if (!auth()->user()->can('superadmin') && !auth()->user()->can('admin')) {
            abort(403, 'No autorizado');
        }

        $locations = BusinessLocation::forBusiness($business_id)
            ->pluck('name', 'id')
            ->prepend('Todas', '');

        if ($request->ajax()) {
            $query = OperationLog::forBusiness($business_id)
                ->orderByDesc('occurred_at');

            // Filtros
            if ($request->filled('module')) {
                $query->where('module', $request->module);
            }
            if ($request->filled('action')) {
                $query->where('action', $request->action);
            }
            if ($request->filled('user_name')) {
                $query->where('user_name', 'like', '%' . $request->user_name . '%');
            }
            if ($request->filled('entity_ref')) {
                $query->where('entity_ref', 'like', '%' . $request->entity_ref . '%');
            }
            if ($request->filled('start_date')) {
                $query->whereDate('occurred_at', '>=', $request->start_date);
            }
            if ($request->filled('end_date')) {
                $query->whereDate('occurred_at', '<=', $request->end_date);
            }

            $total  = $query->count();
            $length = (int) ($request->length ?? 25);
            $start  = (int) ($request->start ?? 0);

            $logs = $query->skip($start)->take($length)->get();

            $data = $logs->map(function ($log) {
                $moduleLabel = OperationLog::moduleLabel($log->module);
                $actionLabel = OperationLog::actionLabel($log->action);
                $badgeClass  = OperationLog::actionBadgeClass($log->action);

                $changes = '';
                if (!empty($log->changes)) {
                    $lines = [];
                    foreach ($log->changes as $field => $diff) {
                        $before = is_array($diff) ? ($diff['before'] ?? '') : $diff;
                        $after  = is_array($diff) ? ($diff['after']  ?? '') : $diff;
                        $lines[] = "<small><strong>{$field}:</strong> {$before} &rarr; {$after}</small>";
                    }
                    $changes = implode('<br>', $lines);
                }

                return [
                    'occurred_at'   => $log->occurred_at ? $log->occurred_at->format('d/m/Y H:i:s') : '-',
                    'module'        => "<span class=\"label label-default\">{$moduleLabel}</span>",
                    'action'        => "<span class=\"label {$badgeClass}\">{$actionLabel}</span>",
                    'entity_ref'    => e($log->entity_ref ?? '-'),
                    'location_name' => e($log->location_name ?? '-'),
                    'user_name'     => e($log->user_name ?? '-'),
                    'amount'        => $log->amount !== null
                        ? number_format($log->amount, 2) . ' ' . ($log->currency ?? '')
                        : '-',
                    'ip_address'    => e($log->ip_address ?? '-'),
                    'changes'       => $changes ?: '-',
                ];
            });

            return response()->json([
                'draw'            => (int) ($request->draw ?? 1),
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $data,
            ]);
        }

        $modules = [
            ''               => 'Todos los módulos',
            'sell'           => 'Ventas',
            'purchase'       => 'Compras',
            'payment'        => 'Pagos',
            'expense'        => 'Gastos',
            'sell_return'    => 'Dev. Ventas',
            'purchase_return' => 'Dev. Compras',
            'stock_adj'      => 'Ajuste Inventario',
        ];

        $actions = [
            ''        => 'Todas las acciones',
            'created' => 'Creado',
            'updated' => 'Modificado',
            'deleted' => 'Eliminado',
        ];

        return view('report.operation_log', compact('modules', 'actions', 'locations'));
    }

    /**
     * Exporta la auditoría operativa a CSV con los mismos filtros del index.
     */
    public function exportCsv(Request $request)
    {
        if (!auth()->user()->can('superadmin') && !auth()->user()->can('admin')) {
            abort(403, 'No autorizado');
        }

        $business_id = $request->session()->get('user.business_id');

        $query = OperationLog::forBusiness($business_id)->orderByDesc('occurred_at');

        if ($request->filled('module'))     { $query->where('module', $request->module); }
        if ($request->filled('action'))     { $query->where('action', $request->action); }
        if ($request->filled('user_name'))  { $query->where('user_name', 'like', '%' . $request->user_name . '%'); }
        if ($request->filled('entity_ref')) { $query->where('entity_ref', 'like', '%' . $request->entity_ref . '%'); }
        if ($request->filled('start_date')) { $query->whereDate('occurred_at', '>=', $request->start_date); }
        if ($request->filled('end_date'))   { $query->whereDate('occurred_at', '<=', $request->end_date); }

        $logs = $query->get();

        $start = $request->get('start_date', now()->toDateString());
        $end   = $request->get('end_date',   now()->toDateString());
        $filename = 'auditoria_' . $start . '_al_' . $end . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        return response()->stream(function () use ($logs) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, ['Fecha/Hora', 'Módulo', 'Acción', 'Referencia', 'Sede', 'Usuario', 'Monto', 'IP']);
            foreach ($logs as $log) {
                fputcsv($out, [
                    $log->occurred_at ? $log->occurred_at->format('d/m/Y H:i:s') : '-',
                    OperationLog::moduleLabel($log->module),
                    OperationLog::actionLabel($log->action),
                    $log->entity_ref ?? '-',
                    $log->location_name ?? '-',
                    $log->user_name ?? '-',
                    $log->amount !== null ? number_format($log->amount, 2) . ' ' . ($log->currency ?? '') : '-',
                    $log->ip_address ?? '-',
                ]);
            }
            fclose($out);
        }, 200, $headers);
    }
}
