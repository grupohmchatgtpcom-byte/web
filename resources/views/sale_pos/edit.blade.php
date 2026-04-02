@extends('layouts.app')

@section('title', __('sale.pos_sale'))

@section('content')
<section class="content no-print">
	<input type="hidden" id="amount_rounding_method" value="{{$pos_settings['amount_rounding_method'] ?? ''}}">
	@if(!empty($pos_settings['allow_overselling']))
		<input type="hidden" id="is_overselling_allowed">
	@endif
	@if(session('business.enable_rp') == 1)
        <input type="hidden" id="reward_point_enabled">
    @endif
	<input type="hidden" id="can_override_negative_margin" value="{{ (auth()->user()->can('superadmin') || auth()->user()->can('admin')) ? 1 : 0 }}">
    @php
		$is_discount_enabled = $pos_settings['disable_discount'] != 1 ? true : false;
		$is_rp_enabled = session('business.enable_rp') == 1 ? true : false;
	@endphp
	{!! Form::open(['url' => action([\App\Http\Controllers\SellPosController::class, 'update'], [$transaction->id]), 'method' => 'post', 'id' => 'edit_pos_sell_form' ]) !!}
	{{ method_field('PUT') }}
	<div class="row mb-12">
		<div class="col-md-12">
			<div class="row">
				<div class="@if(empty($pos_settings['hide_product_suggestion'])) col-md-7 @else col-md-10 col-md-offset-1 @endif no-padding pr-12">
					<div class="box box-solid mb-12 @if(!isMobile()) mb-40 @endif">
						<div class="box-body pb-0">
							{!! Form::hidden('location_id', $transaction->location_id, ['id' => 'location_id', 'data-receipt_printer_type' => !empty($location_printer_type) ? $location_printer_type : 'browser', 'data-default_payment_accounts' => $transaction->location->default_payment_accounts]); !!}
							<!-- sub_type -->
							{!! Form::hidden('sub_type', isset($sub_type) ? $sub_type : null) !!}
							<input type="hidden" id="item_addition_method" value="{{$business_details->item_addition_method}}">
								@include('sale_pos.partials.pos_form_edit')

								@include('sale_pos.partials.pos_form_totals', ['edit' => true])

								@include('sale_pos.partials.payment_modal')

								@if(empty($pos_settings['disable_suspend']))
									@include('sale_pos.partials.suspend_note_modal')
								@endif

								@if(empty($pos_settings['disable_recurring_invoice']))
									@include('sale_pos.partials.recurring_invoice_modal')
								@endif
							</div>
							@if(!empty($only_payment))
								<div class="overlay"></div>
							@endif
						</div>
					</div>
				@if(empty($pos_settings['hide_product_suggestion'])  && !isMobile() && empty($only_payment))
					<div class="col-md-5 no-padding">
						@include('sale_pos.partials.pos_sidebar')
					</div>
				@endif
			</div>
		</div>
	</div>
	@include('sale_pos.partials.pos_form_actions', ['edit' => true])
	{!! Form::close() !!}
</section>

<!-- This will be printed -->
<section class="invoice print_section" id="receipt_section">
</section>
<div class="modal fade contact_modal" tabindex="-1" role="dialog" aria-labelledby="gridSystemModalLabel">
	@include('contact.create', ['quick_add' => true])
</div>
@if(empty($pos_settings['hide_product_suggestion']) && isMobile())
	@include('sale_pos.partials.mobile_product_suggestions')
@endif
<!-- /.content -->
<div class="modal fade register_details_modal" tabindex="-1" role="dialog" 
	aria-labelledby="gridSystemModalLabel">
</div>
<div class="modal fade close_register_modal" tabindex="-1" role="dialog" 
	aria-labelledby="gridSystemModalLabel">
</div>
<!-- quick product modal -->
<div class="modal fade quick_add_product_modal" tabindex="-1" role="dialog" aria-labelledby="modalTitle"></div>

@include('sale_pos.partials.configure_search_modal')

@include('sale_pos.partials.recent_transactions_modal')

@include('sale_pos.partials.weighing_scale_modal')

@stop

@section('javascript')
	<input type="hidden" id="pos_bcv_rate" value="{{ (float) getActiveBcvRate() }}">
	<script src="{{ asset('js/pos.js?v=' . $asset_v) }}"></script>
	<script src="{{ asset('js/printer.js?v=' . $asset_v) }}"></script>
	<script src="{{ asset('js/product.js?v=' . $asset_v) }}"></script>
	<script src="{{ asset('js/opening_stock.js?v=' . $asset_v) }}"></script>
	@include('sale_pos.partials.keyboard_shortcuts')

	<!-- Call restaurant module if defined -->
    @if(in_array('tables' ,$enabled_modules) || in_array('modifiers' ,$enabled_modules) || in_array('service_staff' ,$enabled_modules))
    	<script src="{{ asset('js/restaurant.js?v=' . $asset_v) }}"></script>
    @endif

    <!-- include module js -->
    @if(!empty($pos_module_data))
	    @foreach($pos_module_data as $key => $value)
            @if(!empty($value['module_js_path']))
                @includeIf($value['module_js_path'], ['view_data' => $value['view_data']])
            @endif
	    @endforeach
	@endif

    {{-- GHM: Subtotales en Bs en la vista de edición --}}
    <script>
    $(document).ready(function() {
        var bcvRate = parseFloat($('#pos_bcv_rate').val() || 0);
        if (!bcvRate || bcvRate <= 0) { bcvRate = 0; }

        var formatNumber = function(v) {
            return Number(v || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        };

        var updateLineSubtotalsBs = function() {
            $('#pos_table tbody tr').each(function() {
                var lineUsd = 0;
                var lineInput = $(this).find('.pos_line_total');
                if (typeof __read_number === 'function' && lineInput.length) {
                    try { lineUsd = Number(__read_number(lineInput) || 0); } catch(e) {}
                }
                if (!lineUsd) { lineUsd = parseFloat((lineInput.val() || '').replace(/,/g, '')) || 0; }
                $(this).find('.pos_line_total_bs_text').text(formatNumber(lineUsd * bcvRate));
            });
        };

		var updateLineProfitability = function() {
			$('#pos_table tbody tr').each(function() {
				var lineUsd = 0;
				var qty = 0;
				var lineInput = $(this).find('.pos_line_total');
				var qtyInput = $(this).find('.pos_quantity');
				var costBase = parseFloat(($(this).find('.line_last_purchase_price').val() || '').replace(/,/g, '')) || 0;
				var multiplier = parseFloat(($(this).find('.base_unit_multiplier').val() || '').replace(/,/g, '')) || 1;

				if (typeof __read_number === 'function' && lineInput.length) {
					try { lineUsd = Number(__read_number(lineInput) || 0); } catch(e) {}
				}
				if (!lineUsd) { lineUsd = parseFloat((lineInput.val() || '').replace(/,/g, '')) || 0; }

				if (typeof __read_number === 'function' && qtyInput.length) {
					try { qty = Number(__read_number(qtyInput) || 0); } catch(e) {}
				}
				if (!qty) { qty = parseFloat((qtyInput.val() || '').replace(/,/g, '')) || 0; }

				var lineCost = costBase * qty * multiplier;
				var marginAmount = lineUsd - lineCost;
				var marginPct = lineUsd > 0 ? (marginAmount / lineUsd) * 100 : 0;

				var marginEl = $(this).find('.pos_line_margin_text');
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

		var hasNegativeMarginRows = function() {
			var hasNegative = false;
			$('#pos_table tbody tr').each(function() {
				var marginEl = $(this).find('.pos_line_margin_text');
				if (marginEl.hasClass('text-danger')) {
					hasNegative = true;
				}
			});
			return hasNegative;
		};

		var hasMissingLotSelection = function() {
			var hasMissingLot = false;
			$('#pos_table tbody tr').each(function() {
				var lotSelect = $(this).find('select.lot_number');
				if (!lotSelect.length) {
					return;
				}

				var hasRealOptions = lotSelect.find('option').length > 1;
				var selected = (lotSelect.val() || '').toString().trim();
				if (hasRealOptions && selected === '') {
					hasMissingLot = true;
					lotSelect.addClass('is-invalid').css('border-color', '#d9534f');
				} else {
					lotSelect.removeClass('is-invalid').css('border-color', '');
				}
			});

			return hasMissingLot;
		};

		var hasMissingSerialNumber = function() {
			var hasMissing = false;
			$('#pos_table tbody tr.product_row').each(function() {
				var enableSrNo = $(this).find('input.enable_sr_no').val();
				if (enableSrNo !== '1') { return; }
				var rowIndex = $(this).data('row_index');
				var noteField = $(this).find('textarea[name="products[' + rowIndex + '][sell_line_note]"]');
				var srText = (noteField.val() || '').trim();
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

        updateLineSubtotalsBs();
		updateLineProfitability();

		$('#edit_pos_sell_form').on('submit', function(e) {
			updateLineProfitability();
			var canOverride = parseInt($('#can_override_negative_margin').val() || '0', 10) === 1;
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
			updateLineSubtotalsBs();
			updateLineProfitability();
		}, 500);
    });
    </script>

    {{-- GHM: JS de dualidad de monedas en el modal de pagos (edición) --}}
    <script>
    (function() {
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

        function updatePaymentRowEquiv($row) {
            var rowIndex = $row.find('.payment_row_index').val();
            if (rowIndex === undefined) return;

            var $currencySelect = $row.find('.payment-currency-selector');
            var $currencyHidden = $row.find('.payment-currency-code');
            var $rateHidden     = $row.find('.payment-exchange-rate');
            var $amountInput    = $row.find('input.payment-amount');
            var $equivValue     = $row.find('.payment-equiv-value');
            var $equivCurrency  = $row.find('.payment-equiv-currency');

            var rate   = getActiveBcvRate();
            var amount = 0;
            if (typeof __read_number === 'function') {
                try { amount = Number(__read_number($amountInput) || 0); } catch(e) {}
            }
            if (!amount) {
                var raw = ($amountInput.val() + '').replace(/[^0-9,.-]/g, '').replace(',', '.');
                amount = parseFloat(raw) || 0;
            }

            var currency = $currencySelect.length ? $currencySelect.val() : ($currencyHidden.val() || 'USD');
            $currencyHidden.val(currency);
            $rateHidden.val(rate);

            if (currency === 'VES') {
                $equivValue.text(formatUsd(rate > 0 ? (amount / rate) : 0));
                $equivCurrency.text('USD');
            } else {
                $equivValue.text(formatBs(amount * rate));
                $equivCurrency.text('Bs');
            }
        }

        function calcTotalPayingUsd() {
            var rate = getActiveBcvRate();
            var total = 0;
            $('#payment_rows_div .payment_row').each(function() {
                var $row = $(this);
                var currency = $row.find('.payment-currency-code').val() || 'USD';
                var amount = 0;
                if (typeof __read_number === 'function') {
                    try { amount = Number(__read_number($row.find('input.payment-amount')) || 0); } catch(e) {}
                }
                if (!amount) {
                    var raw = ($row.find('input.payment-amount').val() + '').replace(/[^0-9,.-]/g, '').replace(',', '.');
                    amount = parseFloat(raw) || 0;
                }
                total += (currency === 'VES') ? (rate > 0 ? (amount / rate) : 0) : amount;
            });
            return total;
        }

        function refreshModalBsTotals() {
            var rate = getActiveBcvRate();
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
            $('.payment_total_paying_bs').text(formatBs(calcTotalPayingUsd() * rate));
        }

        $(document).on('change', '.payment_types_dropdown', function() {
            var methodText = $(this).find('option:selected').text() || '';
            var $row = $(this).closest('.payment_row, .box-body, .row');
            var $currencySelect = $row.find('.payment-currency-selector');
            if ($currencySelect.length) {
                var newCurrency = isBsMethod(methodText) ? 'VES' : 'USD';
                $currencySelect.val(newCurrency);
                $row.find('.payment-currency-code').val(newCurrency);
                updatePaymentRowEquiv($row);
            }
            refreshModalBsTotals();
        });

        $(document).on('change', '.payment-currency-selector', function() {
            updatePaymentRowEquiv($(this).closest('.col-md-12').parent());
            refreshModalBsTotals();
        });

        $(document).on('keyup change', 'input.payment-amount', function() {
            updatePaymentRowEquiv($(this).closest('.col-md-12').parent());
            refreshModalBsTotals();
        });

        $(document).on('shown.bs.modal', '#modal_payment', function() {
            var rate = getActiveBcvRate();
            $('.payment-exchange-rate').each(function() {
                if (!$(this).val() || parseFloat($(this).val()) <= 0) { $(this).val(rate); }
            });
            $('#payment_rows_div .col-md-12').each(function() { updatePaymentRowEquiv($(this)); });
            refreshModalBsTotals();
        });

        $(document).on('DOMNodeInserted', '#payment_rows_div', function(e) {
            var $newRow = $(e.target);
            if ($newRow.hasClass('col-md-12') || $newRow.find('input.payment-amount').length) {
                setTimeout(function() {
                    updatePaymentRowEquiv($newRow.hasClass('col-md-12') ? $newRow : $newRow.find('.col-md-12').first());
                    refreshModalBsTotals();
                }, 100);
            }
        });

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

@endsection

@section('css')
	<style type="text/css">
		/*CSS to print receipts*/
		.print_section{
		    display: none;
		}
		@media print{
		    .print_section{
		        display: block !important;
		    }
		}
		@page {
		    size: 3.1in auto;/* width height */
		    height: auto !important;
		    margin-top: 0mm;
		    margin-bottom: 0mm;
		}
		.overlay {
			background: rgba(255,255,255,0) !important;
			cursor: not-allowed;
		}
	</style>
	<!-- include module css -->
    @if(!empty($pos_module_data))
        @foreach($pos_module_data as $key => $value)
            @if(!empty($value['module_css_path']))
                @includeIf($value['module_css_path'])
            @endif
        @endforeach
    @endif
@endsection