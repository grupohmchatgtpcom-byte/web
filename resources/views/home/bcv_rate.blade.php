@extends('layouts.app')
@section('title', 'Tasa del dia')

@section('content')
    <section class="content">
        <div class="row">
            <div class="col-md-12">
                <div class="box box-solid">
                    <div class="box-header with-border">
                        <h3 class="box-title">Tasa del dia</h3>
                    </div>
                    <div class="box-body">
                        <div class="row" style="margin-bottom: 15px;">
                            <div class="col-md-12">
                                <form action="{{ route('home.bcv_rate.fetch') }}" method="POST" style="display: inline-block;">
                                    @csrf
                                    <button type="submit" class="btn btn-success">Traer tasa desde BCV</button>
                                </form>
                            </div>
                        </div>

                        <form action="{{ route('home.bcv_rate.store') }}" method="POST" class="row" style="margin-bottom: 20px;">
                            @csrf
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="rate_date">Fecha</label>
                                    <input type="date" name="rate_date" id="rate_date" class="form-control" value="{{ old('rate_date', date('Y-m-d')) }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="usd_rate">Tasa USD a Bs</label>
                                    <input type="number" step="0.0001" min="0" name="usd_rate" id="usd_rate" class="form-control" value="{{ old('usd_rate', !empty($bcv_rate_info['usd_to_ves_rate']) ? $bcv_rate_info['usd_to_ves_rate'] : '') }}" required>
                                </div>
                            </div>
                            <div class="col-md-3" style="margin-top: 25px;">
                                <button type="submit" class="btn btn-primary">Guardar tasa</button>
                            </div>
                        </form>

                        @if (!empty($bcv_rate_info['usd_to_ves_rate']))
                            <p><strong>BCV:</strong> 1 USD = {{ number_format((float) $bcv_rate_info['usd_to_ves_rate'], 4, '.', ',') }} Bs</p>
                            @if (!empty($bcv_rate_info['rate_date']))
                                <p><strong>Fecha:</strong> {{ $bcv_rate_info['rate_date'] }}</p>
                            @endif
                            @if (!empty($bcv_rate_info['source']))
                                <p><strong>Fuente:</strong> {{ $bcv_rate_info['source'] }}</p>
                            @endif
                        @else
                            <div class="alert alert-warning" role="alert">
                                No hay tasa disponible en este momento.
                            </div>
                        @endif

                        <hr>
                        <h4 style="margin-top: 0;">Historial de tasas</h4>

                        <form action="{{ route('home.bcv_rate') }}" method="GET" class="row" style="margin-bottom: 15px;">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_month">Filtrar por mes</label>
                                    <input type="month" name="filter_month" id="filter_month" class="form-control" value="{{ $filter_month }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label for="filter_date">Filtrar por dia</label>
                                    <input type="date" name="filter_date" id="filter_date" class="form-control" value="{{ $filter_date }}">
                                </div>
                            </div>
                            <div class="col-md-6" style="margin-top: 25px;">
                                <button type="submit" class="btn btn-info">Buscar</button>
                                <a href="{{ route('home.bcv_rate') }}" class="btn btn-default">Limpiar</a>
                            </div>
                        </form>

                        @if (!$has_bcv_table)
                            <div class="alert alert-danger" role="alert">
                                No existe la tabla ghm_bcv_rates.
                            </div>
                        @elseif (empty($history_rates) || $history_rates->count() === 0)
                            <div class="alert alert-info" role="alert">
                                No hay registros de tasa para el filtro seleccionado.
                            </div>
                        @else
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Tasa USD a Bs</th>
                                            <th>Fuente</th>
                                            <th>Oficial</th>
                                            <th>Fallback</th>
                                            <th>Registrado</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($history_rates as $row)
                                            @php
                                                $rateValue = !empty($row->usd_to_ves_rate) ? (float) $row->usd_to_ves_rate : (float) ($row->usd_to_bs ?? 0);
                                            @endphp
                                            <tr>
                                                <td>{{ $row->rate_date }}</td>
                                                <td>{{ number_format($rateValue, 4, '.', ',') }}</td>
                                                <td>{{ $row->source ?? 'N/A' }}</td>
                                                <td>{{ !empty($row->is_official) ? 'Si' : 'No' }}</td>
                                                <td>{{ !empty($row->is_fallback) ? 'Si' : 'No' }}</td>
                                                <td>{{ $row->created_at ?? '-' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>

                            {{ $history_rates->links() }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
