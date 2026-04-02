@extends('layouts.app')
@section('title', 'Cierre de Caja')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Cierre de Caja
        <small class="tw-text-sm tw-text-gray-500 tw-ml-2">
            Tasa BCV: <strong>Bs {{ number_format($bcv_rate, 2) }}</strong>
        </small>
    </h1>
</section>

<section class="content no-print">

    @component('components.filters', ['title' => 'Parámetros'])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cc_date', 'Fecha:') !!}
                {!! Form::text('cc_date', $date, ['class'=>'form-control','id'=>'cc_date','readonly']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cc_location', 'Sucursal:') !!}
                {!! Form::select('cc_location', $locations, $location_id, ['class'=>'form-control select2','style'=>'width:100%','placeholder'=>'Todas','id'=>'cc_location']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary btn-sm" id="cc_apply"><i class="fa fa-search"></i> Consultar</button>
                <a id="cc_export_btn" href="{{ route('reports.cash_register_close.export') }}?date={{ $date }}"
                   class="btn btn-success btn-sm"><i class="fa fa-file-excel-o"></i> CSV</a>
                <button onclick="window.print()" class="btn btn-default btn-sm ml-1"><i class="fa fa-print"></i></button>
            </div>
        </div>
    @endcomponent

    {{-- Encabezado estilo comprobante --}}
    <div class="row">
        <div class="col-md-12">
            <div class="box box-primary">
                <div class="box-header with-border text-center">
                    <h3 class="box-title">
                        <i class="fa fa-cash-register"></i>
                        CIERRE DE CAJA — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }}
                    </h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        {{-- Columna izquierda: Resumen ventas --}}
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="bg-primary"><th colspan="3">RESUMEN DE VENTAS</th></tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>Fondo inicial de caja</td>
                                        <td class="text-right">${{ number_format($resumen['fondo_inicial'], 2) }}</td>
                                        <td class="text-right">Bs {{ number_format($resumen['fondo_inicial'] * $bcv_rate, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Total ventas del día ({{ $resumen['total_transacciones'] }} facturas)</td>
                                        <td class="text-right text-green"><strong>${{ number_format($resumen['total_ventas_usd'], 2) }}</strong></td>
                                        <td class="text-right text-green"><strong>Bs {{ number_format($resumen['total_ventas_bs'], 2) }}</strong></td>
                                    </tr>
                                    <tr>
                                        <td>Devoluciones</td>
                                        <td class="text-right text-red">-${{ number_format($resumen['devoluciones_usd'], 2) }}</td>
                                        <td class="text-right text-red">-Bs {{ number_format($resumen['devoluciones_usd'] * $bcv_rate, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td>Gastos del día</td>
                                        <td class="text-right text-red">-${{ number_format($resumen['gastos_usd'], 2) }}</td>
                                        <td class="text-right text-red">-Bs {{ number_format($resumen['gastos_usd'] * $bcv_rate, 2) }}</td>
                                    </tr>
                                    <tr class="bg-success">
                                        <td><strong>NETO EN CAJA</strong></td>
                                        <td class="text-right"><strong>${{ number_format($resumen['neto_usd'], 2) }}</strong></td>
                                        <td class="text-right"><strong>Bs {{ number_format($resumen['neto_bs'], 2) }}</strong></td>
                                    </tr>
                                </tbody>
                            </table>

                            @if($cash_register)
                            <p class="text-muted text-sm">
                                <i class="fa fa-info-circle"></i>
                                Caja abierta a las {{ \Carbon\Carbon::parse($cash_register->created_at)->format('H:i') }}.
                                Fondo declarado: ${{ number_format($cash_register->opening_amount, 2) }}
                            </p>
                            @endif
                        </div>

                        {{-- Columna derecha: Desglose por método de pago --}}
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="bg-primary">
                                        <th>Método de Pago</th>
                                        <th>Moneda</th>
                                        <th>Cant.</th>
                                        <th>Total USD</th>
                                        <th>Total Bs</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($cobros_por_metodo as $cobro)
                                    <tr>
                                        <td>{{ ucfirst(str_replace('_', ' ', $cobro->metodo)) }}</td>
                                        <td class="text-center">
                                            <span class="label {{ $cobro->moneda === 'VES' ? 'label-warning' : 'label-info' }}">
                                                {{ $cobro->moneda }}
                                            </span>
                                        </td>
                                        <td class="text-center">{{ $cobro->cantidad }}</td>
                                        <td class="text-right">${{ number_format($cobro->total_usd, 2) }}</td>
                                        <td class="text-right">Bs {{ number_format($cobro->total_bs, 2) }}</td>
                                    </tr>
                                    @empty
                                    <tr><td colspan="5" class="text-center text-muted">Sin pagos registrados.</td></tr>
                                    @endforelse
                                </tbody>
                                @if($cobros_por_metodo->isNotEmpty())
                                <tfoot>
                                    <tr class="bg-info">
                                        <th colspan="3">TOTAL COBRADO</th>
                                        <th class="text-right">${{ number_format($cobros_por_metodo->sum('total_usd'), 2) }}</th>
                                        <th class="text-right">Bs {{ number_format($cobros_por_metodo->sum('total_bs'), 2) }}</th>
                                    </tr>
                                </tfoot>
                                @endif
                            </table>
                        </div>
                    </div>

                    {{-- Diferencia (sobrante/faltante) --}}
                    @php
                        $cobrado_usd = $cobros_por_metodo->sum('total_usd');
                        $diferencia  = $cobrado_usd - $resumen['neto_usd'];
                    @endphp
                    <div class="row mt-3">
                        <div class="col-md-6 col-md-offset-3">
                            <div class="alert {{ abs($diferencia) < 0.01 ? 'alert-success' : ($diferencia > 0 ? 'alert-info' : 'alert-danger') }}">
                                <strong>
                                    @if(abs($diferencia) < 0.01)
                                        <i class="fa fa-check-circle"></i> Caja cuadrada
                                    @elseif($diferencia > 0)
                                        <i class="fa fa-arrow-up"></i> Sobrante en caja: ${{ number_format($diferencia, 2) }}
                                    @else
                                        <i class="fa fa-arrow-down"></i> Faltante en caja: ${{ number_format(abs($diferencia), 2) }}
                                    @endif
                                </strong>
                                <span class="pull-right text-muted">
                                    Bs {{ number_format($diferencia * $bcv_rate, 2) }}
                                </span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</section>

{{-- Estilos de impresión --}}
@section('css')
<style>
@media print {
    .no-print, .sidebar, .main-header, .content-header ol { display: none !important; }
    .content-wrapper { margin-left: 0 !important; }
    .box { border: 1px solid #ccc !important; box-shadow: none !important; }
}
</style>
@stop
@stop

@section('javascript')
<script>
$(document).ready(function() {
    $('#cc_date').datepicker({ dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true });
    $('#cc_apply').on('click', function() {
        var params = '?date=' + $('#cc_date').val()
            + '&location_id=' + ($('#cc_location').val() || '');
        $('#cc_export_btn').attr('href', '{{ route("reports.cash_register_close.export") }}' + params);
        window.location.href = '{{ route("reports.cash_register_close") }}' + params;
    });
});
</script>
@stop
