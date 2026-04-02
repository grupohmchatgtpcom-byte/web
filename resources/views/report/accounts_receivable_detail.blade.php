@extends('layouts.app')
@section('title', 'CxC — Detalle: {{ $contact->name }}')

@section('content')
<section class="content-header no-print">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">
        Cuentas por Cobrar
        <small>— {{ $contact->name }}</small>
    </h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('reports.cxc') }}"><i class="fa fa-arrow-left"></i> Volver a CxC</a></li>
    </ol>
</section>

<section class="content no-print">

    <div class="row">
        <div class="col-md-4">
            <div class="info-box bg-blue">
                <span class="info-box-icon"><i class="fa fa-user"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Cliente</span>
                    <span class="info-box-number" style="font-size:16px;">{{ $contact->name }}</span>
                    <span class="progress-description">{{ $contact->mobile ?? $contact->landline ?? '—' }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box bg-red">
                <span class="info-box-icon"><i class="fa fa-dollar"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Saldo Total USD</span>
                    <span class="info-box-number">${{ number_format($totales['saldo_usd'], 2) }}</span>
                    <span class="progress-description">Bs {{ number_format($totales['saldo_bs'], 2) }}</span>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="info-box {{ $totales['vencido_usd'] > 0 ? 'bg-orange' : 'bg-green' }}">
                <span class="info-box-icon"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Vencido USD</span>
                    <span class="info-box-number">${{ number_format($totales['vencido_usd'], 2) }}</span>
                    <span class="progress-description">Por vencer: ${{ number_format($totales['por_vencer_usd'], 2) }}</span>
                </div>
            </div>
        </div>
    </div>

    @component('components.widget', ['class' => 'box-primary'])
        <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Vencimiento</th>
                        <th>Total USD</th>
                        <th>Pagado USD</th>
                        <th>Saldo USD</th>
                        <th>Saldo Bs</th>
                        <th>Días Vencida</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($facturas as $f)
                    <tr>
                        <td>
                            <a href="/sells/{{ $f->id }}" target="_blank">
                                {{ $f->invoice_no ?? '#' . $f->id }}
                            </a>
                        </td>
                        <td>{{ \Carbon\Carbon::parse($f->transaction_date)->format('d/m/Y') }}</td>
                        <td>
                            @if($f->pay_term_number && $f->pay_term_type)
                                @php
                                    $venc = \Carbon\Carbon::parse($f->transaction_date);
                                    if ($f->pay_term_type === 'days') $venc->addDays($f->pay_term_number);
                                    elseif ($f->pay_term_type === 'months') $venc->addMonths($f->pay_term_number);
                                @endphp
                                {{ $venc->format('d/m/Y') }}
                            @else
                                —
                            @endif
                        </td>
                        <td>${{ number_format($f->final_total, 2) }}</td>
                        <td>${{ number_format($f->total_paid ?? 0, 2) }}</td>
                        <td>${{ number_format($f->balance_usd, 2) }}</td>
                        <td>Bs {{ number_format($f->balance_bs, 2) }}</td>
                        <td class="text-center">
                            @if($f->dias_vencida > 0)
                                <span class="label label-danger">{{ $f->dias_vencida }} días</span>
                            @else
                                <span class="label label-success">Vigente</span>
                            @endif
                        </td>
                        <td>
                            @if($f->payment_status === 'paid')
                                <span class="label label-success">Pagada</span>
                            @elseif($f->payment_status === 'partial')
                                <span class="label label-warning">Parcial</span>
                            @else
                                <span class="label label-danger">Pendiente</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center">No se encontraron facturas pendientes.</td></tr>
                    @endforelse
                </tbody>
                <tfoot>
                    <tr class="info">
                        <th colspan="3">TOTALES</th>
                        <th>${{ number_format($totales['total_usd'], 2) }}</th>
                        <th>${{ number_format($totales['pagado_usd'], 2) }}</th>
                        <th>${{ number_format($totales['saldo_usd'], 2) }}</th>
                        <th>Bs {{ number_format($totales['saldo_bs'], 2) }}</th>
                        <th colspan="2"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endcomponent
</section>
@stop
