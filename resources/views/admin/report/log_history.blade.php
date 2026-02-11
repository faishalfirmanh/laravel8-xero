@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Log History</h5>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-bordered" id="tableLogHistory">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>User ID</th>
                    <th>IP Address</th>
                    <th>Browser</th>
                    <th>Action</th>
                    <th>Created By</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
let table;

$(document).ready(function () {

    table = initGlobalDataTableToken(
        '#tableLogHistory',
        `{{ route('list-log-history') }}`,
        [
            {
                data: null,
                className: 'text-center',
                render: function(d, t, r, m){
                    return m.row + m.settings._iDisplayStart + 1;
                }
            },
            {
                data: 'user_id',
                name: 'user_id',
                render: d => d ?? '-'
            },
            {
                data: 'ip_address',
                name: 'ip_address',
                render: d => d ?? '-'
            },
            {
                data: 'browser',
                name: 'browser',
                render: function (data) {
                    if (!data) return '-';
                    return `<span title="${data}">${data.substring(0, 40)}...</span>`;
                }
            },
            {
                data: 'action',
                name: 'action'
            },
            {
                data: 'created_by',
                name: 'created_by',
                render: d => d ?? '-'
            },
        ],
        { kolom_name: 'action' }
    );

});
</script>
@endpush
