<!-- Edit Order tax Modal -->
<div class="modal-dialog modal-lg" role="document">
	<div class="modal-content">
		<div class="modal-header">
			<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
			<h4 class="modal-title">@lang('lang_v1.suspended_sales')</h4>
		</div>
		<div class="modal-body">
			<div class="row" style="margin-bottom:12px;">
				<div class="col-xs-12 col-sm-8">
					<input type="text" id="suspended_sales_search" class="form-control" placeholder="Buscar por factura, cliente, nota o etiqueta...">
				</div>
				<div class="col-xs-12 col-sm-4" style="margin-top:6px;">
					<select id="suspended_sales_filter" class="form-control">
						<option value="all">Todas</option>
						<option value="tagged">Con etiqueta</option>
						<option value="untagged">Sin etiqueta</option>
					</select>
				</div>
			</div>
			<div class="row">
				@php
					$c = 0;
					$subtype = '';
				@endphp
				@if(!empty($transaction_sub_type))
					@php
						$subtype = '?sub_type='.$transaction_sub_type;
					@endphp
				@endif
				@forelse($sales as $sale)
					@if($sale->is_suspend)
						@php
							$tag = '';
							$noteBody = (string) $sale->additional_notes;
							if (!empty($sale->additional_notes) && preg_match('/^\[([^\]]+)\]\s*(.*)$/u', $sale->additional_notes, $parts)) {
								$tag = trim($parts[1]);
								$noteBody = trim($parts[2]);
							}
							$searchText = strtolower(trim(($sale->invoice_no ?? '') . ' ' . ($sale->name ?? '') . ' ' . ($sale->additional_notes ?? '') . ' ' . $tag));
						@endphp
						<div class="col-xs-6 col-sm-3 suspended-sale-card" data-search="{{ $searchText }}" data-has-tag="{{ !empty($tag) ? '1' : '0' }}">
							<div class="small-box bg-yellow">
					            <div class="inner text-center">
				            	@if(!empty($tag))
				            		<p style="margin-bottom:4px;"><span class="label label-primary" style="font-size:11px;">{{ $tag }}</span></p>
				            	@endif
						            @if(!empty($sale->additional_notes))
					            	<p><i class="fa fa-edit"></i> {{ $noteBody }}</p>
						            @endif
					              <p>{{$sale->invoice_no}}<br>
					              {{@format_date($sale->transaction_date)}}<br>
					              <strong><i class="fa fa-user"></i> {{$sale->name}}</strong></p>
					              <p><i class="fa fa-cubes"></i>@lang('lang_v1.total_items'): {{count($sale->sell_lines)}}<br>
					              <i class="fas fa-money-bill-alt"></i> @lang('sale.total'): <span class="display_currency" data-currency_symbol=true>{{$sale->final_total}}</span>
					              </p>
					              @if($is_tables_enabled && !empty($sale->table->name))
					              	@lang('restaurant.table'): {{$sale->table->name}}
					              @endif
					              @if($is_service_staff_enabled && !empty($sale->service_staff))
					              	<br>@lang('restaurant.service_staff'): {{$sale->service_staff->user_full_name}}
					              @endif
					            </div>
								@if(auth()->user()->can('sell.update') || auth()->user()->can('direct_sell.update'))
									<a href="{{action([\App\Http\Controllers\SellPosController::class, 'edit'], ['po' => $sale->id]).$subtype}}" class="small-box-footer bg-blue p-10">
									@lang('sale.edit_sale') <i class="fa fa-arrow-circle-right"></i>
									</a>
								@endif
								@if(auth()->user()->can('sell.delete') || auth()->user()->can('direct_sell.delete'))
									<a href="{{action([\App\Http\Controllers\SellPosController::class, 'destroy'], ['po' => $sale->id])}}" class="small-box-footer delete-sale bg-red is_suspended">
										@lang('messages.delete') <i class="fas fa-trash"></i>
					            	</a>
								@endif
								@if(!auth()->user()->can('sell.update') && auth()->user()->can('edit_pos_payment'))
									<a href="{{route('edit-pos-payment', ['po' => $sale->id])}}" 
									class="small-box-footer bg-blue p-10">
									@lang('lang_v1.add_edit_payment') <i class="fas fa-money-bill-alt"></i>
									</a>
								@endif
					         </div>
				         </div>
				        @php
				         	$c++;
				        @endphp
					@endif

					@if($c%4==0)
						<div class="clearfix"></div>
					@endif
				@empty
					<p class="text-center">@lang('purchase.no_records_found')</p>
				@endforelse
			</div>
		</div>
		<div class="modal-footer">
		    <button type="button" class="tw-dw-btn tw-dw-btn-neutral tw-text-white" data-dismiss="modal">@lang('messages.close')</button>
		</div>
	</div><!-- /.modal-content -->
</div><!-- /.modal-dialog -->

<script>
	(function() {
		function filterSuspendedSalesCards() {
			var q = ($('#suspended_sales_search').val() || '').toLowerCase().trim();
			var f = $('#suspended_sales_filter').val() || 'all';

			$('.suspended-sale-card').each(function() {
				var text = ($(this).data('search') || '').toString();
				var hasTag = ($(this).data('has-tag') || '').toString() === '1';

				var matchesText = q === '' || text.indexOf(q) !== -1;
				var matchesFilter = f === 'all' || (f === 'tagged' && hasTag) || (f === 'untagged' && !hasTag);

				$(this).toggle(matchesText && matchesFilter);
			});
		}

		$(document).off('keyup.suspendSearch').on('keyup.suspendSearch', '#suspended_sales_search', filterSuspendedSalesCards);
		$(document).off('change.suspendSearch').on('change.suspendSearch', '#suspended_sales_filter', filterSuspendedSalesCards);
	})();
</script>