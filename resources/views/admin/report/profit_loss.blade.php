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
    white-space: nowrap;
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

/* ── Filter row: flex wrap supaya tidak overlapping di layar sempit ── */
.filter-row {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-end;
    gap: 12px 16px;
}
.filter-row > div {
    flex: 0 0 auto;
}

/* ── Date range group ── */
.date-range-group {
    display: flex;
    align-items: center;
    gap: 0;
}
.date-range-group input {
    border-radius: 4px 0 0 4px !important;
    border-right: none !important;
    width: 130px;
    height: 34px;
    font-size: 12px;
}
.date-range-group input:last-of-type {
    border-radius: 0 !important;
    border-right: none !important;
}
.date-range-group .btn-dropdown {
    border: 1px solid #ccc;
    border-radius: 0 4px 4px 0;
    background: #f5f5f5;
    padding: 0 10px;
    height: 34px;
    display: flex;
    align-items: center;
    cursor: pointer;
    font-size: 14px;
    color: #555;
    white-space: nowrap;
}
.date-range-group .btn-dropdown:hover { background: #ebebeb; }

/* ── Select2 multiple — fix agar tidak melar ke bawah ── */
/* Wrapper select2 untuk tracking filter */
.tracking-select-wrap {
    width: 200px;
    min-width: 160px;
    max-width: 260px;
}

/* Batasi tinggi container select2 multiple */
.tracking-select-wrap .select2-container--bootstrap4
    .select2-selection--multiple {
    min-height: 34px !important;
    max-height: 34px !important;
    overflow: hidden !important;
    display: flex !important;
    align-items: center !important;
    padding: 0 4px !important;
    border: 1px solid #ccc !important;
    border-radius: 4px !important;
    background: #fff !important;
    cursor: pointer !important;
    flex-wrap: nowrap !important;  /* tag satu baris */
}

/* Scroll horizontal tag jika banyak */
.tracking-select-wrap .select2-container--bootstrap4
    .select2-selection--multiple
    .select2-selection__rendered {
    display: flex !important;
    flex-wrap: nowrap !important;
    overflow: hidden !important;
    padding: 0 !important;
    gap: 2px !important;
    max-width: 100% !important;
    align-items: center !important;
}

/* Tag (badge) kompak */
.tracking-select-wrap .select2-container--bootstrap4
    .select2-selection--multiple
    .select2-selection__choice {
    font-size: 10px !important;
    padding: 1px 18px 1px 6px !important;
    margin: 0 !important;
    height: 20px !important;
    line-height: 18px !important;
    border-radius: 3px !important;
    background: #e8f0fe !important;
    border: 1px solid #c2d0f8 !important;
    color: #1a56db !important;
    white-space: nowrap !important;
    max-width: 90px !important;        /* potong teks panjang */
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    flex-shrink: 0 !important;
    position: relative !important;
}

/* Tombol X di dalam tag */
.tracking-select-wrap .select2-container--bootstrap4
    .select2-selection--multiple
    .select2-selection__choice__remove {
    position: absolute !important;
    right: 3px !important;
    top: 50% !important;
    transform: translateY(-50%) !important;
    font-size: 11px !important;
    color: #1a56db !important;
    line-height: 1 !important;
    padding: 0 !important;
}

/* Jika tag > 2, sembunyikan sisanya & tampilkan counter */
/* (ditangani JS lewat class .s2-overflow-hidden) */
.tracking-select-wrap .select2-selection__choice.s2-hidden {
    display: none !important;
}
.s2-overflow-badge {
    font-size: 10px;
    background: #0070c4;
    color: #fff;
    border-radius: 10px;
    padding: 1px 7px;
    margin-left: 2px;
    white-space: nowrap;
    flex-shrink: 0;
    line-height: 20px;
    height: 20px;
    display: inline-flex;
    align-items: center;
}

/* Sembunyikan input search bawaan agar tidak geser layout */
.tracking-select-wrap .select2-container--bootstrap4
    .select2-selection--multiple
    .select2-search--inline {
    flex: 1 1 40px; min-width: 40px; max-width: 80px;
}

.select2-search__field  { height: 22px; font-size: 11px; min-width: 40px; }



/* Dropdown normal */
.tracking-select-wrap .select2-dropdown {
    font-size: 12px;
    min-width: 240px !important;
    z-index: 9999 !important;
}

/* Override select2 width agar ikut .tracking-select-wrap */
.tracking-select-wrap .select2-container {
    width: 100% !important;
}

/* ── Currency select ── */
.currency-select-wrap {
    width: 200px;
}
.currency-select-wrap .form-control {
    width: 100%;
    height: 34px;
    font-size: 12px;
}

.filter-row-2 {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #f0f0f0;
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
.report-table tr.detail-row:hover td { background: #f0f7ff; }
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
.report-table td.amt.positive { color: #1a6abf; }
.report-table td.amt.negative { color: #c0392b; }
.report-table td.amt.total    { color: #222; }

.report-table tr.net-profit td {
    padding: 10px 10px 10px 12px;
    font-weight: 600;
    font-size: 13px;
    background: #eaf3fb;
    color: #0070c4;
    border-top: 2px solid #0070c4;
    border-bottom: 2px solid #0070c4;
}

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
.compact-toggle input[type="checkbox"] {
    accent-color: #0070c4;
    width: 14px;
    height: 14px;
}

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
    <form id="formFilter" autocomplete="off">
        <div class="filter-row">

            {{-- Date range --}}
            <div>
                <span class="filter-label">
                    Date range:&nbsp;<span style="font-weight:400;color:#0070c4;" id="labelDateRange">This month</span>
                </span>
                <div class="date-range-group">
                    <input type="date" class="form-control" id="date_from" name="date_start"
                           value="{{ now()->startOfMonth()->format('Y-m-d') }}">
                    <input type="date" class="form-control" id="date_to" name="date_end"
                           value="{{ now()->endOfMonth()->format('Y-m-d') }}">
                    <button type="button" class="btn-dropdown" id="btnDatePreset"
                            title="Pilih preset tanggal">
                        <i class="ti ti-chevron-down" style="font-size:13px;" aria-hidden="true"></i>
                    </button>
                </div>
            </div>

            {{-- Divisi --}}
            <div>
                <span class="filter-label">Divisi</span>
                <div class="tracking-select-wrap">
                    <select class="select2-divisi" name="tracking_divisi[]"
                            id="tracking_divisi" multiple="multiple" style="width:100%;">
                    </select>
                </div>
            </div>

            {{-- Nama Paket --}}
            <div>
                <span class="filter-label">Nama Paket</span>
                <div class="tracking-select-wrap">
                    <select class="select2-paket" name="tracking_paket_name[]"
                            id="tracking_paket_name" multiple="multiple" style="width:100%;">
                    </select>
                </div>
            </div>

            {{-- Currency --}}
            <div>
                <span class="filter-label">Currency</span>
                <div class="currency-select-wrap">
                    <select class="form-control" name="currency" id="currency">
                        <option value="IDR" selected>🇮🇩 Indonesian Rupiah</option>
                        <option value="SAR">🇸🇦 Saudi Riyal</option>
                        <option value="USD">🇺🇸 US Dollar</option>
                    </select>
                </div>
            </div>

        </div>{{-- /.filter-row --}}

        <div class="filter-row-2">
            <div class="d-flex align-items-center" style="gap:8px;">
                <button type="button" class="btn-xero-filter" id="btnFilter">
                    <i class="ti ti-filter" style="font-size:13px;" aria-hidden="true"></i> Filter
                </button>
            </div>
            <div class="d-flex align-items-center" style="gap:8px;">
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

    <div id="reportLoading">
        <div class="spinner-border text-primary" role="status" style="width:1.6rem;height:1.6rem;">
            <span class="sr-only">Loading...</span>
        </div>
        <div class="mt-2">Memuat laporan...</div>
    </div>

    <div id="reportContent">
        <p class="report-title">Profit and Loss</p>
        <p class="report-company">{{ config('app.company_name', 'PT An Namiroh Travelindo') }}</p>
        <p class="report-period" id="reportPeriodLabel">
            For the month ended {{ now()->endOfMonth()->isoFormat('D MMMM YYYY') }}
        </p>

        <div class="period-header-row">
            <div class="col-amt" id="colPeriodLabel">
                {{ now()->format('M Y') }}
            </div>
        </div>

        <table class="report-table" id="reportTable">
            <tbody id="reportBody"></tbody>
        </table>

        <div class="compact-toggle">
            <input type="checkbox" id="compactView">
            <label for="compactView" style="cursor:pointer;margin:0;">Compact view</label>
        </div>
    </div>

</div>

{{-- ── DATE PRESET DROPDOWN ── --}}
<div class="dropdown-menu" id="datePresetMenu"
     style="font-size:12px;min-width:190px;position:fixed;z-index:9999;display:none;">
    <a class="dropdown-item preset-item" data-preset="this_month"  href="javascript:void(0)">This month</a>
    <a class="dropdown-item preset-item" data-preset="last_month"  href="javascript:void(0)">Last month</a>
    <a class="dropdown-item preset-item" data-preset="this_quarter"href="javascript:void(0)">This quarter</a>
    <a class="dropdown-item preset-item" data-preset="last_quarter"href="javascript:void(0)">Last quarter</a>
    <a class="dropdown-item preset-item" data-preset="this_year"   href="javascript:void(0)">This year (Jan – Dec)</a>
    <a class="dropdown-item preset-item" data-preset="last_year"   href="javascript:void(0)">Last year (Jan – Dec)</a>
    <div class="dropdown-divider"></div>
    <a class="dropdown-item preset-item" data-preset="custom"      href="javascript:void(0)">Custom range</a>
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

  var plRouteTemplate = "{{ route('view-report-profit-loss-detail', ['account' => '__ACCOUNT__', 'date_start' => '__DATE_START__','date_end' => '__DATE_END__',    'track_paket' => '__TRACK_PAKET__','track_divisi' => '__TRACK_DIVISI__']) }}";
    function buildDetailUrl(accountId, dateStart, dateEnd, trackPaket, trackDivisi) {
        var url = plRouteTemplate
            .replace('__ACCOUNT__', accountId)
            .replace('__DATE_START__', dateStart)
            .replace('__DATE_END__', dateEnd)
            .replace('__TRACK_PAKET__', trackPaket ?? '')
            .replace('__TRACK_DIVISI__', trackDivisi ?? '');

        return url;
    }

    
    function formatAmt(val) {
        val = parseFloat(val) || 0;
        if (val < 0) return '(' + fmt.format(Math.abs(val)) + ')';
        return fmt.format(val);
    }

    function amtClass(val) {
        return (parseFloat(val) || 0) < 0 ? 'amt negative' : 'amt positive';
    }

    function escHtml(str) {
        return $('<div>').text(str || '').html();
    }

    // =========================================================
    // DATE PRESETS  (PHP 7.4 safe — semua pakai var / function)
    // =========================================================
    var presets = {
        this_month   : function () { return [moment().startOf('month'),                           moment().endOf('month')]; },
        last_month   : function () { return [moment().subtract(1,'month').startOf('month'),       moment().subtract(1,'month').endOf('month')]; },
        this_quarter : function () { return [moment().startOf('quarter'),                         moment().endOf('quarter')]; },
        last_quarter : function () { return [moment().subtract(1,'quarter').startOf('quarter'),   moment().subtract(1,'quarter').endOf('quarter')]; },
        this_year    : function () { return [moment().startOf('year'),                            moment().endOf('year')]; },
        last_year    : function () { return [moment().subtract(1,'year').startOf('year'),         moment().subtract(1,'year').endOf('year')]; }
    };

    function applyPreset(key, label) {
        if (!presets[key]) return;
        var range = presets[key]();
        $('#date_from').val(range[0].format('YYYY-MM-DD'));
        $('#date_to').val(range[1].format('YYYY-MM-DD'));
        $('#labelDateRange').text(label);
        updatePeriodLabel();
    }

    $('#btnDatePreset').on('click', function (e) {
        e.stopPropagation();
        var $menu  = $('#datePresetMenu');
        var offset = $(this).offset();
        var visible = $menu.is(':visible');
        $menu.css({
            top  : offset.top + $(this).outerHeight() + 4,
            left : offset.left - 140
        }).toggle(!visible);
    });

    $(document).on('click', '.preset-item', function (e) {
        e.preventDefault();
        applyPreset($(this).data('preset'), $(this).text().trim());
        $('#datePresetMenu').hide();
    });

    $(document).on('click', function (e) {
        if (!$(e.target).closest('#btnDatePreset, #datePresetMenu').length) {
            $('#datePresetMenu').hide();
        }
    });

    // =========================================================
    // PERIOD LABEL
    // =========================================================
    function updatePeriodLabel() {
        var from = moment($('#date_from').val());
        var to   = moment($('#date_to').val());
        if (!from.isValid() || !to.isValid()) return;

        if (from.isSame(from.clone().startOf('month'), 'day') &&
            to.isSame(to.clone().endOf('month'), 'day') &&
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
    // SELECT2 MULTIPLE — tracking filter
    // Dibungkus .tracking-select-wrap supaya CSS override tepat sasaran.
    // Dropdown ke <body> supaya tidak terpotong overflow parent.
    // =========================================================
    function initTrackingSelect($el, parentCategory) {
        $el.select2({
            theme          : 'bootstrap4',
            placeholder    : 'None',
            allowClear     : true,
            width          : '100%',   /* ikut lebar .tracking-select-wrap */
            dropdownParent : $('body'),
            ajax: {
                url      : '{{ route("tracking-by-parent") }}',
                dataType : 'json',
                delay    : 300,
                data     : function (p) {
                    return { keyword: p.term || '', name_parent_category: parentCategory };
                },
                processResults: function (response) {
                    if (!response.status || !response.data) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category || [], function (item) {
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

        // ── Overflow handler: tampilkan "+N" badge jika tag > 2 ────────
        // Dijalankan setiap kali selection berubah
        $el.on('change', function () {
            // Tunda agar DOM select2 sudah dirender
            setTimeout(function () { trimOverflowTags($el); }, 0);
        });
    }

    /**
     * Batasi tampilan tag di select2 multiple:
     * tampilkan max MAX_VISIBLE tag, sisanya sembunyikan & tambah "+N" badge.
     * Ini murni visual — value tetap dikirim semua saat submit.
     */
    var MAX_VISIBLE = 2;

    function trimOverflowTags($el) {
        var $container = $el.next('.select2-container');
        if (!$container.length) return;

        // Hapus badge lama
        $container.find('.s2-overflow-badge').remove();

        // Kembalikan semua tag yang sempat disembunyikan
        $container.find('.select2-selection__choice.s2-hidden')
            .removeClass('s2-hidden');

        var $choices = $container.find('.select2-selection__choice');
        var total    = $choices.length;

        if (total <= MAX_VISIBLE) return; // tidak perlu trim

        // Sembunyikan tag kelebihan
        $choices.each(function (i) {
            if (i >= MAX_VISIBLE) $(this).addClass('s2-hidden');
        });

        // Tambah badge "+N"
        var overflow = total - MAX_VISIBLE;
        var $badge   = $('<span class="s2-overflow-badge">+' + overflow + '</span>');
        $container.find('.select2-selection__rendered').append($badge);
    }

    initTrackingSelect($('#tracking_divisi'),     'divisi');
    initTrackingSelect($('#tracking_paket_name'), 'nama paket');

    // =========================================================
    // PRELOAD nilai tersimpan (edit mode / old())
    // =========================================================
    function preloadTrackingOption($el, uuid, parentCategory) {
        if (!uuid) return;
        ajaxRequest(
            '{{ route("tracking-detail") }}',
            'get',
            { name_parent_category: parentCategory, id: uuid },
            localStorage.getItem('token')
        ).then(function (response) {
            if (response.status == 200) {
                var d    = response.data.data;
                var text = d.item_name_category || ('ID: ' + uuid);
                var opt  = new Option(text, uuid, true, true);
                $el.append(opt).trigger('change');
            }
        }).catch(function (err) { cathError(err); });
    }

    // Contoh penggunaan:
    // preloadTrackingOption($('#tracking_divisi'),     'uuid-divisi-xxx', 'divisi');
    // preloadTrackingOption($('#tracking_paket_name'), 'uuid-paket-xxx',  'nama paket');

    // =========================================================
    // ADAPTER — ubah response API → format sections
    // =========================================================
    function adaptApiResponse(data) {
        var sections = [];

        if (data.trading_income) {
            sections.push({
                name : 'Trading Income',
                items: $.map(data.trading_income.items || [], function (i) {
                     return { coa_id: i.coa_id, name: i.name, amount: i.total };
                }),
                total: data.trading_income.total
            });
        }
        if (data.cost_of_sales) {
            sections.push({
                name : 'Cost of Sales',
                items: $.map(data.cost_of_sales.items || [], function (i) {
                   return { coa_id: i.coa_id, name: i.name, amount: i.total }; 
                }),
                total: data.cost_of_sales.total
            });
        }

        return {
            sections         : sections,
            gross_profit     : parseFloat(data.gross_profit) || 0,
            net_profit       : parseFloat(data.net_profit)   || 0,
            net_profit_label : (parseFloat(data.net_profit) || 0) >= 0 ? 'Net Profit' : 'Net Loss'
        };
    }

    // =========================================================
    // LOAD REPORT — AJAX
    // =========================================================
    var _lastData = null;

    function loadReport() {
        // Kumpulkan semua UUID tracking yang dipilih (bisa multiple)

        var divisiVals = $('#tracking_divisi').val()      || [];
        var paketVals  = $('#tracking_paket_name').val()  || [];

        var params = {
            date_start           : $('#date_from').val(),
            date_end             : $('#date_to').val(),
            currency             : $('#currency').val(),
            // tracking_divisi      : divisiVals,
            // tracking_paket_name  : paketVals
        };


        $.each(divisiVals, function(i, val) {
            params['tracking_divisi[' + i + ']'] = val;
        });
        
        $.each(paketVals, function(i, val) {
            params['tracking_paket_name[' + i + ']'] = val;
        });


        $('#reportLoading').show();
        $('#reportContent').hide();

        ajaxRequest(
            '{{ route("profit-and-loss") }}',
            'GET',
            params,
            localStorage.getItem('token')
        )
        .then(function (response) {
            console.log('res',response)
            if (!response.status || !response.data) {
                throw new Error(response.message || 'Gagal memuat data');
            }
            var adapted = adaptApiResponse(response.data.data);
            _lastData   = adapted;
            renderReport(adapted);
            console.log('adp',adapted)
        })
        .catch(function (err) {
            cathError(err);
        })
        .finally(function () {
            $('#reportLoading').hide();
            $('#reportContent').show();
        });
    }

    // =========================================================
    // RENDER REPORT
    // =========================================================

    
    
    function renderReport(data) {
        var $body   = $('#reportBody');
        var compact = $('#compactView').is(':checked');
        $body.empty();

        // Ambil sekali di luar loop
        var dateStart  = $('#date_from').val();
        var dateEnd    = $('#date_to').val();
        var divisiVals = ($('#tracking_divisi').val() || []).join(',');
        var paketVals  = ($('#tracking_paket_name').val() || []).join(',');

        $.each(data.sections || [], function (idx, section) {

            $body.append(
                '<tr class="section-header">' +
                '<td>' + escHtml(section.name) + '</td>' +
                '<td class="amt total"></td>' +
                '</tr>'
            );

            if (!compact) {
                $.each(section.items || [], function (i, item) {
                    var detailUrl = buildDetailUrl(item.coa_id, dateStart, dateEnd, paketVals, divisiVals);

                    $body.append(
                        '<tr class="detail-row">' +
                        '<td>' + escHtml(item.name) + '</td>' +
                        '<td class="' + amtClass(item.amount) + '">' +
                            '<a href="' + detailUrl + '" target="_blank">' + formatAmt(item.amount) + '</a>' +
                        '</td>' +
                        '</tr>'
                    );
                });
            }

            $body.append(
                '<tr class="section-total">' +
                '<td>Total ' + escHtml(section.name) + '</td>' +
                '<td class="amt total">' + formatAmt(section.total) + '</td>' +
                '</tr>'
            );
        });

        if (data.gross_profit !== undefined) {
            $body.append(
                '<tr class="grand-total">' +
                '<td>Gross Profit</td>' +
                '<td class="amt total ' + amtClass(data.gross_profit) + '">' + formatAmt(data.gross_profit) + '</td>' +
                '</tr>'
            );
        }

        var npLabel = data.net_profit_label || 'Net Profit';
        $body.append(
            '<tr class="net-profit">' +
            '<td>' + escHtml(npLabel) + '</td>' +
            '<td class="amt">' + formatAmt(data.net_profit || 0) + '</td>' +
            '</tr>'
        );
    }
    // =========================================================
    // EVENT BINDINGS
    // =========================================================
    $('#btnUpdate').on('click', function () {
        updatePeriodLabel();
        loadReport();
    });

    $('#compactView').on('change', function () {
        if (_lastData) renderReport(_lastData);
    });

    $('#btnFilter').on('click', function () {
        Swal.fire({
            title: 'Filter', icon: 'info',
            text : 'Panel filter tambahan (implementasi sesuai kebutuhan).',
            confirmButtonText: 'OK'
        });
    });

    $('#btnMore').on('click', function () {
        Swal.fire({
            title: 'More options', icon: 'info',
            text : 'Export PDF, Excel, dll. (implementasi sesuai kebutuhan).',
            confirmButtonText: 'OK'
        });
    });

    // =========================================================
    // INIT
    // =========================================================
    updatePeriodLabel();
    loadReport();
});
</script>
@endpush