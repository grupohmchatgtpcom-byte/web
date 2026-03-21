@extends('layouts.app')
@section('title', __('lang_v1.add_stock_transfer'))

@section('content')

    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('lang_v1.add_stock_transfer')</h1>
    </section>

    <!-- Main content -->
    <section class="content no-print">
        {!! Form::open([
            'url' => action([\App\Http\Controllers\StockTransferController::class, 'store']),
            'method' => 'post',
            'id' => 'stock_transfer_form',
        ]) !!}

        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('transaction_date', __('messages.date') . ':*') !!}
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-calendar"></i>
                            </span>
                            {!! Form::text('transaction_date', @format_datetime('now'), ['class' => 'form-control', 'readonly', 'required']) !!}
                        </div>
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('ref_no', __('purchase.ref_no') . ':') !!}
                        {!! Form::text('ref_no', null, ['class' => 'form-control']) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('status', __('sale.status') . ':*') !!} @show_tooltip(__('lang_v1.completed_status_help'))
                        {!! Form::select('status', $statuses, null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'status',
                        ]) !!}
                    </div>
                </div>
                <div class="clearfix"></div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('location_id', __('lang_v1.location_from') . ':*') !!}
                        {!! Form::select('location_id', $business_locations, null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'location_id',
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-6">
                    <div class="form-group">
                        {!! Form::label('transfer_location_id', __('lang_v1.location_to') . ':*') !!}
                        {!! Form::select('transfer_location_id', $business_locations, null, [
                            'class' => 'form-control select2',
                            'placeholder' => __('messages.please_select'),
                            'required',
                            'id' => 'transfer_location_id',
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group text-right">
                        <a href="#" id="download_dispatch_suggestion"
                            data-base-url="{{ action([\App\Http\Controllers\StockTransferController::class, 'downloadSuggestedDispatchExcel']) }}"
                            class="tw-dw-btn tw-dw-btn-info">
                            Descargar sugerencia de despacho (Excel)
                        </a>
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="alert alert-info" style="margin-top: 8px;">
                        @php
                            $bcvRateValue = !empty($bcv_rate_info) ? (float) $bcv_rate_info->usd_to_bs : 0;
                        @endphp
                        <strong>Tasa BCV vigente:</strong>
                        @if(!empty($bcv_rate_info))
                            {{ number_format($bcvRateValue, 6, '.', ',') }} Bs
                            <small>(Fecha: {{ $bcv_rate_info->rate_date }})</small>
                        @else
                            No disponible
                        @endif
                        <input type="hidden" id="bcv_rate_value" value="{{ $bcvRateValue }}">
                    </div>
                </div>

            </div>
        @endcomponent

        <!-- end-->
        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-8 col-sm-offset-2">
                    <div class="form-group">
                        <div class="input-group">
                            <span class="input-group-addon">
                                <i class="fa fa-search"></i>
                            </span>
                            {!! Form::text('search_product', null, [
                                'class' => 'form-control',
                                'id' => 'search_product_for_srock_adjustment',
                                'placeholder' => __('stock_adjustment.search_product'),
                                'disabled',
                            ]) !!}
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-10 col-sm-offset-1">
                    <input type="hidden" id="product_row_index" value="0">
                    <input type="hidden" id="total_amount" name="final_total" value="0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-condensed" id="stock_adjustment_product_table">
                            <thead>
                                <tr>
                                    <th class="col-sm-4 text-center">
                                        @lang('sale.product')
                                    </th>
                                    <th class="col-sm-2 text-center">
                                        @lang('sale.qty')
                                    </th>
                                    <th class="col-sm-2 text-center">
                                        @lang('sale.unit_price')
                                    </th>
                                    <th class="col-sm-2 text-center">
                                        @lang('sale.subtotal')
                                    </th>
                                    <th class="col-sm-2 text-center"><i class="fa fa-trash" aria-hidden="true"></i></th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                            <tfoot>
                                <tr class="text-center">
                                    <td colspan="3"></td>
                                    <td>
                                        <div class="pull-right"><b>@lang('sale.total'):</b> <span
                                                id="total_adjustment">0.00</span></div>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        @endcomponent


        @component('components.widget', ['class' => 'box-solid'])
            <div class="row">
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('shipping_charges', __('lang_v1.shipping_charges') . ':') !!}
                        {!! Form::text('shipping_charges', 0, [
                            'class' => 'form-control input_number',
                            'placeholder' => __('lang_v1.shipping_charges'),
                        ]) !!}
                    </div>
                </div>
                <div class="col-sm-4">
                    <div class="form-group">
                        {!! Form::label('additional_notes', __('purchase.additional_notes')) !!}
                        {!! Form::textarea('additional_notes', null, ['class' => 'form-control', 'rows' => 3]) !!}
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12 text-right">
                    <b>@lang('stock_adjustment.total_amount'):</b> <span id="final_total_text">0.00</span>
                </div>
                <div class="col-md-12 text-right" style="margin-top: 4px;">
                    <b>Total estimado en Bs:</b> <span id="final_total_bs_text">0.00</span>
                </div>
                <br>
                <br>
                <div class="col-sm-12 text-center">
                    <button type="submit" id="save_stock_transfer" class="tw-dw-btn tw-dw-btn-primary tw-dw-btn-lg tw-text-white">@lang('messages.save')</button>
                </div>
            </div>
        @endcomponent

        {!! Form::close() !!}
    </section>
@stop
@section('javascript')
    <script src="{{ asset('js/stock_transfer.js?v=' . $asset_v) }}"></script>
    <script type="text/javascript">
        __page_leave_confirmation('#stock_transfer_form');

        $(document).on('click', '#download_dispatch_suggestion', function(e) {
            e.preventDefault();

            const warehouseId = $('#location_id').val();
            if (!warehouseId) {
                toastr.warning('Selecciona la ubicación origen (almacén) para generar la sugerencia.');
                return;
            }

            const baseUrl = $(this).data('base-url');
            window.location = baseUrl + '?warehouse_location_id=' + encodeURIComponent(warehouseId);
        });

        const updateBolivarTotal = function() {
            const usdTotal = parseFloat(__read_number($('#total_amount'))) || 0;
            const bcvRate = parseFloat($('#bcv_rate_value').val()) || 0;
            const bsTotal = usdTotal * bcvRate;
            $('#final_total_bs_text').text(__number_f(bsTotal));
        };

        $(document).on('change', 'input.product_quantity, input.product_unit_price, #shipping_charges', function() {
            setTimeout(updateBolivarTotal, 50);
        });

        updateBolivarTotal();
    </script>
@endsection
