@extends('layouts.app')
@section('title', __('report.sync_supervisor_report'))

@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">@lang('report.sync_supervisor_report')</h1>
</section>

<section class="content">
    <div class="row">
        <div class="col-md-3 col-xs-12">
            <div class="form-group">
                <label>@lang('messages.location')</label>
                <select class="form-control select2" id="sync_supervisor_location_filter">
                    @foreach($business_locations as $key => $value)
                        <option value="{{ $key }}">{{ $value }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="col-md-2 col-xs-6">
            <div class="form-group">
                <label>@lang('messages.status')</label>
                <select class="form-control" id="sync_supervisor_status_filter">
                    <option value="">@lang('report.all')</option>
                    <option value="pending">PENDING</option>
                    <option value="failed">FAILED</option>
                    <option value="conflict">CONFLICT</option>
                </select>
            </div>
        </div>
        <div class="col-md-2 col-xs-6">
            <div class="form-group">
                <label>@lang('report.date_range') (@lang('lang_v1.start_date'))</label>
                <input type="date" class="form-control" id="sync_supervisor_start_date" value="{{ date('Y-m-01') }}">
            </div>
        </div>
        <div class="col-md-2 col-xs-6">
            <div class="form-group">
                <label>@lang('report.date_range') (@lang('lang_v1.end_date'))</label>
                <input type="date" class="form-control" id="sync_supervisor_end_date" value="{{ date('Y-m-d') }}">
            </div>
        </div>
        <div class="col-md-3 col-xs-6">
            <div class="form-group">
                <label>&nbsp;</label>
                <button type="button" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm tw-w-full" id="sync_supervisor_refresh_btn">
                    @lang('report.apply_filters')
                </button>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-sm-3">
            @component('components.widget')
                <div class="tw-text-xs tw-uppercase tw-text-gray-500">PENDING</div>
                <div class="tw-text-2xl tw-font-semibold" id="sync_pending_count">0</div>
            @endcomponent
        </div>
        <div class="col-sm-3">
            @component('components.widget')
                <div class="tw-text-xs tw-uppercase tw-text-gray-500">FAILED</div>
                <div class="tw-text-2xl tw-font-semibold" id="sync_failed_count">0</div>
            @endcomponent
        </div>
        <div class="col-sm-3">
            @component('components.widget')
                <div class="tw-text-xs tw-uppercase tw-text-gray-500">CONFLICT</div>
                <div class="tw-text-2xl tw-font-semibold" id="sync_conflict_count">0</div>
            @endcomponent
        </div>
        <div class="col-sm-3">
            @component('components.widget')
                <div class="tw-text-xs tw-uppercase tw-text-gray-500">@lang('report.total')</div>
                <div class="tw-text-2xl tw-font-semibold" id="sync_total_count">0</div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('report.sync_by_location')])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>@lang('business.location')</th>
                                <th>PENDING</th>
                                <th>FAILED</th>
                                <th>CONFLICT</th>
                                <th>@lang('report.total')</th>
                            </tr>
                        </thead>
                        <tbody id="sync_by_location_tbody" data-no-data-text="{{ __('lang_v1.no_data') }}">
                            <tr>
                                <td colspan="5" class="text-center text-muted">@lang('lang_v1.no_data')</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>

    <div class="row">
        <div class="col-sm-12">
            @component('components.widget', ['class' => 'box-primary', 'title' => __('report.sync_pending_failed_transactions')])
                <div class="table-responsive">
                    <table class="table table-bordered table-striped" id="sync_supervisor_table">
                        <thead>
                            <tr>
                                <th>@lang('messages.date')</th>
                                <th>@lang('sale.invoice_no')</th>
                                <th>@lang('business.location')</th>
                                <th>@lang('messages.status')</th>
                                <th>@lang('lang_v1.payment_status')</th>
                                <th>@lang('report.sync_status')</th>
                                <th>@lang('report.origin_device')</th>
                                <th>@lang('report.offline_uuid')</th>
                                <th>@lang('lang_v1.added_by')</th>
                                <th>@lang('lang_v1.updated_at')</th>
                            </tr>
                        </thead>
                    </table>
                </div>
            @endcomponent
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function () {
        var sync_supervisor_table = $('#sync_supervisor_table').DataTable({
            processing: true,
            serverSide: true,
            aaSorting: [[0, 'desc']],
            ajax: {
                url: '{{ route("reports.sync_supervisor") }}',
                data: function (d) {
                    d.location_id = $('#sync_supervisor_location_filter').val();
                    d.sync_status = $('#sync_supervisor_status_filter').val();
                    d.start_date = $('#sync_supervisor_start_date').val();
                    d.end_date = $('#sync_supervisor_end_date').val();
                }
            },
            columns: [
                {data: 'transaction_date', name: 'transactions.transaction_date'},
                {data: 'invoice_no', name: 'transactions.invoice_no'},
                {data: 'location_name', name: 'bl.name'},
                {data: 'status', name: 'transactions.status'},
                {data: 'payment_status', name: 'transactions.payment_status'},
                {data: 'sync_status', name: 'transactions.sync_status', searchable: false, orderable: false},
                {data: 'origin_device_id', name: 'transactions.origin_device_id'},
                {data: 'offline_uuid', name: 'transactions.offline_uuid'},
                {data: 'added_by', name: 'added_by', orderable: false},
                {data: 'updated_at', name: 'transactions.updated_at'}
            ]
        });

        function loadSyncSummary() {
            var noDataText = $('#sync_by_location_tbody').data('no-data-text') || 'No data';
            $('#sync_by_location_tbody').html('<tr><td colspan="5" class="text-center"><i class="fas fa-sync fa-spin fa-fw"></i></td></tr>');

            $.ajax({
                method: 'GET',
                url: '{{ route("reports.sync_supervisor.summary") }}',
                dataType: 'json',
                data: {
                    location_id: $('#sync_supervisor_location_filter').val(),
                    start_date: $('#sync_supervisor_start_date').val(),
                    end_date: $('#sync_supervisor_end_date').val()
                },
                success: function (data) {
                    $('#sync_pending_count').text(data.pending_count || 0);
                    $('#sync_failed_count').text(data.failed_count || 0);
                    $('#sync_conflict_count').text(data.conflict_count || 0);
                    $('#sync_total_count').text(data.total_count || 0);

                    var rows = '';
                    if (data.by_location && data.by_location.length > 0) {
                        data.by_location.forEach(function (item) {
                            rows += '<tr>' +
                                '<td>' + (item.location_name || '') + '</td>' +
                                '<td>' + (item.pending_count || 0) + '</td>' +
                                '<td>' + (item.failed_count || 0) + '</td>' +
                                '<td>' + (item.conflict_count || 0) + '</td>' +
                                '<td>' + (item.total_count || 0) + '</td>' +
                                '</tr>';
                        });
                    } else {
                        rows = '<tr><td colspan="5" class="text-center text-muted">' + noDataText + '</td></tr>';
                    }

                    $('#sync_by_location_tbody').html(rows);
                }
            });
        }

        $('#sync_supervisor_refresh_btn, #sync_supervisor_location_filter, #sync_supervisor_status_filter, #sync_supervisor_start_date, #sync_supervisor_end_date').on('change click', function () {
            sync_supervisor_table.ajax.reload();
            loadSyncSummary();
        });

        loadSyncSummary();
    });
</script>
@endsection
