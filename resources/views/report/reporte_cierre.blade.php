@extends('layouts.app')
@section('title', 'Reporte de Cierre')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Reporte de Cierre
        <small class="tw-text-sm tw-text-gray-500 tw-ml-2">Dinámico por período</small>
    </h1>
</section>

<section class="content no-print">
    @component('components.filters', ['title' => 'Filtros'])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('rc_filter_date_range', 'Período:') !!}
                {!! Form::text('rc_filter_date_range', \Carbon\Carbon::parse($start_date)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($end_date)->format('d/m/Y'), [
                    'class' => 'form-control',
                    'id' => 'rc_filter_date_range',
                    'readonly',
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('rc_filter_location', 'Tienda/Sucursal:') !!}
                {!! Form::select('rc_filter_location', $locations, $location_id, [
                    'class' => 'form-control select2',
                    'style' => 'width:100%',
                    'placeholder' => 'Todas',
                    'id' => 'rc_filter_location',
                ]) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary btn-sm" id="rc_btn_apply">
                    <i class="fa fa-search"></i> Consultar
                </button>
                <a id="rc_export_btn" class="btn btn-success btn-sm"
                   href="{{ route('reports.closure_report.export', ['start_date' => $start_date, 'end_date' => $end_date, 'location_id' => $location_id]) }}">
                    <i class="fa fa-file-excel-o"></i> CSV
                </a>
            </div>
        </div>
    @endcomponent

    @component('components.widget', ['class' => 'box-primary'])
        @slot('title')
            Tabla de Cierre (editable)
        @endslot

        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="reporte_cierre_table" style="width:100%;">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo</th>
                        <th>Documento</th>
                        <th>Monto Pagado</th>
                        <th>TipoP</th>
                        <th>Código Cliente</th>
                        <th>Nombre Cliente</th>
                        <th>Usuario</th>
                        <th>Vendedor</th>
                        <th>TotalDoc</th>
                        <th>Contado</th>
                        <th>Crédito</th>
                        <th>Antic</th>
                        <th>Dolar</th>
                        <th>Transf Dola</th>
                        <th>Efectivo Bs</th>
                        <th>Transf Bs</th>
                        <th>Tasa</th>
                        <th>Dcto%</th>
                        <th>Comentario</th>
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
    if ($('#rc_filter_date_range').length) {
        $('#rc_filter_date_range').daterangepicker(dateRangeSettings, function(start, end) {
            $('#rc_filter_date_range').val(start.format(moment_date_format) + ' - ' + end.format(moment_date_format));
            cierre_table.ajax.reload();
        });
        $('#rc_filter_date_range').on('cancel.daterangepicker', function() {
            $(this).val('');
            cierre_table.ajax.reload();
        });
    }

    var cierre_table = $('#reporte_cierre_table').DataTable({
        processing: true,
        serverSide: true,
        scrollX: true,
        ajax: {
            url: '{{ route("reports.closure_report") }}',
            data: function(d) {
                var drp = $('#rc_filter_date_range').val();
                if (drp) {
                    var date = drp.split(' - ');
                    d.start_date = moment(date[0], moment_date_format).format('YYYY-MM-DD');
                    d.end_date   = moment(date[1], moment_date_format).format('YYYY-MM-DD');
                }
                d.location_id = $('#rc_filter_location').val();
            }
        },
        columns: [
            { data: 'fecha', name: 'fecha' },
            { data: 'tipo', name: 'tipo' },
            { data: 'documento', name: 'documento' },
            { data: 'monto_pagado', name: 'monto_pagado', className: 'text-right' },
            { data: 'tipo_pagado', name: 'tipo_pagado' },
            { data: 'codigo_cliente', name: 'codigo_cliente' },
            { data: 'nombre_cliente', name: 'nombre_cliente' },
            { data: 'usuario', name: 'usuario' },
            { data: 'vendedor', name: 'vendedor' },
            { data: 'total_doc', name: 'total_doc', className: 'text-right' },
            { data: 'contado', name: 'contado', className: 'text-right' },
            { data: 'credito', name: 'credito', className: 'text-right' },
            { data: 'antic', name: 'antic', className: 'text-right' },
            { data: 'dolar', name: 'dolar', className: 'text-right' },
            { data: 'transf_dola', name: 'transf_dola', className: 'text-right' },
            { data: 'efectivo_bs', name: 'efectivo_bs', className: 'text-right' },
            { data: 'transf_bs', name: 'transf_bs', className: 'text-right' },
            { data: 'tasa_editable', name: 'tasa', orderable: false, searchable: false },
            { data: 'dcto_editable', name: 'dcto_porcentaje', orderable: false, searchable: false },
            { data: 'comentario_editable', name: 'comentario_editable', orderable: false, searchable: false }
        ],
        order: [[0, 'asc']],
        language: { url: '/js/Spanish.json' }
    });

    function syncExportHref() {
        var drp = $('#rc_filter_date_range').val();
        var startDate = '';
        var endDate = '';
        if (drp) {
            var date = drp.split(' - ');
            startDate = moment(date[0], moment_date_format).format('YYYY-MM-DD');
            endDate = moment(date[1], moment_date_format).format('YYYY-MM-DD');
        }
        var locationId = $('#rc_filter_location').val() || '';
        var href = '{{ route("reports.closure_report.export") }}?start_date=' + startDate + '&end_date=' + endDate + '&location_id=' + locationId;
        $('#rc_export_btn').attr('href', href);
    }

    $('#rc_btn_apply, #rc_filter_location').on('click change', function(e) {
        if (e.type === 'click') {
            e.preventDefault();
        }
        syncExportHref();
        cierre_table.ajax.reload();
    });

    $(document).on('click', '.btn-save-cierre', function() {
        var id = $(this).data('id');
        var comentario = $('.cierre-comentario[data-id="' + id + '"]').val();
        var tasa = $('.cierre-tasa[data-id="' + id + '"]').val();
        var dcto = $('.cierre-dcto[data-id="' + id + '"]').val();
        var $btn = $(this);
        $btn.prop('disabled', true);

        function toFloat(raw) {
            var v = (raw || '').toString().trim();
            if (v === '') {
                return '';
            }
            v = v.replace(/\./g, '').replace(',', '.');
            var n = parseFloat(v);
            return isNaN(n) ? '' : n;
        }

        $.ajax({
            method: 'POST',
            url: '{{ route("reports.closure_report.update_comment") }}',
            data: {
                _token: $('meta[name="csrf-token"]').attr('content'),
                transaction_id: id,
                comentario: comentario,
                tasa: toFloat(tasa),
                dcto_porcentaje: toFloat(dcto)
            }
        }).done(function(resp) {
            if (resp && resp.ok) {
                toastr.success(resp.message || 'Guardado');
            } else {
                toastr.error((resp && resp.message) ? resp.message : 'No se pudo guardar');
            }
        }).fail(function(xhr) {
            toastr.error((xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : 'Error al actualizar comentario');
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });

    syncExportHref();
});
</script>
@stop
