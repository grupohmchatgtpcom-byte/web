@extends('layouts.app')
@section('title', 'Consolidado de Pedidos')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Consolidado de Pedidos
    </h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-12">

            {{-- Alertas de sesión --}}
            @if(session('status'))
                @if(session('status.success'))
                    <div class="alert alert-success alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        {{ session('status.msg') }}
                    </div>
                @else
                    <div class="alert alert-danger alert-dismissible">
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                        {{ session('status.msg') }}
                    </div>
                @endif
            @endif

            @if(!$has_policies_table || !$has_policies_data)
                <div class="alert alert-info">
                    <strong><i class="fa fa-info-circle"></i> Modo automático (sin políticas configuradas):</strong>
                    Se usará la <strong>Cantidad de alerta</strong> de cada producto como mínimo, y el doble de ese valor como máximo.
                    Solo se reponen productos cuyo stock en tienda esté <em>por debajo</em> de su alerta.
                    @if($has_policies_table && !$has_policies_data)
                        <br><small class="text-muted">La tabla de políticas existe pero no tiene datos activos para este negocio.</small>
                    @endif
                </div>
            @endif

            {{-- Formulario de cálculo --}}
            <div class="box box-primary">
                <div class="box-header with-border">
                    <h3 class="box-title">
                        <i class="fa fa-calculator"></i>
                        Calcular distribución de inventario
                    </h3>
                </div>
                <div class="box-body">
                    <div class="row">
                        <div class="col-md-5">
                            <div class="form-group">
                                <label for="warehouse_location_id">
                                    <strong>Almacén origen (Almacen GHM)</strong>
                                </label>
                                <select name="warehouse_location_id" id="warehouse_location_id" class="form-control select2">
                                    <option value="">-- Seleccionar almacén --</option>
                                    @foreach($business_locations as $id => $name)
                                        <option value="{{ $id }}" {{ $id == $default_warehouse_id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="col-md-7" style="padding-top: 25px;">
                            <button id="btn_calcular" type="button" class="btn btn-primary btn-lg">
                                <i class="fa fa-refresh"></i>
                                Calcular consolidado
                            </button>
                            <button id="btn_exportar" type="button" class="btn btn-success btn-lg" style="margin-left: 8px;">
                                <i class="fa fa-file-excel-o"></i>
                                Exportar Excel
                            </button>
                        </div>
                    </div>

                    <div class="row" style="margin-top:8px;">
                        <div class="col-md-12">
                            <small class="text-muted">
                                <i class="fa fa-info-circle"></i>
                                El cálculo distribuye el inventario del almacén a las tiendas en
                                <strong>orden alfabético</strong> según sus políticas de mínimo/máximo.
                                Si el stock no alcanza para todas, las tiendas que vienen después alphabéticamente
                                pueden quedar sin recibir.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Spinner de carga --}}
            <div id="loading_spinner" style="display:none; text-align:center; padding: 40px;">
                <i class="fa fa-spinner fa-spin fa-3x text-primary"></i>
                <p style="margin-top:10px;" class="text-muted">Calculando distribución...</p>
            </div>

            {{-- Resultados (ocultos hasta que se calcule) --}}
            <div id="resultado_contenedor" style="display:none;">

                {{-- Resumen estadístico --}}
                <div class="row" id="resumen_stats" style="margin-bottom: 15px;">
                    <div id="modo_fallback_aviso" style="display:none;" class="col-md-12">
                        <div class="alert alert-info" style="margin-bottom:10px;">
                            <i class="fa fa-info-circle"></i>
                            <strong>Modo automático:</strong> Distribución basada en <em>Cantidad de alerta</em> de cada producto.
                            El máximo se estimó como el doble del mínimo. Para usar mínimos/máximos personalizados por tienda, configura las políticas de despacho.
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="info-box bg-blue">
                            <span class="info-box-icon"><i class="fa fa-cube"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Productos a despachar</span>
                                <span class="info-box-number" id="stat_productos">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="info-box bg-green">
                            <span class="info-box-icon"><i class="fa fa-store"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Tiendas con despacho</span>
                                <span class="info-box-number" id="stat_tiendas">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="info-box bg-yellow">
                            <span class="info-box-icon"><i class="fa fa-cubes"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Total unidades a enviar</span>
                                <span class="info-box-number" id="stat_unidades">0</span>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-3">
                        <div class="info-box bg-red">
                            <span class="info-box-icon"><i class="fa fa-exclamation-triangle"></i></span>
                            <div class="info-box-content">
                                <span class="info-box-text">Unidades faltantes</span>
                                <span class="info-box-number" id="stat_faltantes">0</span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Tabs dinámicos --}}
                <div class="box box-solid">
                    <div class="box-header with-border">
                        <h3 class="box-title"><i class="fa fa-list"></i> Detalle por tienda</h3>
                    </div>
                    <div class="box-body">
                        <div id="tabs_container">
                            <ul class="nav nav-tabs" id="tabs_list" role="tablist" style="flex-wrap: wrap;">
                            </ul>
                            <div class="tab-content" id="tabs_content" style="margin-top: 15px;">
                            </div>
                        </div>
                    </div>
                </div>

            </div>{{-- fin resultado_contenedor --}}

        </div>
    </div>
</section>

{{-- Formulario oculto para exportar --}}
<form id="form_exportar" action="{{ route('consolidado.exportar') }}" method="GET" style="display:none;">
    <input type="hidden" name="warehouse_location_id" id="export_warehouse_id">
</form>
@stop

@section('javascript')
<script>
$(document).ready(function () {

    var calcularUrl = '{{ route("consolidado.calcular") }}';
    var resultadoData = null;

    // ─── Calcular ──────────────────────────────────────────────────────────────
    $('#btn_calcular').on('click', function () {
        var warehouseId = $('#warehouse_location_id').val();
        if (!warehouseId) {
            alert('Selecciona el almacén origen antes de calcular.');
            return;
        }

        $('#resultado_contenedor').hide();
        $('#loading_spinner').show();
        $('#btn_calcular').prop('disabled', true);

        $.ajax({
            url: calcularUrl,
            type: 'GET',
            data: { warehouse_location_id: warehouseId },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
            success: function (response) {
                $('#loading_spinner').hide();
                $('#btn_calcular').prop('disabled', false);

                if (!response.success) {
                    toastr.error(response.msg || 'Error al calcular.');
                    return;
                }

                resultadoData = response;
                renderResultados(response);
                $('#resultado_contenedor').show();
                $('html, body').animate({ scrollTop: $('#resultado_contenedor').offset().top - 60 }, 400);
            },
            error: function (xhr) {
                $('#loading_spinner').hide();
                $('#btn_calcular').prop('disabled', false);
                var msg = 'Error de conexión al calcular.';
                try { msg = JSON.parse(xhr.responseText).msg || msg; } catch(e){}
                toastr.error(msg);
            }
        });
    });

    // ─── Exportar Excel ────────────────────────────────────────────────────────
    $('#btn_exportar').on('click', function () {
        var warehouseId = $('#warehouse_location_id').val();
        if (!warehouseId) {
            alert('Selecciona el almacén origen antes de exportar.');
            return;
        }
        $('#export_warehouse_id').val(warehouseId);
        $('#form_exportar').submit();
    });

    // ─── Renderizar resultados ─────────────────────────────────────────────────
    function renderResultados(data) {
        var summary = data.summary;
        var storeResults = data.results_by_store;
        var shortages = data.shortages_by_store;

        // Aviso modo fallback
        if (summary.modo_fallback) {
            $('#modo_fallback_aviso').show();
        } else {
            $('#modo_fallback_aviso').hide();
        }

        // Stats
        $('#stat_productos').text(summary.total_productos);
        $('#stat_tiendas').text(summary.total_tiendas_con_despacho);
        $('#stat_unidades').text(formatNum(summary.total_unidades_a_despachar));
        $('#stat_faltantes').text(formatNum(summary.total_unidades_con_faltante));

        var tabsList = $('#tabs_list').empty();
        var tabsContent = $('#tabs_content').empty();

        var storeNames = Object.keys(storeResults).sort();
        var tabIndex = 0;

        // Tab por cada tienda
        storeNames.forEach(function (storeName, idx) {
            var tabId = 'tab_store_' + idx;
            var isActive = idx === 0 ? 'active' : '';
            var items = storeResults[storeName];
            var totalUnidades = items.reduce(function(s, i){ return s + i.a_enviar; }, 0);
            var tieneFaltante = shortages.hasOwnProperty(storeName);

            tabsList.append(
                '<li class="' + isActive + '" role="presentation">' +
                '<a href="#' + tabId + '" data-toggle="tab" role="tab">' +
                '<i class="fa fa-store text-green"></i> ' + escHtml(storeName) +
                ' <span class="badge bg-blue">' + items.length + ' prod.</span>' +
                (tieneFaltante ? ' <span class="badge bg-red" title="Tiene faltantes"><i class="fa fa-warning"></i></span>' : '') +
                '</a></li>'
            );

            tabsContent.append(
                '<div role="tabpanel" class="tab-pane ' + isActive + '" id="' + tabId + '">' +
                buildStoreTable(storeName, items, totalUnidades) +
                '</div>'
            );
            tabIndex++;
        });

        // Tab de faltantes
        var shortageStores = Object.keys(shortages).sort();
        if (shortageStores.length > 0) {
            var tabIdF = 'tab_faltantes';
            tabsList.append(
                '<li role="presentation">' +
                '<a href="#' + tabIdF + '" data-toggle="tab" role="tab" style="color:#c0392b; font-weight:bold;">' +
                '<i class="fa fa-exclamation-triangle text-red"></i> Faltantes ' +
                '<span class="badge bg-red">' + shortageStores.length + ' tienda(s)</span>' +
                '</a></li>'
            );
            tabsContent.append(
                '<div role="tabpanel" class="tab-pane" id="' + tabIdF + '">' +
                buildShortageTable(shortages, shortageStores) +
                '</div>'
            );
        } else {
            // Tab verde "Sin faltantes"
            tabsList.append(
                '<li role="presentation">' +
                '<a href="#tab_ok" data-toggle="tab" role="tab" style="color:#27ae60; font-weight:bold;">' +
                '<i class="fa fa-check-circle text-green"></i> Sin faltantes' +
                '</a></li>'
            );
            tabsContent.append(
                '<div role="tabpanel" class="tab-pane" id="tab_ok">' +
                '<div class="alert alert-success" style="margin-top:15px;">' +
                '<i class="fa fa-check-circle"></i> El almacén tenía stock suficiente para cubrir todas las necesidades. ¡Sin faltantes!' +
                '</div></div>'
            );
        }

        // Activar primer tab
        $('#tabs_list a:first').tab('show');
    }

    function buildStoreTable(storeName, items, totalUnidades) {
        var html = '<div class="table-responsive">';
        html += '<p style="margin-bottom:8px;"><strong><i class="fa fa-map-marker text-blue"></i> ' +
                escHtml(storeName) + '</strong> — ' + items.length + ' producto(s) — ' +
                '<strong>' + formatNum(totalUnidades) + '</strong> unidades totales a enviar</p>';

        html += '<table class="table table-bordered table-condensed table-hover" style="font-size:13px;">';
        html += '<thead class="bg-blue" style="color:#fff;">';
        html += '<tr>';
        html += '<th>Producto</th>';
        html += '<th>SKU</th>';
        html += '<th class="text-center">Stock tienda</th>';
        html += '<th class="text-center">Mínimo</th>';
        html += '<th class="text-center">Máximo</th>';
        html += '<th class="text-center">Requerido</th>';
        html += '<th class="text-center tw-font-bold">A enviar</th>';
        html += '<th class="text-center">Stock final est.</th>';
        html += '<th class="text-center">Estado</th>';
        html += '</tr>';
        html += '</thead><tbody>';

        items.forEach(function (item) {
            var rowClass = item.tiene_faltante ? 'warning' : '';
            var estadoBadge = item.tiene_faltante
                ? '<span class="label label-warning"><i class="fa fa-warning"></i> Parcial (falta ' + formatNum(item.faltante) + ')</span>'
                : '<span class="label label-success"><i class="fa fa-check"></i> OK</span>';
            var productLabel = item.variation_name && item.variation_name !== 'DUMMY'
                ? escHtml(item.product_name) + ' <small class="text-muted">(' + escHtml(item.variation_name) + ')</small>'
                : escHtml(item.product_name);

            html += '<tr class="' + rowClass + '">';
            html += '<td>' + productLabel + '</td>';
            html += '<td><code>' + escHtml(item.sku || '') + '</code></td>';
            html += '<td class="text-center">' + formatNum(item.stock_tienda) + '</td>';
            html += '<td class="text-center">' + formatNum(item.minimo) + '</td>';
            html += '<td class="text-center">' + formatNum(item.maximo) + '</td>';
            html += '<td class="text-center">' + formatNum(item.requerido) + '</td>';
            html += '<td class="text-center tw-font-bold text-primary"><strong>' + formatNum(item.a_enviar) + '</strong></td>';
            html += '<td class="text-center">' + formatNum(item.stock_final_est) + '</td>';
            html += '<td class="text-center">' + estadoBadge + '</td>';
            html += '</tr>';
        });

        html += '</tbody></table></div>';
        return html;
    }

    function buildShortageTable(shortages, shortageStores) {
        var html = '<div class="alert alert-warning" style="margin-bottom:15px;">';
        html += '<strong><i class="fa fa-exclamation-triangle"></i> Resumen de faltantes:</strong> ';
        html += 'Las siguientes tiendas no recibieron todo lo necesario porque el stock del almacén no fue suficiente. ';
        html += 'Se respetó el <strong>orden alfabético</strong>, por lo que las tiendas al final del abecedario tienen prioridad menor.';
        html += '</div>';

        shortageStores.forEach(function (storeName) {
            var items = shortages[storeName];
            var totalFaltante = items.reduce(function(s, i){ return s + i.faltante; }, 0);

            html += '<h4 style="margin-top:20px; border-bottom:2px solid #e74c3c; padding-bottom:5px; color:#c0392b;">';
            html += '<i class="fa fa-store"></i> ' + escHtml(storeName);
            html += ' <small class="text-muted">— ' + items.length + ' producto(s) faltantes, ' + formatNum(totalFaltante) + ' unidades totales faltantes</small>';
            html += '</h4>';

            html += '<div class="table-responsive">';
            html += '<table class="table table-bordered table-condensed table-hover" style="font-size:13px;">';
            html += '<thead class="bg-red" style="color:#fff;">';
            html += '<tr>';
            html += '<th>Producto</th>';
            html += '<th>SKU</th>';
            html += '<th class="text-center">Stock tienda</th>';
            html += '<th class="text-center">Mín/Máx</th>';
            html += '<th class="text-center">Requerido</th>';
            html += '<th class="text-center">Dado</th>';
            html += '<th class="text-center tw-text-red-700">Faltante</th>';
            html += '</tr>';
            html += '</thead><tbody>';

            items.forEach(function (item) {
                var productLabel = item.variation_name && item.variation_name !== 'DUMMY'
                    ? escHtml(item.product_name) + ' <small class="text-muted">(' + escHtml(item.variation_name) + ')</small>'
                    : escHtml(item.product_name);

                html += '<tr class="danger">';
                html += '<td>' + productLabel + '</td>';
                html += '<td><code>' + escHtml(item.sku || '') + '</code></td>';
                html += '<td class="text-center">' + formatNum(item.stock_tienda) + '</td>';
                html += '<td class="text-center">' + formatNum(item.minimo) + ' / ' + formatNum(item.maximo) + '</td>';
                html += '<td class="text-center">' + formatNum(item.requerido) + '</td>';
                html += '<td class="text-center">' + formatNum(item.dado) + '</td>';
                html += '<td class="text-center tw-font-bold" style="color:#c0392b;"><strong>' + formatNum(item.faltante) + '</strong></td>';
                html += '</tr>';
            });

            html += '</tbody></table></div>';
        });

        return html;
    }

    function formatNum(val) {
        var n = parseFloat(val) || 0;
        return n % 1 === 0
            ? n.toLocaleString('es-VE')
            : n.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
});
</script>
@endsection
