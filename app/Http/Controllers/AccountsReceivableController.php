<?php

namespace App\Http\Controllers;

use App\Contact;
use App\Transaction;
use App\Utils\TransactionUtil;
use DB;
use Illuminate\Http\Request;

class AccountsReceivableController extends Controller
{
    protected $transactionUtil;

    public function __construct(TransactionUtil $transactionUtil)
    {
        $this->transactionUtil = $transactionUtil;
    }

    /**
     * Reporte de Cuentas por Cobrar agrupado por cliente.
     * GET /reports/accounts-receivable
     */
    public function index(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');

        if (request()->ajax()) {
            return $this->getDataTable($request, $business_id);
        }

        $customers = Contact::where('business_id', $business_id)
            ->where('type', 'customer')
            ->orderBy('name')
            ->pluck('name', 'id');

        $bcv_rate = getActiveBcvRate();

        return view('report.accounts_receivable', compact('customers', 'bcv_rate'));
    }

    /**
     * Devuelve el JSON para DataTables con el listado de CxC por cliente.
     */
    private function getDataTable(Request $request, int $business_id)
    {
        $bcv_rate = getActiveBcvRate();

        $query = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->select([
                'c.id as contact_id',
                'c.name as cliente',
                'c.mobile as telefono',
                DB::raw('COUNT(t.id) as total_facturas'),
                DB::raw('SUM(t.final_total - t.total_paid) as saldo_usd'),
                DB::raw('MIN(t.transaction_date) as primera_factura'),
                DB::raw('MAX(t.transaction_date) as ultima_factura'),
                DB::raw('SUM(CASE WHEN t.due_date < NOW() THEN (t.final_total - t.total_paid) ELSE 0 END) as vencido_usd'),
                DB::raw('SUM(CASE WHEN t.due_date >= NOW() OR t.due_date IS NULL THEN (t.final_total - t.total_paid) ELSE 0 END) as por_vencer_usd'),
            ])
            ->groupBy('c.id', 'c.name', 'c.mobile');

        // Filtros opcionales
        if ($request->filled('contact_id')) {
            $query->where('t.contact_id', $request->contact_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('t.transaction_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('t.transaction_date', '<=', $request->end_date);
        }
        if ($request->filled('status')) {
            if ($request->status === 'vencida') {
                $query->having('vencido_usd', '>', 0);
            } elseif ($request->status === 'vigente') {
                $query->having('por_vencer_usd', '>', 0);
            }
        }

        return datatables()->of($query)
            ->addColumn('saldo_bs', function ($row) use ($bcv_rate) {
                return round($row->saldo_usd * $bcv_rate, 2);
            })
            ->addColumn('vencido_bs', function ($row) use ($bcv_rate) {
                return round($row->vencido_usd * $bcv_rate, 2);
            })
            ->addColumn('por_vencer_bs', function ($row) use ($bcv_rate) {
                return round($row->por_vencer_usd * $bcv_rate, 2);
            })
            ->addColumn('acciones', function ($row) {
                return '<a href="' . route('reports.cxc.detalle', $row->contact_id) . '"
                    class="btn btn-xs btn-info">
                    <i class="fa fa-eye"></i> Ver facturas
                </a>';
            })
            ->rawColumns(['acciones'])
            ->make(true);
    }

    /**
     * Detalle de facturas pendientes de un cliente.
     * GET /reports/accounts-receivable/{contact_id}
     */
    public function detalle(Request $request, int $contact_id)
    {
        $business_id = $request->session()->get('user.business_id');
        $bcv_rate    = getActiveBcvRate();

        $contact = Contact::where('business_id', $business_id)
            ->where('id', $contact_id)
            ->firstOrFail();

        $facturas = DB::table('transactions as t')
            ->leftJoin('contacts as c', 'c.id', '=', 't.contact_id')
            ->where('t.business_id', $business_id)
            ->where('t.contact_id', $contact_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->selectRaw('
                t.id, t.invoice_no, t.transaction_date, t.pay_term_number, t.pay_term_type,
                t.final_total, t.total_paid,
                t.payment_status,
                (t.final_total - t.total_paid) as balance_usd,
                (t.final_total - t.total_paid) * ? as balance_bs,
                GREATEST(0, DATEDIFF(CURDATE(),
                    CASE WHEN t.pay_term_type="days"
                         THEN DATE_ADD(t.transaction_date, INTERVAL t.pay_term_number DAY)
                         WHEN t.pay_term_type="months"
                         THEN DATE_ADD(t.transaction_date, INTERVAL t.pay_term_number MONTH)
                         ELSE CURDATE()
                    END
                )) as dias_vencida
            ', [$bcv_rate])
            ->orderBy('t.transaction_date', 'desc')
            ->get();

        $totales = [
            'total_usd'     => $facturas->sum('final_total'),
            'pagado_usd'    => $facturas->sum('total_paid'),
            'saldo_usd'     => $facturas->sum('balance_usd'),
            'saldo_bs'      => $facturas->sum('balance_bs'),
            'vencido_usd'   => $facturas->where('dias_vencida', '>', 0)->sum('balance_usd'),
            'por_vencer_usd'=> $facturas->where('dias_vencida', 0)->sum('balance_usd'),
        ];

        return view('report.accounts_receivable_detail', compact('contact', 'facturas', 'totales', 'bcv_rate'));
    }

    /**
     * Exportar CxC a Excel.
     * GET /reports/accounts-receivable/export
     */
    public function export(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $bcv_rate    = getActiveBcvRate();

        $rows = DB::table('transactions as t')
            ->join('contacts as c', 't.contact_id', '=', 'c.id')
            ->where('t.business_id', $business_id)
            ->where('t.type', 'sell')
            ->where('t.status', 'final')
            ->whereIn('t.payment_status', ['due', 'partial'])
            ->select([
                'c.name as Cliente',
                'c.mobile as Teléfono',
                DB::raw('COUNT(t.id) as "N° Facturas"'),
                DB::raw('SUM(t.final_total - t.total_paid) as "Saldo USD"'),
                DB::raw('SUM((t.final_total - t.total_paid) * ' . (float) $bcv_rate . ') as "Saldo Bs"'),
                DB::raw('MIN(t.transaction_date) as "Primera Factura"'),
                DB::raw('MAX(t.transaction_date) as "Última Factura"'),
            ])
            ->groupBy('c.id', 'c.name', 'c.mobile')
            ->get();

        // Si maatwebsite/excel no está disponible, retornar CSV
        $filename = 'cxc_' . now()->format('Ymd_His') . '.csv';
        $headers  = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($rows) {
            $handle = fopen('php://output', 'w');
            // BOM para Excel
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));
            if ($rows->isNotEmpty()) {
                fputcsv($handle, array_keys((array) $rows->first()), ';');
            }
            foreach ($rows as $row) {
                fputcsv($handle, (array) $row, ';');
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
