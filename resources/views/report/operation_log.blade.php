@extends('layouts.app')
@section('title', 'Auditoría Operativa')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Auditoría Operativa
        <small class="tw-text-sm tw-text-gray-500 tw-ml-2">Bitácora de operaciones del sistema</small>
    </h1>
</section>

<section class="content">

    {{-- Filtros --}}
    @component('components.filters', ['title' => 'Filtros'])
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ol_start_date', 'Desde:') !!}
                {!! Form::text('ol_start_date', date('Y-m-d'), ['class' => 'form-control', 'id' => 'ol_start_date']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ol_end_date', 'Hasta:') !!}
                {!! Form::text('ol_end_date', date('Y-m-d'), ['class' => 'form-control', 'id' => 'ol_end_date']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ol_module', 'Módulo:') !!}
                {!! Form::select('ol_module', $modules, '', ['class' => 'form-control select2', 'id' => 'ol_module', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ol_action', 'Acción:') !!}
                {!! Form::select('ol_action', $actions, '', ['class' => 'form-control select2', 'id' => 'ol_action', 'style' => 'width:100%']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ol_user', 'Usuario:') !!}
                {!! Form::text('ol_user', '', ['class' => 'form-control', 'id' => 'ol_user', 'placeholder' => 'Nombre de usuario']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ol_ref', 'Referencia:') !!}
                {!! Form::text('ol_ref', '', ['class' => 'form-control', 'id' => 'ol_ref', 'placeholder' => 'Nro. factura / ref.']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary btn-sm" id="ol_btn_apply">
                    <i class="fa fa-search"></i> Consultar
                </button>
                <button class="btn btn-default btn-sm" id="ol_btn_reset">
                    <i class="fa fa-times"></i> Limpiar
                </button>
                <a id="ol_export_btn"
                   href="{{ route('reports.auditoria_operativa.export') }}?start_date={{ date('Y-m-d') }}&end_date={{ date('Y-m-d') }}"
                   class="btn btn-success btn-sm">
                    <i class="fa fa-file-excel-o"></i> CSV
                </a>
            </div>
        </div>
    @endcomponent

    {{-- Tabla --}}
    <div class="row">
        <div class="col-md-12">
            <div class="box box-solid">
                <div class="box-header with-border">
                    <h3 class="box-title">Registros de Auditoría</h3>
                    <div class="box-tools pull-right">
                        <span id="ol_record_count" class="label label-primary"></span>
                    </div>
                </div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-hover table-bordered table-striped" id="ol_table">
                        <thead>
                            <tr>
                                <th style="width:120px">Fecha/Hora</th>
                                <th style="width:90px">Módulo</th>
                                <th style="width:80px">Acción</th>
                                <th>Referencia</th>
                                <th>Sede</th>
                                <th>Usuario</th>
                                <th style="width:110px">Monto</th>
                                <th style="width:110px">IP</th>
                                <th>Cambios</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="9" class="text-center text-muted">Aplique filtros y presione <strong>Consultar</strong></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</section>
@endsection

@section('javascript')
<script>
$(function() {

    var olTable = null;

    // Inicializar datepickers
    $('#ol_start_date, #ol_end_date').datepicker({
        dateFormat: 'yy-mm-dd',
        changeMonth: true,
        changeYear: true,
    });

    // Inicializar select2
    $('#ol_module, #ol_action').select2();

    function loadTable() {
        var params = {
            ajax:       true,
            start_date: $('#ol_start_date').val(),
            end_date:   $('#ol_end_date').val(),
            module:     $('#ol_module').val(),
            action:     $('#ol_action').val(),
            user_name:  $('#ol_user').val(),
            entity_ref: $('#ol_ref').val(),
        };

        if (olTable) {
            olTable.destroy();
            $('#ol_table tbody').html('');
        }

        olTable = $('#ol_table').DataTable({
            processing:  true,
            serverSide:  true,
            ajax: {
                url:  '{{ route("reports.auditoria_operativa") }}',
                data: params,
            },
            columns: [
                { data: 'occurred_at',   name: 'occurred_at',   orderable: false },
                { data: 'module',        name: 'module',        orderable: false },
                { data: 'action',        name: 'action',        orderable: false },
                { data: 'entity_ref',    name: 'entity_ref',    orderable: false },
                { data: 'location_name', name: 'location_name', orderable: false },
                { data: 'user_name',     name: 'user_name',     orderable: false },
                { data: 'amount',        name: 'amount',        orderable: false, className: 'text-right' },
                { data: 'ip_address',    name: 'ip_address',    orderable: false },
                { data: 'changes',       name: 'changes',       orderable: false },
            ],
            pageLength: 25,
            language: {
                url: '/js/datatables-es.json',
                processing: '<i class="fa fa-spinner fa-spin"></i> Cargando...',
            },
            drawCallback: function(settings) {
                var info = this.api().page.info();
                $('#ol_record_count').text(info.recordsTotal + ' registro(s)');
            },
        });
    }

    $('#ol_btn_apply').on('click', function() {
        var params = '?export=csv'
            + '&start_date=' + $('#ol_start_date').val()
            + '&end_date='   + $('#ol_end_date').val()
            + '&module='     + ($('#ol_module').val() || '')
            + '&action='     + ($('#ol_action').val() || '')
            + '&user_name='  + encodeURIComponent($('#ol_user').val() || '')
            + '&entity_ref=' + encodeURIComponent($('#ol_ref').val() || '');
        $('#ol_export_btn').attr('href', '{{ route("reports.auditoria_operativa.export") }}' + params);
        loadTable();
    });

    $('#ol_btn_reset').on('click', function() {
        $('#ol_start_date').val('{{ date("Y-m-d") }}');
        $('#ol_end_date').val('{{ date("Y-m-d") }}');
        $('#ol_module').val('').trigger('change');
        $('#ol_action').val('').trigger('change');
        $('#ol_user').val('');
        $('#ol_ref').val('');
        if (olTable) {
            olTable.destroy();
            $('#ol_table tbody').html('<tr><td colspan="9" class="text-center text-muted">Aplique filtros y presione <strong>Consultar</strong></td></tr>');
            olTable = null;
            $('#ol_record_count').text('');
        }
    });

    // Carga inicial automática del día actual
    loadTable();
});
</script>
@endsection
