@extends('layouts.app')
@section('title', 'Ventas Mensuales')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Reporte de Ventas Mensual
        <small class="tw-text-sm tw-text-gray-500 tw-ml-2">
            Tasa BCV: <strong>Bs {{ number_format($bcv_rate, 2) }}</strong>
        </small>
    </h1>
</section>

<section class="content no-print">

    @component('components.filters', ['title' => 'Filtros'])
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ms_filter_month', 'Mes:') !!}
                {!! Form::select('ms_filter_month', [
                    1=>'Enero', 2=>'Febrero', 3=>'Marzo', 4=>'Abril', 5=>'Mayo', 6=>'Junio',
                    7=>'Julio', 8=>'Agosto', 9=>'Septiembre', 10=>'Octubre', 11=>'Noviembre', 12=>'Diciembre',
                ], $month, ['class'=>'form-control select2','style'=>'width:100%','id'=>'ms_filter_month']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                {!! Form::label('ms_filter_year', 'Año:') !!}
                {!! Form::select('ms_filter_year', array_combine(range(date('Y'), date('Y')-3), range(date('Y'), date('Y')-3)), $year, ['class'=>'form-control select2','style'=>'width:100%','id'=>'ms_filter_year']) !!}
            </div>
        </div>
        <div class="col-md-3">
            <div class="form-group">
                {!! Form::label('ms_filter_location', 'Sucursal:') !!}
                {!! Form::select('ms_filter_location', $locations, $location_id, ['class'=>'form-control select2','style'=>'width:100%','placeholder'=>'Todas','id'=>'ms_filter_location']) !!}
            </div>
        </div>
        <div class="col-md-2">
            <div class="form-group">
                <label>&nbsp;</label><br>
                <button class="btn btn-primary btn-sm" id="ms_btn_apply">
                    <i class="fa fa-search"></i> Consultar
                </button>
                <a id="ms_export_btn" href="{{ route('reports.monthly_sales.export') }}?month={{ $month }}&year={{ $year }}"
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
                    <span class="info-box-text">Ventas del Mes (USD)</span>
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
                    <span class="progress-description">Días con ventas: {{ $summary['days_with_sales'] }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box bg-yellow">
                <span class="info-box-icon"><i class="fa fa-bar-chart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Promedio Diario</span>
                    <span class="info-box-number">
                        ${{ $summary['days_with_sales'] > 0 ? number_format($summary['total_usd'] / $summary['days_with_sales'], 2) : '0.00' }}
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="info-box {{ $summary['total_usd'] >= $summary['prev_month_usd'] ? 'bg-green' : 'bg-red' }}">
                <span class="info-box-icon">
                    <i class="fa fa-{{ $summary['total_usd'] >= $summary['prev_month_usd'] ? 'arrow-up' : 'arrow-down' }}"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">vs Mes Anterior</span>
                    <span class="info-box-number">
                        @php $diff = $summary['total_usd'] - $summary['prev_month_usd']; @endphp
                        {{ $diff >= 0 ? '+' : '' }}${{ number_format($diff, 2) }}
                    </span>
                    <span class="progress-description">Mes ant.: ${{ number_format($summary['prev_month_usd'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Gráfico de tendencia + tabla diaria --}}
    <div class="row">
        <div class="col-md-8">
            @component('components.widget', ['class' => 'box-primary'])
                @slot('title') Ventas por Día — {{ \Carbon\Carbon::createFromDate($year, $month, 1)->locale('es')->monthName }} {{ $year }} @endslot
                <canvas id="ms_daily_chart" height="120"></canvas>
            @endcomponent
        </div>
        <div class="col-md-4">
            @component('components.widget', ['class' => 'box-success'])
                @slot('title') Top Métodos de Pago @endslot
                <table class="table table-condensed table-bordered">
                    <thead><tr><th>Método</th><th>USD</th></tr></thead>
                    <tbody>
                        @forelse($by_payment_method as $method)
                        <tr>
                            <td>{{ $method->method_name }}</td>
                            <td>${{ number_format($method->total_usd, 2) }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="2" class="text-center">Sin datos</td></tr>
                        @endforelse
                    </tbody>
                </table>
            @endcomponent
        </div>
    </div>

    {{-- Tabla por día --}}
    @component('components.widget', ['class' => 'box-primary'])
        @slot('title') Detalle por Día @endslot
        <div class="table-responsive">
            <table class="table table-bordered table-striped" id="ms_daily_table">
                <thead>
                    <tr>
                        <th>Día</th>
                        <th>Fecha</th>
                        <th>Transacciones</th>
                        <th>Total USD</th>
                        <th>Total Bs</th>
                        <th>Acumulado USD</th>
                    </tr>
                </thead>
                <tbody>
                    @php $acum = 0; @endphp
                    @forelse($daily_data as $day)
                    @php $acum += $day->total_usd; @endphp
                    <tr>
                        <td class="text-center">{{ \Carbon\Carbon::parse($day->sale_date)->format('d') }}</td>
                        <td>{{ \Carbon\Carbon::parse($day->sale_date)->format('d/m/Y') }}</td>
                        <td class="text-center">{{ $day->transactions }}</td>
                        <td>${{ number_format($day->total_usd, 2) }}</td>
                        <td>Bs {{ number_format($day->total_bs, 2) }}</td>
                        <td>${{ number_format($acum, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center">Sin ventas en este mes.</td></tr>
                    @endforelse
                </tbody>
                @if(count($daily_data) > 0)
                <tfoot>
                    <tr class="info">
                        <th colspan="2">TOTAL</th>
                        <th>{{ $summary['total_transactions'] }}</th>
                        <th>${{ number_format($summary['total_usd'], 2) }}</th>
                        <th>Bs {{ number_format($summary['total_bs'], 2) }}</th>
                        <th></th>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    @endcomponent
</section>
@stop

@section('javascript')
<script src="https://cdn.jsdelivr.net/npm/chart.js@3/dist/chart.min.js"></script>
<script>
$(document).ready(function() {
    $('#ms_btn_apply').on('click', function() {
        var params = '?month=' + $('#ms_filter_month').val()
            + '&year='  + $('#ms_filter_year').val()
            + '&location_id=' + ($('#ms_filter_location').val() || '');
        $('#ms_export_btn').attr('href', '{{ route("reports.monthly_sales.export") }}' + params);
        window.location.href = '{{ route("reports.monthly_sales") }}' + params;
    });

    // DataTable
    if ($('#ms_daily_table tbody tr').length) {
        $('#ms_daily_table').DataTable({
            paging: false,
            order: [[0, 'asc']],
            language: { url: '/js/Spanish.json' },
        });
    }

    // Gráfico de tendencia diaria
    var dailyData = @json($daily_data);
    if (dailyData.length && document.getElementById('ms_daily_chart')) {
        var ctx = document.getElementById('ms_daily_chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dailyData.map(function(d) { return d.sale_date.substring(8, 10); }),
                datasets: [
                    {
                        label: 'Ventas USD',
                        data: dailyData.map(function(d) { return parseFloat(d.total_usd); }),
                        borderColor: '#00b5e2',
                        backgroundColor: 'rgba(0,181,226,0.10)',
                        fill: true,
                        tension: 0.3,
                    },
                ],
            },
            options: {
                responsive: true,
                plugins: { legend: { display: true } },
                scales: { y: { beginAtZero: true } },
            },
        });
    }
});
</script>
@stop
