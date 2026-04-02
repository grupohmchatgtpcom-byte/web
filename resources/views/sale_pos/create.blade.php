@extends('layouts.app')

@section('title', __('sale.pos_sale'))

@section('content')
    <section class="content no-print">
        <input type="hidden" id="amount_rounding_method" value="{{ $pos_settings['amount_rounding_method'] ?? '' }}">
        @if (!empty($pos_settings['allow_overselling']))
            <input type="hidden" id="is_overselling_allowed">
        @endif
        @if (session('business.enable_rp') == 1)
            <input type="hidden" id="reward_point_enabled">
        @endif
        <input type="hidden" id="can_override_negative_margin" value="{{ (auth()->user()->can('superadmin') || auth()->user()->can('admin')) ? 1 : 0 }}">
        @php
            $is_discount_enabled = $pos_settings['disable_discount'] != 1 ? true : false;
            $is_rp_enabled = session('business.enable_rp') == 1 ? true : false;
        @endphp
        {!! Form::open([
            'url' => action([\App\Http\Controllers\SellPosController::class, 'store']),
            'method' => 'post',
            'id' => 'add_pos_sell_form',
        ]) !!}
        <div class="row mb-12">
            <div class="col-md-12 tw-pt-0 tw-mb-14">
                <div class="row tw-flex lg:tw-flex-row md:tw-flex-col sm:tw-flex-col tw-flex-col tw-items-start md:tw-gap-4">
                    {{-- <div class="@if (empty($pos_settings['hide_product_suggestion'])) col-md-7 @else col-md-10 col-md-offset-1 @endif no-padding pr-12"> --}}
                    <div class="tw-px-3 tw-w-full lg:tw-w-[60%] lg:tw-px-0 lg:tw-pr-0">

                        <div class="tw-shadow-[rgba(17,_17,_26,_0.1)_0px_0px_16px] tw-rounded-2xl tw-bg-white tw-mb-2 md:tw-mb-8 tw-p-2">

                            {{-- <div class="box box-solid mb-12 @if (!isMobile()) mb-40 @endif"> --}}
                                <div class="box-body pb-0">
                                    {!! Form::hidden('location_id', $default_location->id ?? null, [
                                        'id' => 'location_id',
                                        'data-receipt_printer_type' => !empty($default_location->receipt_printer_type)
                                            ? $default_location->receipt_printer_type
                                            : 'browser',
                                        'data-default_payment_accounts' => $default_location->default_payment_accounts ?? '',
                                    ]) !!}
                                    <!-- sub_type -->
                                    {!! Form::hidden('sub_type', isset($sub_type) ? $sub_type : null) !!}
                                    <input type="hidden" id="item_addition_method"
                                        value="{{ $business_details->item_addition_method }}">
                                    @include('sale_pos.partials.pos_form')

                                    @include('sale_pos.partials.pos_form_totals')

                                    @include('sale_pos.partials.payment_modal')

                                    @if (empty($pos_settings['disable_suspend']))
                                        @include('sale_pos.partials.suspend_note_modal')
                                    @endif

                                    @if (empty($pos_settings['disable_recurring_invoice']))
                                        @include('sale_pos.partials.recurring_invoice_modal')
                                    @endif
                                </div>
                            {{-- </div> --}}
                        </div>
                    </div>
                    @if (empty($pos_settings['hide_product_suggestion']) && !isMobile())
                        <div class="md:tw-no-padding tw-w-full lg:tw-w-[40%] tw-px-5">
                            @include('sale_pos.partials.pos_sidebar')
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @include('sale_pos.partials.pos_form_actions')
        {!! Form::close() !!}
    </section>

    <!-- This will be printed -->
    <section class="invoice print_section" id="receipt_section">
    </section>
    <div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
        @include('contact.create', ['quick_add' => true])
    </div>
    @if (empty($pos_settings['hide_product_suggestion']) && isMobile())
        @include('sale_pos.partials.mobile_product_suggestions')
    @endif
    <!-- /.content -->
    <div class="modal fade register_details_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
    <div class="modal fade close_register_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>
    <!-- quick product modal -->
    <div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>

    <div class="modal fade" id="expense_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
    </div>

    @include('sale_pos.partials.configure_search_modal')

    @include('sale_pos.partials.recent_transactions_modal')

    @include('sale_pos.partials.weighing_scale_modal')

@stop
@section('css')
    <!-- include module css -->
    @if (!empty($pos_module_data))
        @foreach ($pos_module_data as $key => $value)
            @if (!empty($value['module_css_path']))
                @includeIf($value['module_css_path'])
            @endif
        @endforeach
    @endif
@stop
@section('javascript')
    <script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/printer.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
    <script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
    @include('sale_pos.partials.keyboard_shortcuts')

    <!-- Call restaurant module if defined -->
    @if (in_array('tables', $enabled_modules) ||
            in_array('modifiers', $enabled_modules) ||
            in_array('service_staff', $enabled_modules))
        <script src="{{ asset('js/restaurant.js?v=' . $asset_v) }}"></script>
    @endif
    <!-- include module js -->
    @if (!empty($pos_module_data))
        @foreach ($pos_module_data as $key => $value)
            @if (!empty($value['module_js_path']))
                @includeIf($value['module_js_path'], ['view_data' => $value['view_data']])
            @endif
        @endforeach
    @endif

    <script>
        $(document).ready(function() {
            let bcvRate = parseFloat($('#pos_bcv_rate').val() || 0);
            if (!bcvRate || bcvRate <= 0) {
                bcvRate = 0;
            }

            const parseNumeric = function(value) {
                if (value === undefined || value === null) {
                    return 0;
                }

                let cleaned = (value + '').replace(/[^0-9,.-]/g, '').trim();
                if (!cleaned) {
                    return 0;
                }

                const lastComma = cleaned.lastIndexOf(',');
                const lastDot = cleaned.lastIndexOf('.');

                if (lastComma > -1 && lastDot > -1) {
                    if (lastComma > lastDot) {
                        cleaned = cleaned.replace(/\./g, '').replace(',', '.');
                    } else {
                        cleaned = cleaned.replace(/,/g, '');
                    }
                } else if (lastComma > -1) {
                    cleaned = cleaned.replace(',', '.');
                }

                const parsed = parseFloat(cleaned);

                return isNaN(parsed) ? 0 : parsed;
            };

            const formatNumber = function(value) {
                const numericValue = Number(value || 0);
                return numericValue.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            };

            const getCurrentTotalUsd = function() {
                let totalUsd = 0;

                $('[id="final_total_input"]').each(function() {
                    let val = 0;
                    if (typeof __read_number === 'function') {
                        try {
                            val = Number(__read_number($(this)) || 0);
                        } catch (e) {
                            val = 0;
                        }
                    }
                    if (!val) {
                        val = parseNumeric($(this).val());
                    }
                    if (val > totalUsd) {
                        totalUsd = val;
                    }
                });

                if (!totalUsd) {
                    $('[id="total_payable"]').each(function() {
                        const val = parseNumeric($(this).text());
                        if (val > totalUsd) {
                            totalUsd = val;
                        }
                    });
                }

                return totalUsd;
            };

            const updateBolivarPayable = function() {
                const totalUsd = getCurrentTotalUsd();
                const totalBs = totalUsd * bcvRate;
                $('.pos_total_bs_display').text(formatNumber(totalBs));
            };

            const updateLineSubtotalsBs = function() {
                $('#pos_table tbody tr').each(function() {
                    let lineUsd = 0;
                    const lineInput = $(this).find('.pos_line_total');

                    if (typeof __read_number === 'function' && lineInput.length) {
                        try {
                            lineUsd = Number(__read_number(lineInput) || 0);
                        } catch (e) {
                            lineUsd = 0;
                        }
                    }

                    if (!lineUsd) {
                        lineUsd = parseNumeric(lineInput.val());
                    }
                    if (!lineUsd) {
                        lineUsd = parseNumeric($(this).find('.pos_line_total_text').first().text());
                    }

                    const lineBs = lineUsd * bcvRate;
                    $(this).find('.pos_line_total_bs_text').text(formatNumber(lineBs));
                });
            };

            const updateLineProfitability = function() {
                $('#pos_table tbody tr').each(function() {
                    let lineUsd = 0;
                    let qty = 0;

                    const lineInput = $(this).find('.pos_line_total');
                    const qtyInput = $(this).find('.pos_quantity');
                    const costBase = parseNumeric($(this).find('.line_last_purchase_price').val());
                    const multiplier = parseNumeric($(this).find('.base_unit_multiplier').val()) || 1;

                    if (typeof __read_number === 'function' && lineInput.length) {
                        try {
                            lineUsd = Number(__read_number(lineInput) || 0);
                        } catch (e) {
                            lineUsd = 0;
                        }
                    }
                    if (!lineUsd) {
                        lineUsd = parseNumeric(lineInput.val());
                    }

                    if (typeof __read_number === 'function' && qtyInput.length) {
                        try {
                            qty = Number(__read_number(qtyInput) || 0);
                        } catch (e) {
                            qty = 0;
                        }
                    }
                    if (!qty) {
                        qty = parseNumeric(qtyInput.val());
                    }

                    const lineCost = costBase * qty * multiplier;
                    const marginAmount = lineUsd - lineCost;
                    const marginPct = lineUsd > 0 ? (marginAmount / lineUsd) * 100 : 0;

                    const marginEl = $(this).find('.pos_line_margin_text');
                    marginEl
                        .text(formatNumber(marginAmount) + ' (' + formatNumber(marginPct) + '%)')
                        .removeClass('text-success text-danger text-warning text-muted');

                    if (lineUsd <= 0 || costBase <= 0) {
                        marginEl.addClass('text-muted');
                    } else if (marginAmount < 0) {
                        marginEl.addClass('text-danger');
                    } else if (marginPct < 15) {
                        marginEl.addClass('text-warning');
                    } else {
                        marginEl.addClass('text-success');
                    }
                });
            };

            updateBolivarPayable();
            updateLineSubtotalsBs();
            updateLineProfitability();

            const hasNegativeMarginRows = function() {
                let hasNegative = false;
                $('#pos_table tbody tr').each(function() {
                    const marginEl = $(this).find('.pos_line_margin_text');
                    if (marginEl.hasClass('text-danger')) {
                        hasNegative = true;
                    }
                });
                return hasNegative;
            };

            const hasMissingLotSelection = function() {
                let hasMissingLot = false;
                $('#pos_table tbody tr').each(function() {
                    const lotSelect = $(this).find('select.lot_number');
                    if (!lotSelect.length) {
                        return;
                    }

                    const hasRealOptions = lotSelect.find('option').length > 1;
                    const selected = (lotSelect.val() || '').toString().trim();
                    if (hasRealOptions && selected === '') {
                        hasMissingLot = true;
                        lotSelect.addClass('is-invalid').css('border-color', '#d9534f');
                    } else {
                        lotSelect.removeClass('is-invalid').css('border-color', '');
                    }
                });

                return hasMissingLot;
            };

            const hasMissingSerialNumber = function() {
                let hasMissing = false;
                $('#pos_table tbody tr.product_row').each(function() {
                    const enableSrNo = $(this).find('input.enable_sr_no').val();
                    if (enableSrNo !== '1') { return; }
                    const rowIndex = $(this).data('row_index');
                    const noteField = $(this).find('textarea[name="products[' + rowIndex + '][sell_line_note]"]');
                    const srText = (noteField.val() || '').trim();
                    if (srText === '') {
                        hasMissing = true;
                        noteField.addClass('is-invalid').css('border-color', '#d9534f');
                        $(this).find('td:first').css('background-color', '#fdf0ef');
                    } else {
                        noteField.removeClass('is-invalid').css('border-color', '');
                        $(this).find('td:first').css('background-color', '');
                    }
                });
                return hasMissing;
            };

            $('#add_pos_sell_form').on('submit', function(e) {
                updateLineProfitability();
                const canOverride = parseInt($('#can_override_negative_margin').val() || '0', 10) === 1;
                if (hasMissingLotSelection()) {
                    e.preventDefault();
                    alert('No se puede registrar la venta: falta seleccionar lote en uno o más productos.');
                    return false;
                }
                if (hasMissingSerialNumber()) {
                    e.preventDefault();
                    alert('No se puede registrar la venta: falta ingresar el número de serial en uno o más productos. Haga clic en el nombre del producto para abrirlo y complete el campo Descripción con el serial.');
                    return false;
                }
                if (!canOverride && hasNegativeMarginRows()) {
                    e.preventDefault();
                    alert('No se puede registrar la venta: hay renglones con margen negativo.');
                    return false;
                }
            });

            setInterval(function() {
                updateBolivarPayable();
                updateLineSubtotalsBs();
                updateLineProfitability();
            }, 500);

            // Polling AJAX cada 60 segundos para refrescar la tasa BCV desde el servidor
            const bcvRateEndpoint = '{{ route("home.bcv_rate.current") }}';
            const refreshBcvRate = function() {
                $.getJSON(bcvRateEndpoint, function(data) {
                    if (data.success && data.rate > 0) {
                        const newRate = parseFloat(data.rate);
                        if (newRate !== bcvRate) {
                            bcvRate = newRate;
                            $('#pos_bcv_rate').val(newRate);
                            // Actualizar badge de tasa en el header del POS si existe
                            $('.bcv-rate-badge-value').text(
                                newRate.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                            );
                        }
                    }
                }).fail(function() {
                    // Ignorar errores de red silenciosamente
                });
            };
            setInterval(refreshBcvRate, 60000);
        });
    </script>

    {{-- JS de dualidad de monedas en el modal de pagos --}}
    <script>
    (function() {
        // Métodos de pago cuya moneda base es VES (Bolívares).
        // Detectamos por palabras clave en el texto de la opción seleccionada.
        var VES_KEYWORDS = ['bs', 'boliv', 'transferencia bs', 'pago movil', 'pago móvil', 'tarjeta', 'tdd', 'tdc'];

        function isBsMethod(methodText) {
            if (!methodText) return false;
            var lower = methodText.toLowerCase();
            return VES_KEYWORDS.some(function(kw) { return lower.indexOf(kw) !== -1; });
        }

        function getActiveBcvRate() {
            var rate = parseFloat($('#pos_bcv_rate').val() || 0);
            return rate > 0 ? rate : 1;
        }

        function formatBs(value) {
            return Number(value).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function formatUsd(value) {
            return Number(value).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
        }

        /**
         * Actualiza el equivalente visual y los campos ocultos de una fila de pago.
         */
        function updatePaymentRowEquiv($row) {
            var rowIndex = $row.find('.payment_row_index').val();
            if (rowIndex === undefined) return;

            var $currencySelect = $row.find('.payment-currency-selector');
            var $currencyHidden = $row.find('.payment-currency-code');
            var $rateHidden     = $row.find('.payment-exchange-rate');
            var $amountInput    = $row.find('input.payment-amount');
            var $equivValue     = $row.find('.payment-equiv-value');
            var $equivCurrency  = $row.find('.payment-equiv-currency');

            var rate     = getActiveBcvRate();
            var amount   = 0;
            if (typeof __read_number === 'function') {
                try { amount = Number(__read_number($amountInput) || 0); } catch(e) {}
            }
            if (!amount) {
                var raw = ($amountInput.val() + '').replace(/[^0-9,.-]/g, '').replace(',', '.');
                amount = parseFloat(raw) || 0;
            }

            var currency = $currencySelect.length ? $currencySelect.val() : ($currencyHidden.val() || 'USD');

            // Sincronizar el campo oculto
            $currencyHidden.val(currency);
            $rateHidden.val(rate);

            if (currency === 'VES') {
                var usdEquiv = rate > 0 ? (amount / rate) : 0;
                $equivValue.text(formatUsd(usdEquiv));
                $equivCurrency.text('USD');
            } else {
                var bsEquiv = amount * rate;
                $equivValue.text(formatBs(bsEquiv));
                $equivCurrency.text('Bs');
            }
        }

        /**
         * Calcula el total pagado en USD sumando todas las filas.
         * Las filas en VES se convierten usando la tasa activa.
         */
        function calcTotalPayingUsd() {
            var rate = getActiveBcvRate();
            var total = 0;
            $('#payment_rows_div .payment_row').each(function() {
                var $row = $(this);
                var $currencyHidden = $row.find('.payment-currency-code');
                var $amountInput    = $row.find('input.payment-amount');
                var currency = $currencyHidden.val() || 'USD';
                var amount = 0;
                if (typeof __read_number === 'function') {
                    try { amount = Number(__read_number($amountInput) || 0); } catch(e) {}
                }
                if (!amount) {
                    var raw = ($amountInput.val() + '').replace(/[^0-9,.-]/g, '').replace(',', '.');
                    amount = parseFloat(raw) || 0;
                }
                if (currency === 'VES') {
                    total += rate > 0 ? (amount / rate) : 0;
                } else {
                    total += amount;
                }
            });
            return total;
        }

        /**
         * Refresca los totalizadores de Bs en el panel lateral del modal.
         */
        function refreshModalBsTotals() {
            var rate = getActiveBcvRate();
            // Total a pagar en Bs
            var $payableSpan = $('.total_payable_span');
            var payableUsd = 0;
            if (typeof __read_number === 'function') {
                try { payableUsd = Number(__read_number($payableSpan) || 0); } catch(e) {}
            }
            if (!payableUsd) {
                payableUsd = parseFloat(($payableSpan.text() + '').replace(/[^0-9.]/g, '')) || 0;
            }
            $('.payment_total_payable_bs').text(formatBs(payableUsd * rate));
            $('.payment_modal_bcv_rate').text(formatBs(rate));

            // Total pagando en Bs
            var payingUsd = calcTotalPayingUsd();
            $('.payment_total_paying_bs').text(formatBs(payingUsd * rate));
        }

        // Detectar moneda automáticamente según el método seleccionado
        $(document).on('change', '.payment_types_dropdown', function() {
            var $select = $(this);
            var methodText = $select.find('option:selected').text() || '';
            var $row = $select.closest('.payment_row, .box-body, .row');
            // Buscar el selector de moneda más cercano
            var $currencySelect = $row.find('.payment-currency-selector');
            if ($currencySelect.length) {
                var newCurrency = isBsMethod(methodText) ? 'VES' : 'USD';
                $currencySelect.val(newCurrency);
                $row.find('.payment-currency-code').val(newCurrency);
                updatePaymentRowEquiv($row.closest('.col-md-12').parent().closest('.col-md-12, .payment_row').first().length
                    ? $row.closest('.col-md-12, .payment_row')
                    : $row
                );
            }
            refreshModalBsTotals();
        });

        // Actualizar equivalente al cambiar moneda
        $(document).on('change', '.payment-currency-selector', function() {
            var $row = $(this).closest('.col-md-12').parent();
            updatePaymentRowEquiv($row);
            refreshModalBsTotals();
        });

        // Actualizar equivalente al cambiar monto
        $(document).on('keyup change', 'input.payment-amount', function() {
            var $row = $(this).closest('.col-md-12').parent();
            updatePaymentRowEquiv($row);
            refreshModalBsTotals();
        });

        // Inicializar todas las filas cuando se abre el modal
        $(document).on('shown.bs.modal', '#modal_payment', function() {
            var rate = getActiveBcvRate();
            // Escribir la tasa en los campos ocultos vacíos
            $('.payment-exchange-rate').each(function() {
                if (!$(this).val() || parseFloat($(this).val()) <= 0) {
                    $(this).val(rate);
                }
            });
            // Inicializar equivalentes de cada fila
            $('#payment_rows_div .col-md-12').each(function() {
                updatePaymentRowEquiv($(this));
            });
            refreshModalBsTotals();
        });

        // Refrescar cuando se agrega una nueva fila de pago
        $(document).on('DOMNodeInserted', '#payment_rows_div', function(e) {
            var $newRow = $(e.target);
            if ($newRow.hasClass('col-md-12') || $newRow.find('input.payment-amount').length) {
                setTimeout(function() {
                    updatePaymentRowEquiv($newRow.hasClass('col-md-12') ? $newRow : $newRow.find('.col-md-12').first());
                    refreshModalBsTotals();
                }, 100);
            }
        });

        // Refrescar totalizadores al cambiar total a pagar (cuando cambia el carrito)
        var _lastPayable = 0;
        setInterval(function() {
            if ($('#modal_payment').hasClass('in') || $('#modal_payment').css('display') === 'block') {
                var payableText = $('.total_payable_span').text();
                if (payableText !== _lastPayable) {
                    _lastPayable = payableText;
                    refreshModalBsTotals();
                }
            }
        }, 800);
    })();
    </script>
@stop
