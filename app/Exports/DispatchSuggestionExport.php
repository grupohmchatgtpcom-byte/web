<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class DispatchSuggestionExport implements FromArray
{
    protected $rows;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        $output = [[
            'TIENDA',
            'PRODUCTO',
            'SKU_VARIACION',
            'STOCK_TIENDA',
            'MINIMO',
            'MAXIMO',
            'REQUERIDO',
            'SUGERIDO',
            'STOCK_ALMACEN_ANTES',
            'STOCK_ALMACEN_DESPUES',
            'OBSERVACIONES',
        ]];

        foreach ($this->rows as $row) {
            $output[] = [
                $row['tienda'] ?? '',
                $row['producto'] ?? '',
                $row['sku_variacion'] ?? '',
                $row['stock_tienda'] ?? 0,
                $row['minimo'] ?? 0,
                $row['maximo'] ?? 0,
                $row['requerido'] ?? 0,
                $row['sugerido'] ?? 0,
                $row['stock_almacen_antes'] ?? 0,
                $row['stock_almacen_despues'] ?? 0,
                $row['observaciones'] ?? '',
            ];
        }

        return $output;
    }
}
