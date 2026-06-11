@extends('layouts.app')

@section('content')

<style>
/* ── Filter bar ── */
.xero-filter-bar {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 18px 20px 14px;
    margin-bottom: 16px;
}
.xero-filter-bar .filter-label {
    font-size: 11px;
    font-weight: 600;
    color: #555;
    margin-bottom: 4px;
    display: block;
}
.xero-filter-bar .form-control {
    height: 34px;
    font-size: 12px;
    border: 1px solid #ccc;
    border-radius: 4px;
    padding: 4px 10px;
    color: #222;
}
.xero-filter-bar .form-control:focus {
    border-color: #0070c4;
    box-shadow: none;
    outline: none;
}
.filter-row {
    display: grid;
    grid-template-columns: auto auto auto 1fr auto;
    gap: 10px 16px;
    align-items: end;
}
.filter-row-2 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
}
.date-range-group {
    display: flex;
    align-items: center;
    gap: 0;
}
.date-range-group input {
    border-radius: 4px 0 0 4px !important;
    border-right: none !important;
    width: 120px;
}
.date-range-group input:last-of-type {
    border-radius: 0 !important;
    border-right: 1px solid #ccc !important;
}
.date-range-group .btn-dropdown {
    border: 1px solid #ccc;
    border-left: none;
    border-radius: 0 4px 4px 0;
    background: #f5f5f5;
    padding: 0 10px;
    height: 34px;
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
    color: #555;
}
.btn-xero-filter {
    background: #fff;
    border: 1px solid #0070c4;
    color: #0070c4;
    border-radius: 4px;
    padding: 5px 16px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.btn-xero-filter:hover { background: #e8f1fb; }
.btn-xero-more {
    background: #fff;
    border: 1px solid #d0d0d0;
    color: #333;
    border-radius: 4px;
    padding: 5px 14px;
    font-size: 12px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}
.btn-xero-update {
    background: #0070c4;
    border: none;
    color: #fff;
    border-radius: 4px;
    padding: 6px 20px;
    font-size: 12px;
    cursor: pointer;
    font-weight: 500;
}
.btn-xero-update:hover { background: #005fa3; }

/* ── Report card ── */
.xero-report-card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
    padding: 28px 32px;
}
.report-title {
    font-size: 22px;
    font-weight: 400;
    color: #222;
    margin: 0 0 6px;
}
.report-company {
    font-size: 13px;
    color: #333;
    margin: 0;
}
.report-period {
    font-size: 12px;
    color: #666;
    margin: 2px 0 0;
}

/* ── Periode header row ── */
.period-header-row {
    display: flex;
    justify-content: flex-end;
    padding: 14px 0 4px;
    border-bottom: 1px solid #d0d0d0;
    font-size: 12px;
    font-weight: 600;
    color: #333;
    gap: 0;
}
.period-header-row .col-amt {
    width: 160px;
    text-align: right;
}

/* ── Report table ── */
.report-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.report-table tr.section-header td {
    background: #f5f5f5;
    font-weight: 600;
    color: #222;
    padding: 8px 10px 8px 12px;
    border-top: 1px solid #d8d8d8;
    border-bottom: 1px solid #d8d8d8;
    font-size: 12px;
}
.report-table tr.section-total td {
    font-weight: 600;
    color: #222;
    padding: 8px 10px 8px 12px;
    border-top: 1px solid #d8d8d8;
    background: #fafafa;
    font-size: 12px;
}
.report-table tr.detail-row td {
    padding: 6px 10px 6px 28px;
    color: #222;
    border-bottom: 1px solid #f0f0f0;
    cursor: pointer;
}
.report-table tr.detail-row:hover td {
    background: #f0f7ff;
}
.report-table tr.grand-total td {
    padding: 10px 10px 10px 12px;
    font-weight: 600;
    font-size: 13px;
    color: #222;
    border-top: 2px solid #333;
    border-bottom: 2px solid #333;
    background: #fafafa;
}
.report-table td.amt {
    text-align: right;
    width: 160px;
    font-variant-numeric: tabular-nums;
}
/* nilai positif biru, negatif merah (dalam kurung) */
.report-table td.amt.positive { color: #1a6abf; }
.report-table td.amt.negative { color: #c0392b; }
.report-table td.amt.total    { color: #222; }

/* ── Net profit row ── */
.report-table tr.net-profit td {
    padding: 10px 10px 10px 12px;
    font-weight: 600;
    font-size: 13px;
    background: #eaf3fb;
    color: #0070c4;
    border-top: 2px solid #0070c4;
    border-bottom: 2px solid #0070c4;
}

/* ── Compact view toggle ── */
.compact-toggle {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 18px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
    font-size: 12px;
    color: #555;
}
.compact-toggle input[type="checkbox"] { accent-color: #0070c4; width: 14px; height: 14px; }

/* loading spinner */
#reportLoading {
    display: none;
    text-align: center;
    padding: 40px;
    color: #888;
    font-size: 13px;
}
</style>

{{-- ── FILTER BAR ── --}}
<div class="xero-filter-bar">
    <form id="formFilter">
        <div class="filter-row">

            {{-- Date range --}}
            <div>
                <span class="filter-label">
                    Date range:
                    <span style="font-weight:400; color:#0070c4;" id="labelDateRange">This month</span>
                </span>
                <div class="date-range-group">
                    <input type="date" class="form-control" id="date_from" name="date_from"
                           value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                    <input type="date" class="form-control" id="date_to" name="date_to"
                           value="{{ now()->endOfMonth()->format('Y-m-d') }}">
                    <button type="button" class="btn-dropdown" id="btnDatePreset"
                            title="Pilih preset tanggal">
                        <i class="ti ti-chevron-down" style="font-size:13px;" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            {{-- Compare with --}}
            <div>
                <span class="filter-label">Compare with</span>
                <select class="form-control" name="compare_with" id="compare_with" style="width:160px;">
                    <option value="">None</option>
                    <option value="prev_month">Previous month</option>
                    <option value="prev_year">Previous year</option>
                    <option value="prev_quarter">Previous quarter</option>
                </select>
            </div>

            {{-- Compare tracking categories --}}
            <div>
                <span class="filter-label">Compare tracking categories</span>
                <select class="form-control select2" name="tracking_category" id="tracking_category"
                        style="width:200px;">
                    <option value="">None</option>
                </select>
            </div>

            {{-- Currency --}}
            <div>
                <span class="filter-label">Currency</span>
                <select class="form-control" name="currency" id="currency" style="width:200px;">
                    <option value="IDR" selected>🇮🇩 Indonesian Rupiah</option>
                    <option value="SAR">🇸🇦 Saudi Riyal</option>
                    <option value="USD">🇺🇸 US Dollar</option>
                </select>
            </div>

            {{-- spacer --}}
            <div></div>
        </div>

        <div class="filter-row-2">
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn-xero-filter" id="btnFilter">
                    <i class="ti ti-filter" style="font-size:13px;" aria-hidden="true"></i> Filter
                </button>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn-xero-more" id="btnMore">
                    <i class="ti ti-dots" style="font-size:13px;" aria-hidden="true"></i> More
                </button>
                <button type="button" class="btn-xero-update" id="btnUpdate">
                    Update
                </button>
            </div>
        </div>
    </form>
</div>

{{-- ── REPORT CARD ── --}}
<div class="xero-report-card">

    {{-- Loading state --}}
    <div id="reportLoading">
        <div class="spinner-border text-primary" role="status"
             style="width:1.6rem;height:1.6rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="mt-2">Memuat laporan...</div>
    </div>

    {{-- Report content --}}
    <div id="reportContent">

        {{-- Header --}}
        <p class="report-title">Profit and Loss</p>
        <p class="report-company">{{ config('app.company_name', 'PT An Namiroh Travelindo') }}</p>
        <p class="report-period" id="reportPeriodLabel">
            For the month ended {{ now()->endOfMonth()->isoFormat('D MMMM YYYY') }}
        </p>

        {{-- Column period header --}}
        <div class="period-header-row">
            <div class="col-amt" id="colPeriodLabel">Jun 2026</div>
        </div>

        {{-- Report table --}}
        <table class="report-table" id="reportTable">
            <tbody id="reportBody">
                {{-- Diisi via AJAX / JS --}}
            </tbody>
        </table>

        {{-- Compact view toggle --}}
        <div class="compact-toggle">
            <input type="checkbox" id="compactView">
            <label for="compactView" style="cursor:pointer; margin:0;">Compact view</label>
        </div>

    </div>{{-- /#reportContent --}}
</div>

{{-- ── DATE PRESET DROPDOWN (Bootstrap 4 dropdown) ── --}}
<div class="dropdown-menu" id="datePresetMenu" style="font-size:12px; min-width:180px;">
    <a class="dropdown-item preset-item" data-preset="this_month">This month</a>
    <a class="dropdown-item preset-item" data-preset="last_month">Last month</a>
    <a class="dropdown-item preset-item" data-preset="this_quarter">This quarter</a>
    <a class="dropdown-item preset-item" data-preset="last_quarter">Last quarter</a>
    <a class="dropdown-item preset-item" data-preset="this_year">This year (Jan – Dec)</a>
    <a class="dropdown-item preset-item" data-preset="last_year">Last year (Jan – Dec)</a>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item preset-item" data-preset="custom">Custom range</a>
</div>

@endsection


@push('scripts')
<script>
$(function () {

    // =========================================================
    // FORMAT HELPERS
    // =========================================================
    var fmt = new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    function formatAmt(val) {
        val = parseFloat(val) || 0;
        if (val < 0) {
            return '(' + fmt.format(Math.abs(val)) + ')';
        }
        return fmt.format(val);
    }

    function amtClass(val) {
        val = parseFloat(val) || 0;
        return val < 0 ? 'amt negative' : 'amt positive';
    }

    // =========================================================
    // DATE PRESETS
    // =========================================================
    var presets = {
        this_month   : function () { return [moment().startOf('month'), moment().endOf('month')]; },
        last_month   : function () { return [moment().subtract(1,'month').startOf('month'), moment().subtract(1,'month').endOf('month')]; },
        this_quarter : function () { return [moment().startOf('quarter'), moment().endOf('quarter')]; },
        last_quarter : function () { return [moment().subtract(1,'quarter').startOf('quarter'), moment().subtract(1,'quarter').endOf('quarter')]; },
        this_year    : function () { return [moment().startOf('year'), moment().endOf('year')]; },
        last_year    : function () { return [moment().subtract(1,'year').startOf('year'), moment().subtract(1,'year').endOf('year')]; },
    };

    function applyPreset(key, label) {
        if (!presets[key]) return;
        var range = presets[key]();
        $('#date_from').val(range[0].format('YYYY-MM-DD'));
        $('#date_to').val(range[1].format('YYYY-MM-DD'));
        $('#labelDateRange').text(label);
        updatePeriodLabel();
    }

    // Toggle preset dropdown
    $('#btnDatePreset').on('click', function (e) {
        e.stopPropagation();
        var $menu = $('#datePresetMenu');
        var offset = $(this).offset();
        $menu.css({
            display: $menu.is(':visible') ? 'none' : 'block',
            top: offset.top + $(this).outerHeight() + 4,
            left: offset.left - 160,
            position: 'fixed',
            zIndex: 9999
        });
    });

    $(document).on('click', '.preset-item', function () {
        var preset = $(this).data('preset');
        var label  = $(this).text();
        applyPreset(preset, label);
        $('#datePresetMenu').hide();
    });

    $(document).on('click', function () {
        $('#datePresetMenu').hide();
    });

    // =========================================================
    // PERIOD LABEL UPDATE
    // =========================================================
    function updatePeriodLabel() {
        var from = moment($('#date_from').val());
        var to   = moment($('#date_to').val());
        if (!from.isValid() || !to.isValid()) return;

        // Cek apakah satu bulan penuh
        if (from.isSame(from.clone().startOf('month'), 'day') &&
            to.isSame(to.clone().endOf('month'),   'day') &&
            from.isSame(to, 'month')) {
            $('#reportPeriodLabel').text('For the month ended ' + to.format('D MMMM YYYY'));
            $('#colPeriodLabel').text(to.format('MMM YYYY'));
        } else {
            $('#reportPeriodLabel').text(from.format('D MMM YYYY') + ' – ' + to.format('D MMM YYYY'));
            $('#colPeriodLabel').text(from.format('MMM') + ' – ' + to.format('MMM YYYY'));
        }
    }

    $('#date_from, #date_to').on('change', updatePeriodLabel);

    // =========================================================
    // SELECT2 — tracking category
    // =========================================================
    $('#tracking_category').select2({
        theme       : 'bootstrap4',
        placeholder : 'None',
        allowClear  : true,
        dropdownParent: $('body'),
        ajax: {
            url    : '{{ route("tracking-by-parent") }}',
            dataType: 'json',
            delay  : 300,
            data   : function (p) {
                return { keyword: p.term || '', name_parent_category: 'all' };
            },
            processResults: function (response) {
                if (!response.status || !response.data) return { results: [] };
                return {
                    results: (response.data.lines_category || []).map(function (item) {
                        return {
                            id  : item.item_uuid_category || item.id,
                            text: item.item_name_category
                        };
                    })
                };
            },
            cache: true
        }
    });

    // =========================================================
    // LOAD REPORT
    // =========================================================
    function loadReport() {
        var params = {
            date_from        : $('#date_from').val(),
            date_to          : $('#date_to').val(),
            compare_with     : $('#compare_with').val(),
            tracking_category: $('#tracking_category').val(),
            currency         : $('#currency').val(),
        };

        $('#reportLoading').show();
        $('#reportBody').empty();

        // $.ajax({
        //     url    : '{{ route("riport-profit-loss") }}',
        //     method : 'GET',
        //     data   : params,
        //     headers: { 'Authorization': 'Bearer ' + localStorage.getItem('token') },
        //     success: function (response) {
        //         if (!response.status) {
        //             Swal.fire('Gagal', response.message || 'Terjadi kesalahan.', 'error');
        //             return;
        //         }
        //         renderReport(response.data);
        //     },
        //     error: function (err) {
        //         Swal.fire('Error', 'Gagal memuat laporan.', 'error');
        //         console.error(err);
        //     },
        //     complete: function () {
        //         $('#reportLoading').hide();
        //     }
        // });
    }

    // =========================================================
    // RENDER REPORT
    // response.data structure:
    // {
    //   sections: [
    //     {
    //       name: 'Trading Income',
    //       items: [ { name: 'PPPU NAMIROH', amount: 18686125000 }, ... ],
    //       total: 27060595978.19
    //     },
    //     {
    //       name: 'Cost of Sales',
    //       items: [ ... ],
    //       total: 0
    //     },
    //     ...
    //   ],
    //   net_profit: 12345678.90,
    //   net_profit_label: 'Net Profit'
    // }
    // =========================================================
    function renderReport(data) {
        var $body   = $('#reportBody');
        var compact = $('#compactView').is(':checked');
        $body.empty();

        (data.sections || []).forEach(function (section) {
            // Section header
            $body.append(
                '<tr class="section-header">' +
                '<td>' + escHtml(section.name) + '</td>' +
                '<td class="amt total"></td>' +
                '</tr>'
            );

            // Detail rows (hidden in compact mode)
            if (!compact) {
                (section.items || []).forEach(function (item) {
                    var cls = amtClass(item.amount);
                    $body.append(
                        '<tr class="detail-row">' +
                        '<td>' + escHtml(item.name) + '</td>' +
                        '<td class="' + cls + '">' + formatAmt(item.amount) + '</td>' +
                        '</tr>'
                    );
                });
            }

            // Section total
            $body.append(
                '<tr class="section-total">' +
                '<td>Total ' + escHtml(section.name) + '</td>' +
                '<td class="amt total">' + formatAmt(section.total) + '</td>' +
                '</tr>'
            );
        });

        // Net profit / loss
        var npLabel = data.net_profit_label || 'Net Profit';
        var npVal   = parseFloat(data.net_profit) || 0;
        var npAmt   = formatAmt(npVal);
        $body.append(
            '<tr class="net-profit">' +
            '<td>' + escHtml(npLabel) + '</td>' +
            '<td class="amt">' + npAmt + '</td>' +
            '</tr>'
        );
    }

    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    // =========================================================
    // DEMO DATA (dipakai jika API belum tersedia)
    // Hapus blok ini setelah API siap
    // =========================================================
    function loadDemoReport() {
        var demo = {
            sections: [
                {
                    name : 'Trading Income',
                    items: [
                        { name: 'DISKON PENJUALAN (KOMPENSASI)',               amount: -4000000 },
                        { name: 'PENDAPATAN LAYANAN VAKSIN',                   amount: 1200000 },
                        { name: 'PENDAPATAN PEMBUATAN PASPOR',                 amount: 6000000 },
                        { name: 'PENDAPATAN PENJUALAN PERLENGKAPAN UMROH',     amount: 383755000 },
                        { name: 'PENDAPATAN TIKET PESAWAT ONLY',               amount: 5774565978.19 },
                        { name: 'PPPU ANTRAV',                                 amount: 161400000 },
                        { name: 'PPPU NAMIROH',                                amount: 18686125000 },
                        { name: 'PPPU RIHLAH',                                 amount: 1172250000 },
                        { name: 'PPPU TAJALLI',                                amount: 889350000 },
                        { name: 'REFUND & PEMBATALAN',                         amount: -10050000 },
                    ],
                    total: 27060595978.19
                },
                {
                    name : 'Cost of Sales',
                    items: [
                        { name: 'BIAYA KOMISI AGEN & KANTOR',                  amount: 631575000 },
                        { name: 'HARGA POKOK LAYANAN PASPOR',                  amount: 1400000 },
                        { name: 'HARGA POKOK PEMBELIAN PERLENGKAPAN UMROH',    amount: 40312000 },
                        { name: 'HPP HOTEL VILLA RETAJ (NIDA UTAMA)',          amount: 13.95 },
                    ],
                    total: 673287013.95
                },
                {
                    name : 'Operating Expenses',
                    items: [
                        { name: 'BIAYA ADMINISTRASI',                          amount: 12500000 },
                        { name: 'BIAYA GAJI KARYAWAN',                         amount: 85000000 },
                        { name: 'BIAYA PEMASARAN',                             amount: 22000000 },
                    ],
                    total: 119500000
                }
            ],
            net_profit      : 26267808964.24,
            net_profit_label: 'Net Profit'
        };
        renderReport(demo);
    }

    // =========================================================
    // EVENT BINDINGS
    // =========================================================
    $('#btnUpdate').on('click', function () {
        // Ganti loadDemoReport() dengan loadReport() saat API siap
        loadDemoReport();
        updatePeriodLabel();
    });

    $('#compactView').on('change', function () {
        // Re-render dengan mode compact
        $('#btnUpdate').trigger('click');
    });

    $('#btnFilter').on('click', function () {
        // Buka panel filter tambahan (implementasi sesuai kebutuhan)
        Swal.fire({
            title           : 'Filter',
            text            : 'Panel filter tambahan (implementasi sesuai kebutuhan).',
            icon            : 'info',
            confirmButtonText: 'OK'
        });
    });

    $('#btnMore').on('click', function () {
        Swal.fire({
            title            : 'More options',
            text             : 'Export PDF, Excel, dll. (implementasi sesuai kebutuhan).',
            icon             : 'info',
            confirmButtonText: 'OK'
        });
    });

    // =========================================================
    // INIT — load demo saat halaman pertama terbuka
    // =========================================================
    updatePeriodLabel();
    loadDemoReport();  // Ganti ke loadReport() saat API siap

});
</script>
@endpush