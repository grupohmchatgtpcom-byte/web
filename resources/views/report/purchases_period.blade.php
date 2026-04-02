@extends('layouts.app')
@section('title', 'Compras por Período')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Reporte de Compras por Período
        <small class="tw-text-sm tw-text-gray-500 tw-ml-2">
            Tasa BCV: <strong>Bs {{ number_format($bcv_rate, 2) }}</strong>
        </small>
    </h1>
</section>

<section class="content no-print">

    @component('components.filters', ['title' => 'Filtros'])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('pp_start_date', 'Desde:') !!}
                {!! Form::text('pp_start_date', $start_date, ['class'=>'form-control','id'=>'pp_start_date','readonly']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('pp_end_date', 'Hasta:') !!}
                {!! Form::text('pp_end_date', $end_date, ['class'=>'form-control','id'=>'pp_end_date','readonly']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('pp_supplier', 'Proveedor:') !!}
                {!! Form::select('pp_supplier', $suppliers, $supplier_id, ['class'=>'form-control select2','style'=>'width:100%','placeholder'=>'Todos','id'=>'pp_supplier']) !!}
            </div>
        </div>
        <div class="col-md-1">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary btn-sm" id="pp_apply">
                    <i class="fa fa-search"></i> Consultar
                </button>
                <a id="pp_export_btn"
                   href="{{ route('reports.purchases_period') }}?export=csv&start_date={{ $start_date }}&end_date={{ $end_date }}"
                   class="btn btn-success btn-sm">
                    <i class="fa fa-file-excel-o"></i> CSV
                </a>
            </div>
        </div>
    @endcomponent

    {{-- Resumen --}}
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="fa fa-shopping-basket"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Total Compras (USD)</span>
                    <span class="info-box-number">${{ number_format($summary->total_usd ?? 0, 2) }}</span>
                    <span class="progress-description">Bs {{ number_format($summary->total_bs ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="fa fa-percent"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Base Imponible</span>
                    <span class="info-box-number">${{ number_format($summary->base_imponible ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-red">
                <span class="info-box-icon"><i class="fa fa-bank"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">IVA Total</span>
                    <span class="info-box-number">${{ number_format($summary->total_iva ?? 0, 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fa fa-file-text-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">N° Órdenes</span>
                    <span class="info-box-number">{{ $summary->total_ordenes ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="pp_table">
                <thead>
                    <tr>
                        <th>Proveedor</th>
                        <th>RIF</th>
                        <th>N° Órdenes</th>
                        <th>Base Imponible ($)</th>
                        <th>IVA ($)</th>
                        <th>Total USD ($)</th>
                        <th>Total Bs</th>
                        <th>Última Compra</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
            </table>
        </div>
    @endcomponent
</section>
@stop

@section('javascript')
<script>
$(document).ready(function() {
    $('#pp_start_date, #pp_end_date').datepicker({ dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true });

    var table = $('#pp_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("reports.purchases_period") }}',
            data: function(d) {
                d.start_date   = $('#pp_start_date').val();
                d.end_date     = $('#pp_end_date').val();
                d.supplier_id  = $('#pp_supplier').val();
            },
        },
        columns: [
            { data: 'proveedor' },
            { data: 'rif', defaultContent: '—' },
            { data: 'total_ordenes', className: 'text-center' },
            { data: 'base_imponible', render: function(v){ return '$'+parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2}); } },
            { data: 'total_iva', render: function(v){ return '$'+parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2}); } },
            { data: 'total_usd', render: function(v){ return '$'+parseFloat(v||0).toLocaleString('en-US',{minimumFractionDigits:2}); } },
            { data: 'total_bs', render: function(v){ return 'Bs '+parseFloat(v||0).toLocaleString('es-VE',{minimumFractionDigits:2}); } },
            { data: 'ultima_compra', defaultContent: '—' },
            { data: 'acciones', orderable: false, searchable: false },
        ],
        order: [[5, 'desc']],
        language: { url: '/js/Spanish.json' },
    });

    $('#pp_apply').on('click', function() {
        var params = '?export=csv'
            + '&start_date=' + $('#pp_start_date').val()
            + '&end_date='   + $('#pp_end_date').val()
            + '&supplier_id=' + ($('#pp_supplier').val() || '');
        $('#pp_export_btn').attr('href', '{{ route("reports.purchases_period") }}' + params);
        table.draw();
    });
    $('#pp_supplier').on('change', function() { table.draw(); });
});
</script>
@stop
