@extends('layouts.app')
@section('title', 'Libro de Ventas — SENIAT')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Libro de Ventas
        <small>Formato SENIAT — {{ \Carbon\Carbon::parse($start_date)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($end_date)->format('d/m/Y') }}</small>
    </h1>
</section>

<section class="content no-print">

    @component('components.filters', ['title' => 'Período'])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('lv_start', 'Desde:') !!}
                {!! Form::text('lv_start', $start_date, ['class'=>'form-control','id'=>'lv_start','readonly']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('lv_end', 'Hasta:') !!}
                {!! Form::text('lv_end', $end_date, ['class'=>'form-control','id'=>'lv_end','readonly']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary btn-sm" id="lv_apply"><i class="fa fa-search"></i> Consultar</button>
                <a href="{{ route('reports.libro_ventas') }}?start_date={{ $start_date }}&end_date={{ $end_date }}&export=csv"
                   class="btn btn-success btn-sm ml-1">
                    <i class="fa fa-file-excel-o"></i> CSV
                </a>
                <button onclick="window.print()" class="btn btn-default btn-sm ml-1">
                    <i class="fa fa-print"></i> Imprimir
                </button>
            </div>
        </div>
    @endcomponent

    {{-- Tarjetas resumen --}}
    <div class="row">
        <div class="col-md-2">
            <div class="info-box bg-blue">
                <div class="info-box-content">
                    <span class="info-box-text">N° Facturas</span>
                    <span class="info-box-number">{{ $totales['count'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box bg-yellow">
                <div class="info-box-content">
                    <span class="info-box-text">Base Imponible $</span>
                    <span class="info-box-number">${{ number_format($totales['base_imponible'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box bg-red">
                <div class="info-box-content">
                    <span class="info-box-text">IVA Total $</span>
                    <span class="info-box-number">${{ number_format($totales['monto_iva'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box bg-gray">
                <div class="info-box-content">
                    <span class="info-box-text">Exento $</span>
                    <span class="info-box-number">${{ number_format($totales['monto_exento'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box bg-green">
                <div class="info-box-content">
                    <span class="info-box-text">Total Ventas $</span>
                    <span class="info-box-number">${{ number_format($totales['total_facturas'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="info-box bg-aqua">
                <div class="info-box-content">
                    <span class="info-box-text">Total Ventas Bs</span>
                    <span class="info-box-number">Bs {{ number_format($totales['total_bs'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped table-condensed" id="lv_table" style="font-size:12px;">
                <thead class="bg-primary">
                    <tr>
                        <th>N° Control</th>
                        <th>N° Factura</th>
                        <th>Fecha</th>
                        <th>RIF Cliente</th>
                        <th>Razón Social</th>
                        <th>Base Imponible $</th>
                        <th>Alíc. %</th>
                        <th>IVA $</th>
                        <th>Exento $</th>
                        <th>Total $</th>
                        <th>Total Bs</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($facturas as $f)
                    <tr>
                        <td>{{ $f->id }}</td>
                        <td>{{ $f->invoice_no }}</td>
                        <td>{{ \Carbon\Carbon::parse($f->transaction_date)->format('d/m/Y') }}</td>
                        <td>{{ $f->rif_cliente ?: '—' }}</td>
                        <td>{{ $f->nombre_cliente }}</td>
                        <td class="text-right">${{ number_format($f->base_imponible, 2) }}</td>
                        <td class="text-center">{{ $f->alicuota_iva }}%</td>
                        <td class="text-right">${{ number_format($f->monto_iva, 2) }}</td>
                        <td class="text-right">${{ number_format($f->monto_exento, 2) }}</td>
                        <td class="text-right">${{ number_format($f->total_factura, 2) }}</td>
                        <td class="text-right">Bs {{ number_format($f->total_factura_bs, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="11" class="text-center">No hay facturas de venta en este período.</td></tr>
                    @endforelse
                </tbody>
                @if($facturas->isNotEmpty())
                <tfoot class="bg-info">
                    <tr>
                        <th colspan="5" class="text-right">TOTALES DEL PERÍODO:</th>
                        <th class="text-right">${{ number_format($totales['base_imponible'], 2) }}</th>
                        <th></th>
                        <th class="text-right">${{ number_format($totales['monto_iva'], 2) }}</th>
                        <th class="text-right">${{ number_format($totales['monto_exento'], 2) }}</th>
                        <th class="text-right">${{ number_format($totales['total_facturas'], 2) }}</th>
                        <th class="text-right">Bs {{ number_format($totales['total_bs'], 2) }}</th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    @endcomponent
</section>
@stop

@section('javascript')
<script>
$(document).ready(function() {
    $('#lv_start, #lv_end').datepicker({ dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true });
    $('#lv_apply').on('click', function() {
        window.location.href = '{{ route("reports.libro_ventas") }}?start_date=' + $('#lv_start').val() + '&end_date=' + $('#lv_end').val();
    });
    if ($('#lv_table tbody tr').length) {
        $('#lv_table').DataTable({
            paging: false,
            order: [[2, 'asc']],
            language: { url: '/js/Spanish.json' },
        });
    }
});
</script>
@stop
