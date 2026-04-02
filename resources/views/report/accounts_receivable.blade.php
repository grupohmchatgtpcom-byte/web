@extends('layouts.app')
@section('title', 'Cuentas por Cobrar (CxC)')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Cuentas por Cobrar
        <small class="tw-text-sm tw-text-gray-500 tw-ml-2">Tasa BCV: <strong>Bs {{ number_format($bcv_rate, 2) }}</strong></small>
    </h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => 'Filtros'])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cxc_filter_contact', 'Cliente:') !!}
                {!! Form::select('cxc_filter_contact', $customers, null, [
                    'class'       => 'form-control select2',
                    'style'       => 'width:100%',
                    'placeholder' => 'Todos los clientes',
                    'id'          => 'cxc_filter_contact',
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cxc_filter_status', 'Estado:') !!}
                {!! Form::select('cxc_filter_status', [
                    ''        => 'Todos',
                    'vencida' => 'Vencidas',
                    'vigente' => 'Vigentes',
                ], null, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'id'    => 'cxc_filter_status',
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('cxc_filter_date_range', 'Rango de fechas:') !!}
                {!! Form::text('cxc_filter_date_range', null, [
                    'placeholder' => 'Seleccionar rango',
                    'class'       => 'form-control',
                    'readonly',
                    'id'          => 'cxc_filter_date_range',
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <a href="{{ route('reports.cxc.export') }}{{ request()->getQueryString() ? '?' . request()->getQueryString() : '' }}"
                   class="btn btn-success btn-sm"
                   id="cxc_export_btn">
                    <i class="fa fa-file-excel-o"></i> Exportar CSV
                </a>
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        <div class="row mb-3">
            <div class="col-md-12">
                <div id="cxc_summary_cards" class="row"></div>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="cxc_table">
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Teléfono</th>
                        <th>N° Facturas</th>
                        <th>Saldo USD ($)</th>
                        <th>Saldo Bs</th>
                        <th>Vencido USD ($)</th>
                        <th>Por Vencer USD ($)</th>
                        <th>Primera Factura</th>
                        <th>Última Factura</th>
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
    var table = $('#cxc_table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route("reports.cxc") }}',
            data: function(d) {
                d.contact_id  = $('#cxc_filter_contact').val();
                d.status      = $('#cxc_filter_status').val();
                var range     = $('#cxc_filter_date_range').val();
                if (range) {
                    var parts    = range.split(' - ');
                    d.start_date = parts[0] ? parts[0].trim() : '';
                    d.end_date   = parts[1] ? parts[1].trim() : '';
                }
            },
        },
        columns: [
            { data: 'cliente' },
            { data: 'telefono', defaultContent: '—' },
            { data: 'total_facturas', className: 'text-center' },
            { data: 'saldo_usd', render: function(v) { return '$' + parseFloat(v || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); } },
            { data: 'saldo_bs', render: function(v) { return 'Bs ' + parseFloat(v || 0).toLocaleString('es-VE', {minimumFractionDigits:2, maximumFractionDigits:2}); } },
            { data: 'vencido_usd', render: function(v) { return '$' + parseFloat(v || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); } },
            { data: 'por_vencer_usd', render: function(v) { return '$' + parseFloat(v || 0).toLocaleString('en-US', {minimumFractionDigits:2, maximumFractionDigits:2}); } },
            { data: 'primera_factura', defaultContent: '—' },
            { data: 'ultima_factura', defaultContent: '—' },
            { data: 'acciones', orderable: false, searchable: false },
        ],
        footerCallback: function(row, data, start, end, display) {
            var api = this.api();
            var totalUsd  = api.column(3, {page:'current'}).data().reduce(function(a,b){ return parseFloat(a||0)+parseFloat(b||0); }, 0);
            var totalBs   = api.column(4, {page:'current'}).data().reduce(function(a,b){ return parseFloat(a||0)+parseFloat(b||0); }, 0);
            $('#cxc_summary_cards').html(
                '<div class="col-md-3"><div class="info-box bg-red"><span class="info-box-icon"><i class="fa fa-dollar"></i></span>' +
                '<div class="info-box-content"><span class="info-box-text">Total Saldo USD</span>' +
                '<span class="info-box-number">$' + totalUsd.toLocaleString('en-US',{minimumFractionDigits:2}) + '</span></div></div></div>' +
                '<div class="col-md-3"><div class="info-box bg-orange"><span class="info-box-icon"><i class="fa fa-money"></i></span>' +
                '<div class="info-box-content"><span class="info-box-text">Total Saldo Bs</span>' +
                '<span class="info-box-number">Bs ' + parseFloat(totalBs).toLocaleString('es-VE',{minimumFractionDigits:2}) + '</span></div></div></div>'
            );
        },
        order: [[3, 'desc']],
        language: { url: '/js/Spanish.json' },
    });

    // Filtros
    $('#cxc_filter_contact, #cxc_filter_status').on('change', function() { table.draw(); });

    $('#cxc_filter_date_range').daterangepicker({
        locale: { format: 'YYYY-MM-DD', cancelLabel: 'Limpiar' },
        autoUpdateInput: false,
    });
    $('#cxc_filter_date_range').on('apply.daterangepicker', function(ev, picker) {
        $(this).val(picker.startDate.format('YYYY-MM-DD') + ' - ' + picker.endDate.format('YYYY-MM-DD'));
        table.draw();
    });
    $('#cxc_filter_date_range').on('cancel.daterangepicker', function() {
        $(this).val('');
        table.draw();
    });

    // Actualizar URL de exportación con filtros activos
    $('#cxc_export_btn').on('click', function(e) {
        var contact  = $('#cxc_filter_contact').val() || '';
        var status   = $('#cxc_filter_status').val() || '';
        var range    = $('#cxc_filter_date_range').val() || '';
        var params   = $.param({ contact_id: contact, status: status, date_range: range });
        $(this).attr('href', '{{ route("reports.cxc.export") }}?' + params);
    });
});
</script>
@stop
