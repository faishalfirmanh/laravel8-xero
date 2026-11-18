@extends('layouts.app')

@section('content')

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Log History</h5>
    </div>

    {{-- Filter tanggal --}}
    <div class="card-body pb-0">
        <div class="row align-items-end">
            <div class="col-md-3 mb-3">
                <label for="filterDateStart" class="mb-1">Date Start</label>
                <input type="date" id="filterDateStart" class="form-control">
            </div>
            <div class="col-md-3 mb-3">
                <label for="filterDateEnd" class="mb-1">Date End</label>
                <input type="date" id="filterDateEnd" class="form-control">
            </div>
            <div class="col-md-4 mb-3">
                <button type="button" id="btnFilter" class="btn btn-primary">Filter</button>
                <button type="button" id="btnResetFilter" class="btn btn-secondary">Reset</button>
            </div>
        </div>
        <div id="filterError" class="text-danger small mb-2" style="display:none;"></div>
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
                    {{-- <th>User ID</th> --}}
                    <th>IP Address</th>
                    <th>Browser</th>
                    <th>Action</th>
                    <th>Date</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

@endsection

@push('scripts')
<script>
let table;

// Param tambahan untuk request ke server. Isinya diubah saat tombol Filter / Reset diklik.
const tableParams = { kolom_name: 'action' };

function showFilterError(msg) {
    $('#filterError').text(msg).show();
}

function clearFilterError() {
    $('#filterError').text('').hide();
}

function applyDateFilter() {
    const start = $('#filterDateStart').val();
    const end   = $('#filterDateEnd').val();

    clearFilterError();

    // date_start diisi -> date_end wajib (dan sebaliknya)
    if ((start && !end) || (!start && end)) {
        showFilterError('Date Start dan Date End harus diisi keduanya.');
        return;
    }

    // date_end tidak boleh kurang dari date_start (format YYYY-MM-DD, aman dibandingkan sebagai string)
    if (start && end && end < start) {
        showFilterError('Date End tidak boleh kurang dari Date Start.');
        return;
    }

    if (start && end) {
        tableParams.date_start = start;
        tableParams.date_end   = end;
    } else {
        delete tableParams.date_start;
        delete tableParams.date_end;
    }

    table.ajax.reload(); // kembali ke halaman 1
}

function resetDateFilter() {
    $('#filterDateStart').val('');
    $('#filterDateEnd').val('').removeAttr('min');

    clearFilterError();

    delete tableParams.date_start;
    delete tableParams.date_end;

    table.ajax.reload();
}

$(document).ready(function () {

    table = initGlobalDataTableToken(
        '#tableLogHistory',
        `{{ route('rep-log-history') }}`,
        [
            {
                data: null,
                className: 'text-center',
                render: function(d, t, r, m){
                    return m.row + m.settings._iDisplayStart + 1;
                }
            },
            // {
            //     data: 'user_id',
            //     name: 'user_id',
            //     render: d => d ?? '-'
            // },
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
                    return `<span title="${data}">${data.substring(0, 130)}...</span>`;
                }
            },
            {
                data: 'action',
                name: 'action'
            },
            {
                data: 'created_at',
                name: 'created_at',
                render: function(data){
                    return convertStringDate(data)
                }
            },
        ],
        tableParams
    );

    // Date End tidak bisa dipilih sebelum Date Start
    $('#filterDateStart').on('change', function () {
        const start = $(this).val();
        if (start) {
            $('#filterDateEnd').attr('min', start);
        } else {
            $('#filterDateEnd').removeAttr('min');
        }
    });

    $('#btnFilter').on('click', applyDateFilter);
    $('#btnResetFilter').on('click', resetDateFilter);

});
</script>
@endpush