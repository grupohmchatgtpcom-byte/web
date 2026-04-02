<?php

namespace App\Http\Controllers;

use App\BusinessLocation;
use App\Exports\DispatchSuggestionExport;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class ConsolidadoController extends Controller
{
    /**
     * Muestra la página principal del consolidado de pedidos.
     */
    public function index(Request $request)
    {
        if (! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        // Sin filtro de permisos y sin append_id para que los nombres sean limpios
        $business_locations = BusinessLocation::forDropdown($business_id, false, false, false, false);

        // Intentar detectar automáticamente el almacén principal por nombre
        $default_warehouse_id = null;
        $almacen_ghm = BusinessLocation::where('business_id', $business_id)
            ->where(function ($q) {
                $q->where('name', 'like', '%ALMACEN%')
                  ->orWhere('name', 'like', '%Almacen%')
                  ->orWhere('name', 'like', '%almacen%')
                  ->orWhere('name', 'like', '%GHM%')
                  ->orWhere('name', 'like', '%Bodega%')
                  ->orWhere('name', 'like', '%BODEGA%')
                  ->orWhere('name', 'like', '%Principal%');
            })
            ->Active()
            ->first();

        if ($almacen_ghm) {
            $default_warehouse_id = $almacen_ghm->id;
        }

        $has_policies_table = Schema::hasTable('ghm_dispatch_policies');

        // Verificar si hay datos en la tabla de políticas
        $has_policies_data = $has_policies_table
            ? DB::table('ghm_dispatch_policies')
                ->where('business_id', $business_id)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->exists()
            : false;

        return view('consolidado.index', compact(
            'business_locations',
            'default_warehouse_id',
            'has_policies_table',
            'has_policies_data'
        ));
    }

    /**
     * Ejecuta el cálculo del consolidado y retorna los resultados en JSON.
     */
    public function calcular(Request $request)
    {
        if (! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $warehouse_location_id = (int) $request->input('warehouse_location_id');

        if (empty($warehouse_location_id)) {
            return response()->json([
                'success' => false,
                'msg' => 'Debes seleccionar el almacén origen (Almacen GHM).',
            ]);
        }

        if (! Schema::hasTable('ghm_dispatch_policies')) {
            return response()->json([
                'success' => false,
                'msg' => 'No existe la tabla ghm_dispatch_policies. Contacta al administrador.',
            ]);
        }

        try {
            // Determinar si usar políticas personalizadas o modo fallback (alert_quantity)
            $use_policies = false;
            if (Schema::hasTable('ghm_dispatch_policies')) {
                $settings = Schema::hasTable('ghm_dispatch_settings')
                    ? DB::table('ghm_dispatch_settings')
                        ->where('business_id', $business_id)
                        ->where('warehouse_location_id', $warehouse_location_id)
                        ->where('is_active', 1)
                        ->first()
                    : null;

                $replenishment_mode = $settings->replenishment_mode ?? 'to_max_when_below_min';

                $policies = DB::table('ghm_dispatch_policies as gdp')
                    ->join('business_locations as bl', 'bl.id', '=', 'gdp.store_location_id')
                    ->join('variations as v', 'v.id', '=', 'gdp.variation_id')
                    ->join('products as p', 'p.id', '=', 'gdp.product_id')
                    ->where('gdp.business_id', $business_id)
                    ->where('gdp.warehouse_location_id', $warehouse_location_id)
                    ->where('gdp.is_active', 1)
                    ->whereNull('gdp.deleted_at')
                    ->select(
                        'gdp.variation_id',
                        'gdp.product_id',
                        'gdp.store_location_id',
                        'gdp.min_qty',
                        'gdp.max_qty',
                        'bl.name as store_name',
                        'p.name as product_name',
                        'p.sku as product_sku',
                        'v.sub_sku as variation_sku',
                        'v.name as variation_name'
                    )
                    ->get();

                $use_policies = $policies->isNotEmpty();
            }

            // ────────────────────────────────────────────────────────────────────
            // MODO FALLBACK: sin políticas configuradas → usa alert_quantity
            // ────────────────────────────────────────────────────────────────────
            if (! $use_policies) {
                return $this->calcularFallback($business_id, $warehouse_location_id);
            }

            // ────────────────────────────────────────────────────────────────────
            // MODO NORMAL: con policies en ghm_dispatch_policies
            // ────────────────────────────────────────────────────────────────────

            $variation_ids = $policies->pluck('variation_id')->unique()->values();
            $store_ids = $policies->pluck('store_location_id')->unique()->values();

            // Stock actual del almacén origen
            $warehouse_stocks_raw = DB::table('variation_location_details')
                ->where('location_id', $warehouse_location_id)
                ->whereIn('variation_id', $variation_ids)
                ->pluck('qty_available', 'variation_id');

            $warehouse_stocks = [];
            foreach ($warehouse_stocks_raw as $vid => $qty) {
                $warehouse_stocks[$vid] = (float) $qty;
            }

            // Stock actual de todas las tiendas
            $store_stocks_rows = DB::table('variation_location_details')
                ->whereIn('location_id', $store_ids)
                ->whereIn('variation_id', $variation_ids)
                ->select('location_id', 'variation_id', 'qty_available')
                ->get();

            $store_stock_map = [];
            foreach ($store_stocks_rows as $row) {
                $store_stock_map[$row->variation_id . '_' . $row->location_id] = (float) $row->qty_available;
            }

            // Agrupar políticas por variación
            $policies_by_variation = $policies->groupBy('variation_id');

            // Resultados por tienda: [store_name => [items]]
            $results_by_store = [];
            // Faltantes: [store_name => [items con shortage]]
            $shortages_by_store = [];
            // Resumen global
            $summary = [
                'total_productos' => 0,
                'total_tiendas_con_despacho' => 0,
                'total_unidades_a_despachar' => 0,
                'total_unidades_con_faltante' => 0,
                'tiendas_con_faltante' => [],
            ];

            foreach ($policies_by_variation as $variation_id => $variation_policies) {
                $warehouse_available = $warehouse_stocks[$variation_id] ?? 0;
                $first_policy = $variation_policies->first();
                $product_name = $first_policy->product_name;
                $variation_name = $first_policy->variation_name;
                $sku = $first_policy->variation_sku ?: $first_policy->product_sku;

                // Determinar necesidad de cada tienda y ordenar ALFABÉTICAMENTE
                $needs = [];
                foreach ($variation_policies as $policy) {
                    $store_stock = $store_stock_map[$variation_id . '_' . $policy->store_location_id] ?? 0;
                    $min_qty = (float) $policy->min_qty;
                    $max_qty = (float) $policy->max_qty;

                    // ¿Necesita reposición según el modo configurado?
                    if ($replenishment_mode === 'always_to_max') {
                        $required = max(0, $max_qty - $store_stock);
                    } else {
                        // to_max_when_below_min: solo si está por debajo del mínimo
                        $required = $store_stock < $min_qty ? max(0, $max_qty - $store_stock) : 0;
                    }

                    if ($required <= 0) {
                        continue;
                    }

                    $needs[$policy->store_location_id] = [
                        'store_name'   => $policy->store_name,
                        'store_stock'  => $store_stock,
                        'min_qty'      => $min_qty,
                        'max_qty'      => $max_qty,
                        'required'     => $required,
                    ];
                }

                if (empty($needs)) {
                    continue;
                }

                // Ordenar ALFABÉTICAMENTE por nombre de tienda
                uasort($needs, fn($a, $b) => strcmp($a['store_name'], $b['store_name']));

                $warehouse_before_product = $warehouse_available;
                $any_dispatched = false;

                foreach ($needs as $store_id => $need) {
                    $required = (float) $need['required'];
                    $given = min($required, $warehouse_available);
                    $shortage = $required - $given;

                    $warehouse_available -= $given;

                    if ($given > 0) {
                        $store_name = $need['store_name'];
                        if (! isset($results_by_store[$store_name])) {
                            $results_by_store[$store_name] = [];
                        }
                        $results_by_store[$store_name][] = [
                            'product_name'       => $product_name,
                            'variation_name'     => $variation_name,
                            'sku'                => $sku,
                            'stock_tienda'       => round($need['store_stock'], 2),
                            'minimo'             => round($need['min_qty'], 2),
                            'maximo'             => round($need['max_qty'], 2),
                            'requerido'          => round($required, 2),
                            'a_enviar'           => round($given, 2),
                            'stock_final_est'    => round($need['store_stock'] + $given, 2),
                            'tiene_faltante'     => $shortage > 0.001,
                            'faltante'           => round($shortage, 2),
                        ];
                        $summary['total_unidades_a_despachar'] += $given;
                        $any_dispatched = true;
                    }

                    if ($shortage > 0.001) {
                        $store_name = $need['store_name'];
                        if (! isset($shortages_by_store[$store_name])) {
                            $shortages_by_store[$store_name] = [];
                        }
                        $shortages_by_store[$store_name][] = [
                            'product_name'   => $product_name,
                            'variation_name' => $variation_name,
                            'sku'            => $sku,
                            'stock_tienda'   => round($need['store_stock'], 2),
                            'minimo'         => round($need['min_qty'], 2),
                            'maximo'         => round($need['max_qty'], 2),
                            'requerido'      => round($required, 2),
                            'dado'           => round($given, 2),
                            'faltante'       => round($shortage, 2),
                        ];
                        $summary['total_unidades_con_faltante'] += $shortage;
                        if (! in_array($store_name, $summary['tiendas_con_faltante'])) {
                            $summary['tiendas_con_faltante'][] = $store_name;
                        }
                    }
                }

                if ($any_dispatched) {
                    $summary['total_productos']++;
                }
            }

            // Ordenar tiendas alfabéticamente
            ksort($results_by_store);
            ksort($shortages_by_store);

            $summary['total_tiendas_con_despacho'] = count($results_by_store);
            sort($summary['tiendas_con_faltante']);

            // Ordenar productos por nombre dentro de cada tienda
            foreach ($results_by_store as &$items) {
                usort($items, fn($a, $b) => strcmp($a['product_name'], $b['product_name']));
            }
            unset($items);
            foreach ($shortages_by_store as &$items) {
                usort($items, fn($a, $b) => strcmp($a['product_name'], $b['product_name']));
            }
            unset($items);

            return response()->json([
                'success'           => true,
                'results_by_store'  => $results_by_store,
                'shortages_by_store' => $shortages_by_store,
                'summary'           => $summary,
            ]);

        } catch (\Exception $e) {
            \Log::error('Consolidado calculation error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'msg'     => 'Error al calcular el consolidado: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Modo fallback: calcula distribución usando products.alert_quantity como mínimo.
     * No requiere ghm_dispatch_policies.
     * Despacha desde el almacén seleccionado a TODAS las demás tiendas donde
     * el stock actual esté por debajo del alert_quantity del producto.
     */
    protected function calcularFallback(int $business_id, int $warehouse_location_id): \Illuminate\Http\JsonResponse
    {
        // Todas las sucursales excepto el almacén origen
        $store_locations = DB::table('business_locations')
            ->where('business_id', $business_id)
            ->where('id', '!=', $warehouse_location_id)
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        if ($store_locations->isEmpty()) {
            return response()->json([
                'success' => false,
                'msg' => 'No hay otras sucursales/tiendas registradas.',
            ]);
        }

        $store_ids = $store_locations->pluck('id');

        // Productos con stock management activo y alert_quantity configurado
        $products = DB::table('products as p')
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->where('p.business_id', $business_id)
            ->where('p.enable_stock', 1)
            ->where('p.alert_quantity', '>', 0)
            ->whereNull('v.deleted_at')
            ->select(
                'p.id as product_id',
                'p.name as product_name',
                'p.sku as product_sku',
                'p.alert_quantity as min_qty',
                'v.id as variation_id',
                'v.sub_sku as variation_sku',
                'v.name as variation_name'
            )
            ->get();

        if ($products->isEmpty()) {
            return response()->json([
                'success' => false,
                'msg' => 'No hay productos con stock habilitado y cantidad mínima (alerta) configurada. ' .
                         'Configura el campo "Cantidad de alerta" en los productos para usar este modo automático.',
            ]);
        }

        $variation_ids = $products->pluck('variation_id');

        // Stock del almacén origen
        $warehouse_stocks = DB::table('variation_location_details')
            ->where('location_id', $warehouse_location_id)
            ->whereIn('variation_id', $variation_ids)
            ->where('qty_available', '>', 0)
            ->pluck('qty_available', 'variation_id')
            ->map(fn($q) => (float) $q)
            ->toArray();

        // Stock de todas las tiendas
        $store_stocks_rows = DB::table('variation_location_details')
            ->whereIn('location_id', $store_ids)
            ->whereIn('variation_id', $variation_ids)
            ->select('location_id', 'variation_id', 'qty_available')
            ->get();

        $store_stock_map = [];
        foreach ($store_stocks_rows as $row) {
            $store_stock_map[$row->variation_id . '_' . $row->location_id] = (float) $row->qty_available;
        }

        $store_names = $store_locations->pluck('name', 'id')->toArray();

        $results_by_store  = [];
        $shortages_by_store = [];
        $summary = [
            'total_productos'             => 0,
            'total_tiendas_con_despacho'  => 0,
            'total_unidades_a_despachar'  => 0,
            'total_unidades_con_faltante' => 0,
            'tiendas_con_faltante'        => [],
            'modo_fallback'               => true,
        ];

        foreach ($products as $product) {
            $variation_id  = $product->variation_id;
            $warehouse_avail = $warehouse_stocks[$variation_id] ?? 0;

            if ($warehouse_avail <= 0) {
                continue;
            }

            $min_qty   = (float) $product->min_qty;
            // Max = el doble del mínimo como valor por defecto (llenar hasta 2x el mínimo)
            $max_qty   = $min_qty * 2;
            $any_dispatched = false;

            // Ordenar tiendas ALFABÉTICAMENTE
            $sorted_stores = $store_locations->sortBy('name');

            foreach ($sorted_stores as $store) {
                $store_stock = $store_stock_map[$variation_id . '_' . $store->id] ?? 0;

                // Solo reponer si está por debajo del mínimo
                if ($store_stock >= $min_qty) {
                    continue;
                }

                $required = $max_qty - $store_stock;
                $given    = min($required, $warehouse_avail);
                $shortage = $required - $given;

                $warehouse_avail -= $given;

                $store_name = $store->name;

                if ($given > 0) {
                    if (! isset($results_by_store[$store_name])) {
                        $results_by_store[$store_name] = [];
                    }
                    $results_by_store[$store_name][] = [
                        'product_name'    => $product->product_name,
                        'variation_name'  => $product->variation_name,
                        'sku'             => $product->variation_sku ?: $product->product_sku,
                        'stock_tienda'    => round($store_stock, 2),
                        'minimo'          => round($min_qty, 2),
                        'maximo'          => round($max_qty, 2),
                        'requerido'       => round($required, 2),
                        'a_enviar'        => round($given, 2),
                        'stock_final_est' => round($store_stock + $given, 2),
                        'tiene_faltante'  => $shortage > 0.001,
                        'faltante'        => round($shortage, 2),
                    ];
                    $summary['total_unidades_a_despachar'] += $given;
                    $any_dispatched = true;
                }

                if ($shortage > 0.001) {
                    if (! isset($shortages_by_store[$store_name])) {
                        $shortages_by_store[$store_name] = [];
                    }
                    $shortages_by_store[$store_name][] = [
                        'product_name'   => $product->product_name,
                        'variation_name' => $product->variation_name,
                        'sku'            => $product->variation_sku ?: $product->product_sku,
                        'stock_tienda'   => round($store_stock, 2),
                        'minimo'         => round($min_qty, 2),
                        'maximo'         => round($max_qty, 2),
                        'requerido'      => round($required, 2),
                        'dado'           => round($given, 2),
                        'faltante'       => round($shortage, 2),
                    ];
                    $summary['total_unidades_con_faltante'] += $shortage;
                    if (! in_array($store_name, $summary['tiendas_con_faltante'])) {
                        $summary['tiendas_con_faltante'][] = $store_name;
                    }
                }

                if ($warehouse_avail <= 0) {
                    break;
                }
            }

            if ($any_dispatched) {
                $summary['total_productos']++;
            }
        }

        if (empty($results_by_store)) {
            return response()->json([
                'success' => false,
                'msg'     => 'Todas las tiendas tienen stock igual o superior al mínimo configurado. No hay nada que despachar.',
            ]);
        }

        ksort($results_by_store);
        ksort($shortages_by_store);
        $summary['total_tiendas_con_despacho'] = count($results_by_store);
        sort($summary['tiendas_con_faltante']);

        foreach ($results_by_store as &$items) {
            usort($items, fn($a, $b) => strcmp($a['product_name'], $b['product_name']));
        }
        unset($items);
        foreach ($shortages_by_store as &$items) {
            usort($items, fn($a, $b) => strcmp($a['product_name'], $b['product_name']));
        }
        unset($items);

        return response()->json([
            'success'            => true,
            'results_by_store'   => $results_by_store,
            'shortages_by_store' => $shortages_by_store,
            'summary'            => $summary,
        ]);
    }

    /**
     * Exporta la sugerencia de despacho a Excel.
     * Si no hay políticas configuradas, usa el modo fallback (alert_quantity).
     */
    public function exportarExcel(Request $request)
    {
        if (! auth()->user()->can('purchase.view') && ! auth()->user()->can('purchase.create')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = $request->session()->get('user.business_id');
        $warehouse_location_id = (int) $request->input('warehouse_location_id');

        if (empty($warehouse_location_id)) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Selecciona el almacén origen.',
            ]);
        }

        try {
            // Determinar si usar políticas o modo fallback
            $use_policies = false;
            $policies = collect();
            $replenishment_mode = 'to_max_when_below_min';

            if (Schema::hasTable('ghm_dispatch_policies')) {
                $settings = Schema::hasTable('ghm_dispatch_settings')
                    ? DB::table('ghm_dispatch_settings')
                        ->where('business_id', $business_id)
                        ->where('warehouse_location_id', $warehouse_location_id)
                        ->where('is_active', 1)
                        ->first()
                    : null;

                $replenishment_mode = $settings->replenishment_mode ?? 'to_max_when_below_min';

                $policies = DB::table('ghm_dispatch_policies as gdp')
                    ->join('business_locations as bl', 'bl.id', '=', 'gdp.store_location_id')
                    ->join('variations as v', 'v.id', '=', 'gdp.variation_id')
                    ->join('products as p', 'p.id', '=', 'gdp.product_id')
                    ->where('gdp.business_id', $business_id)
                    ->where('gdp.warehouse_location_id', $warehouse_location_id)
                    ->where('gdp.is_active', 1)
                    ->whereNull('gdp.deleted_at')
                    ->select(
                        'gdp.variation_id',
                        'gdp.store_location_id',
                        'gdp.min_qty',
                        'gdp.max_qty',
                        'bl.name as store_name',
                        'p.name as product_name',
                        'v.sub_sku as variation_sku'
                    )
                    ->get();

                $use_policies = $policies->isNotEmpty();
            }

            if (! $use_policies) {
                return $this->exportarExcelFallback($business_id, $warehouse_location_id);
            }

            $variation_ids = $policies->pluck('variation_id')->unique()->values();
            $store_ids = $policies->pluck('store_location_id')->unique()->values();

            $warehouse_stocks = DB::table('variation_location_details')
                ->where('location_id', $warehouse_location_id)
                ->whereIn('variation_id', $variation_ids)
                ->pluck('qty_available', 'variation_id');

            $store_stocks_rows = DB::table('variation_location_details')
                ->whereIn('location_id', $store_ids)
                ->whereIn('variation_id', $variation_ids)
                ->select('location_id', 'variation_id', 'qty_available')
                ->get();

            $store_stock_map = [];
            foreach ($store_stocks_rows as $row) {
                $store_stock_map[$row->variation_id . '_' . $row->location_id] = (float) $row->qty_available;
            }

            $policies_by_variation = $policies->groupBy('variation_id');
            $rows = [];

            foreach ($policies_by_variation as $variation_id => $variation_policies) {
                $warehouse_available = (float) ($warehouse_stocks[$variation_id] ?? 0);

                $needs = [];
                foreach ($variation_policies as $policy) {
                    $store_stock = $store_stock_map[$variation_id . '_' . $policy->store_location_id] ?? 0;
                    $min_qty = (float) $policy->min_qty;
                    $max_qty = (float) $policy->max_qty;

                    if ($replenishment_mode === 'always_to_max') {
                        $required = max(0, $max_qty - $store_stock);
                    } else {
                        $required = $store_stock < $min_qty ? max(0, $max_qty - $store_stock) : 0;
                    }

                    if ($required <= 0) {
                        continue;
                    }

                    $needs[] = [
                        'policy'      => $policy,
                        'store_stock' => $store_stock,
                        'required'    => $required,
                    ];
                }

                // Ordenar ALFABÉTICAMENTE
                usort($needs, fn($a, $b) => strcmp($a['policy']->store_name, $b['policy']->store_name));

                foreach ($needs as $need) {
                    $required = (float) $need['required'];
                    $given = min($required, $warehouse_available);
                    $warehouse_before = $warehouse_available;
                    $warehouse_available -= $given;

                    $rows[] = [
                        'tienda'               => $need['policy']->store_name,
                        'producto'             => $need['policy']->product_name,
                        'sku_variacion'        => $need['policy']->variation_sku,
                        'stock_tienda'         => round($need['store_stock'], 4),
                        'minimo'               => round((float) $need['policy']->min_qty, 4),
                        'maximo'               => round((float) $need['policy']->max_qty, 4),
                        'requerido'            => round($required, 4),
                        'sugerido'             => round($given, 4),
                        'stock_almacen_antes'  => round($warehouse_before, 4),
                        'stock_almacen_despues' => round($warehouse_available, 4),
                        'observaciones'        => $given < $required ? 'Stock insuficiente en almacen' : 'OK',
                    ];
                }
            }

            if (empty($rows)) {
                return redirect()->back()->with('status', [
                    'success' => 0,
                    'msg' => 'No hay ítems por despachar según las políticas actuales.',
                ]);
            }

            // Ordenar filas: primero por tienda, luego por producto
            usort($rows, fn($a, $b) => strcmp($a['tienda'], $b['tienda']) ?: strcmp($a['producto'], $b['producto']));

            $file_name = 'consolidado_' . now()->format('Ymd_His') . '.xlsx';

            return Excel::download(new DispatchSuggestionExport($rows), $file_name);

        } catch (\Exception $e) {
            \Log::error('Consolidado export error: ' . $e->getMessage());

            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Error al exportar: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Exporta en modo fallback (sin políticas configuradas), usando alert_quantity.
     */
    protected function exportarExcelFallback(int $business_id, int $warehouse_location_id)
    {
        $store_locations = DB::table('business_locations')
            ->where('business_id', $business_id)
            ->where('id', '!=', $warehouse_location_id)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->select('id', 'name')
            ->orderBy('name')
            ->get();

        if ($store_locations->isEmpty()) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'No hay otras sucursales/tiendas registradas.',
            ]);
        }

        $store_ids = $store_locations->pluck('id');

        $products = DB::table('products as p')
            ->join('variations as v', 'v.product_id', '=', 'p.id')
            ->where('p.business_id', $business_id)
            ->where('p.enable_stock', 1)
            ->where('p.alert_quantity', '>', 0)
            ->whereNull('v.deleted_at')
            ->select(
                'p.id as product_id',
                'p.name as product_name',
                'p.sku as product_sku',
                'p.alert_quantity as min_qty',
                'v.id as variation_id',
                'v.sub_sku as variation_sku',
                'v.name as variation_name'
            )
            ->get();

        if ($products->isEmpty()) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'No hay productos con cantidad mínima (alerta) configurada. ' .
                         'Configura el campo "Cantidad de alerta" en los productos.',
            ]);
        }

        $variation_ids = $products->pluck('variation_id');

        $warehouse_stocks = DB::table('variation_location_details')
            ->where('location_id', $warehouse_location_id)
            ->whereIn('variation_id', $variation_ids)
            ->where('qty_available', '>', 0)
            ->pluck('qty_available', 'variation_id')
            ->map(fn($q) => (float) $q)
            ->toArray();

        $store_stocks_rows = DB::table('variation_location_details')
            ->whereIn('location_id', $store_ids)
            ->whereIn('variation_id', $variation_ids)
            ->select('location_id', 'variation_id', 'qty_available')
            ->get();

        $store_stock_map = [];
        foreach ($store_stocks_rows as $row) {
            $store_stock_map[$row->variation_id . '_' . $row->location_id] = (float) $row->qty_available;
        }

        $rows = [];

        foreach ($products as $product) {
            $variation_id    = $product->variation_id;
            $warehouse_avail = $warehouse_stocks[$variation_id] ?? 0;

            if ($warehouse_avail <= 0) {
                continue;
            }

            $min_qty = (float) $product->min_qty;
            $max_qty = $min_qty * 2;

            foreach ($store_locations->sortBy('name') as $store) {
                $store_stock = $store_stock_map[$variation_id . '_' . $store->id] ?? 0;

                if ($store_stock >= $min_qty) {
                    continue;
                }

                $required        = $max_qty - $store_stock;
                $given           = min($required, $warehouse_avail);
                $warehouse_before = $warehouse_avail;
                $warehouse_avail -= $given;

                $rows[] = [
                    'tienda'               => $store->name,
                    'producto'             => $product->product_name,
                    'sku_variacion'        => $product->variation_sku ?: $product->product_sku,
                    'stock_tienda'         => round($store_stock, 4),
                    'minimo'               => round($min_qty, 4),
                    'maximo'               => round($max_qty, 4),
                    'requerido'            => round($required, 4),
                    'sugerido'             => round($given, 4),
                    'stock_almacen_antes'  => round($warehouse_before, 4),
                    'stock_almacen_despues' => round($warehouse_avail, 4),
                    'observaciones'        => $given < $required ? 'Stock insuficiente en almacen' : 'OK',
                ];

                if ($warehouse_avail <= 0) {
                    break;
                }
            }
        }

        if (empty($rows)) {
            return redirect()->back()->with('status', [
                'success' => 0,
                'msg' => 'Todas las tiendas tienen stock igual o superior al mínimo. No hay nada que despachar.',
            ]);
        }

        usort($rows, fn($a, $b) => strcmp($a['tienda'], $b['tienda']) ?: strcmp($a['producto'], $b['producto']));

        $file_name = 'consolidado_fallback_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new DispatchSuggestionExport($rows), $file_name);
    }
}
