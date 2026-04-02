@extends('layouts.app')
@section('title', 'Ventas Diarias')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Reporte de Ventas Diarias
        <small class="tw-text-sm tw-text-gray-500 tw-ml-2">
            Tasa BCV: <strong>Bs {{ number_format($bcv_rate, 2) }}</strong>
        </small>
    </h1>
</section>

<section class="content no-print">

    @component('components.filters', ['title' => 'Filtros'])
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('ds_filter_date', 'Fecha:') !!}
                {!! Form::text('ds_filter_date', $date, [
                    'class'    => 'form-control',
                    'id'       => 'ds_filter_date',
                    'readonly',
                ]) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('ds_filter_location', 'Tienda/Sucursal:') !!}
                {!! Form::select('ds_filter_location', $locations, $location_id, [
                    'class'       => 'form-control select2',
                    'style'       => 'width:100%',
                    'placeholder' => 'Todas',
                    'id'          => 'ds_filter_location',
                ]) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary btn-sm" id="ds_btn_apply">
                    <i class="fa fa-search"></i> Consultar
                </button>
                <a id="ds_export_btn" href="{{ route('reports.daily_sales.export') }}?date={{ $date }}"
                   class="btn btn-success btn-sm">
                    <i class="fa fa-file-excel-o"></i> CSV
                </a>
            </div>
        </div>
    @endcomponent

    {{-- Tarjetas resumen --}}
    <div class="row">
        <div class="col-md-3">
            <div class="info-box bg-aqua">
                <span class="info-box-icon"><i class="fa fa-shopping-cart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Ventas del Día</span>
                    <span class="info-box-number">${{ number_format($summary['total_usd'], 2) }}</span>
                    <span class="progress-description">Bs {{ number_format($summary['total_bs'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-green">
                <span class="info-box-icon"><i class="fa fa-file-text-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">N° Transacciones</span>
                    <span class="info-box-number">{{ $summary['total_transactions'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="fa fa-bar-chart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Ticket Promedio</span>
                    <span class="info-box-number">
                        ${{ $summary['total_transactions'] > 0 ? number_format($summary['total_usd'] / $summary['total_transactions'], 2) : '0.00' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box {{ $summary['total_usd'] >= $summary['yesterday_usd'] ? 'bg-green' : 'bg-red' }}">
                <span class="info-box-icon">
                    <i class="fa fa-{{ $summary['total_usd'] >= $summary['yesterday_usd'] ? 'arrow-up' : 'arrow-down' }}"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">vs Ayer</span>
                    <span class="info-box-number">
                        @php
                            $diff = $summary['total_usd'] - $summary['yesterday_usd'];
                            $pct  = $summary['yesterday_usd'] > 0
                                  ? abs($diff / $summary['yesterday_usd'] * 100)
                                  : 0;
                        @endphp
                        {{ $diff >= 0 ? '+' : '' }}${{ number_format($diff, 2) }}
                    </span>
                    <span class="progress-description">{{ number_format($pct, 1) }}% — Ayer: ${{ number_format($summary['yesterday_usd'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Desglose por método de pago --}}
    <div class="row">
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-primary'])
                @slot('title') Ventas por Método de Pago @endslot
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr><th>Método</th><th>USD</th><th>Bs</th><th>N° Pagos</th></tr>
                    </thead>
                    <tbody>
                        @forelse($by_payment_method as $method)
                        <tr>
                            <td>{{ $method->method_name }}</td>
                            <td>${{ number_format($method->total_usd, 2) }}</td>
                            <td>Bs {{ number_format($method->total_bs, 2) }}</td>
                            <td class="text-center">{{ $method->count }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="text-center">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endcomponent
        </div>
        <div class="col-md-6">
            @component('components.widget', ['class' => 'box-success'])
                @slot('title') Ventas por Hora @endslot
                <canvas id="ds_hourly_chart" height="180"></canvas>
            @endcomponent
        </div>
    </div>

    {{-- Detalle de transacciones --}}
    @component('components.widget', ['class' => 'box-primary'])
        @slot('title') Detalle de Ventas — {{ \Carbon\Carbon::parse($date)->format('d/m/Y') }} @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="ds_transactions_table">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Hora</th>
                        <th>Cliente</th>
                        <th>Total USD</th>
                        <th>Total Bs</th>
                        <th>Estado Pago</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($transactions as $t)
                    <tr>
                        <td>{{ $t->invoice_no ?? '#'.$t->id }}</td>
                        <td>{{ \Carbon\Carbon::parse($t->transaction_date)->format('H:i') }}</td>
                        <td>{{ $t->contact_name ?? '—' }}</td>
                        <td>${{ number_format($t->final_total, 2) }}</td>
                        <td>Bs {{ number_format($t->total_bs, 2) }}</td>
                        <td>
                            @if($t->payment_status === 'paid')
                                <span class="label label-success">Pagada</span>
                            @elseif($t->payment_status === 'partial')
                                <span class="label label-warning">Parcial</span>
                            @else
                                <span class="label label-danger">Pendiente</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('sells.show', $t->id) }}" target="_blank" class="btn btn-xs btn-info">
                                <i class="fa fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" class="text-center">Sin ventas en esta fecha.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endcomponent
</section>
@stop

@section('javascript')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    // Datepicker
    $('#ds_filter_date').datepicker({ dateFormat: 'yy-mm-dd', changeMonth: true, changeYear: true });
    $('#ds_btn_apply').on('click', function() {
        var date     = $('#ds_filter_date').val();
        var location = $('#ds_filter_location').val();
        var params   = '?date=' + date + '&location_id=' + location;
        $('#ds_export_btn').attr('href', '{{ route("reports.daily_sales.export") }}' + params);
        window.location.href = '{{ route("reports.daily_sales") }}' + params;
    });

    // DataTable
    if ($('#ds_transactions_table tbody tr').length) {
        $('#ds_transactions_table').DataTable({
            order: [[1, 'asc']],
            language: { url: '/js/Spanish.json' },
        });
    }

    // Gráfico por hora
    var hourlyData = @json($hourly_data);
    if (hourlyData && document.getElementById('ds_hourly_chart')) {
        var ctx = document.getElementById('ds_hourly_chart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: hourlyData.map(function(h) { return h.hour + ':00'; }),
                datasets: [{
                    label: 'USD',
                    data: hourlyData.map(function(h) { return h.total_usd; }),
                    backgroundColor: 'rgba(0, 192, 239, 0.7)',
                }],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true } },
            },
        });
    }
});
</script>
@stop
