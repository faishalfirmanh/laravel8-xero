@extends('layouts.app')

@section('content')

<style>
    .modal-xxl {
        max-width: 95% !important;
        width: 95% !important;
    }
    
    #modalCreateHotel .modal-content {
        min-height: 85vh;
    }
    
    #itemTable th, #itemTable td {
        vertical-align: middle;
    }

    .select2-dropdown {
        min-width: 350px !important;   /* Lebar dropdown Account */
    }
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Hotel</h5>

        <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
            <i class="ti ti-plus me-1"></i> Tambah Bills
        </button>
    </div>

    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>

    <div class="table-responsive p-3">
        <table class="table table-striped table-bordered mt-0" id="tableHotel">
            <thead class="table-dark">
                <tr>
                    <th width="5%">No</th>
                    <th>From</th>
                    <th>Status</th>
                    <th>Reference</th>
                    <th>Date</th>
                    <th>Due Date</th>
                    <th>Paid</th>
                    <th>Due</th>
                       
                    <th width="15%">Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<div class="modal fade" id="modalCreateHotel" tabindex="-1" role="dialog" aria-hidden="true">
   <div class="modal-dialog modal-xl modal-dialog-centered" 
         style="max-width: 95% !important; width: 95% !important;" 
         role="document">
        <div class="modal-content">
            
            <div class="modal-header">
                <h5 class="modal-title">Tambah Invoice / Bill Baru</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <form id="formCreateHotel">
                @csrf
                <input type="hidden" name="idHotelInput" id="idHotelInput">

                <ul class="nav nav-tabs px-3 pt-3" id="myTab" role="tablist">
                    <li class="nav-item"><a class="nav-link active" id="header-tab" data-toggle="tab" href="#headerTab">Header</a></li>
                    <li class="nav-item"><a class="nav-link" id="detail-tab" data-toggle="tab" href="#detailTab">Detail Item</a></li>
                </ul>

                <div class="tab-content">

                    <!-- TAB HEADER -->
                    <div class="tab-pane fade show active p-3" id="headerTab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>From (Supplier / Contact)</label>
                                    <select class="form-control select2-contact" name="uuid_from" id="contact_id" style="width: 100%;" required></select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Date</label>
                                    <input type="date" class="form-control" id="date_req" name="date_req" value="{{ date('Y-m-d') }}" required>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Due Date</label>
                                    <input type="date" id="due_date" class="form-control" name="due_date" value="{{ date('Y-m-d', strtotime('+30 days')) }}">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label>Reference</label>
                                    <input type="text" id="ref_id" class="form-control" name="reference">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label>Currency</label>
                                    <select class="form-control" id="cur_id" name="currency" required>
                                        <option value="0">Pilih mata uang</option>
                                        <option value="IDR">IDR - Indonesian Rupiah</option>
                                        <option value="SAR">SAR - Saudi Riyal</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB DETAIL -->
                    <div class="tab-pane fade p-3" id="detailTab">
                        <div class="table-responsive">
                            <input type="hidden" id="d_id_parent_bill" name="bills_parent_id"/>
                            <table class="table table-bordered table-hover" id="itemTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="40" class="text-center">No</th>
                                        <th style="min-width: 280px;">Item / Description</th>
                                        <th width="80" class="text-center">Qty</th>
                                        <th width="130" class="text-right">Unit Price</th>
                                        <th style="min-width: 220px;">Account</th>
                                        <th width="100" class="text-center">Tax Rate (%)</th>
                                        <th style="min-width: 180px;">Nama Paket</th>
                                        <th style="min-width: 160px;">Divisi</th>
                                        <th width="140" class="text-right">Amount (IDR)</th>
                                        <th width="50" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm mt-2" onclick="addNewRow()">
                            <i class="ti ti-plus"></i> Add a new line
                        </button>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" onclick="switchToDetailTab()" class="btn btn-info" id="btnNext">Next → Detail Item</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    //console.log("token",localStorage.getItem("token"))
    var table;
    // --- 1. DATATABLE ---
    let columnHotel = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'uuid_from', name: 'uuid_from' },
        {
            data: 'status',
            name: 'status',
            render: function(data) {
                if(data == 0) return '<span class="badge badge-secondary">Draft</span>'; 
                if(data == 1) return '<span class="badge badge-success">Awaiting Payment</span>'; // BS4 pakai badge-success
                if(data == 2) return '<span class="badge badge-info">Madinah</span>';   // BS4 pakai badge-info
                return '-';
            }
        },
        { data: 'reference', name: 'reference' },
        { data: 'date_req', name: 'date_req' },
        { data: 'due_date', name: 'due_date' },
        { data: 'nominal_paid', name: 'nominal_paid' },
        { data: 'nominal_due', name: 'nominal_due' },
        {
            data: "id",
            orderable: false,
            searchable: false,
            className: "text-center",
            render: function(data, type, row) {
                console.log('idd',data)
                // Di sini saya asumsikan Edit juga pakai Modal, jadi nanti pakai data-toggle="modal" juga
                let btnEdit = `<a href="javascript:;" onclick="${loadDataHotel(data)}" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
                let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                return btnEdit + btnHapus;
            },
        }
    ];


    $('#tableHotel').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); // Ambil data baris tersebut

        $('#idHotelInput').val(id);
        $('#nameHotel').val(rowData.name);
        $('#typeLocation').val(rowData.type_location_hotel).change(); // .change() untuk memicu update jika pakai select2

        // Ubah Judul Modal dan Tampilkan
        $('.modal-title').text('Edit Hotel ' +rowData.name);
        $('#modalCreateHotel').modal('show');
    });

    function loadDataHotel(id){
        $("#idHotelInput").val(id)

    }

    function initAllSelect2() {
        // Contact (sudah ada sebelumnya)
       $('.select2-contact').select2({
            placeholder: "Cari nama contact...",
            allowClear: true,
            minimumInputLength: 0,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('list-contact-select2') }}",  
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        page: params.page || 1,
                        keyword: params.term || '',  
                        limit: 10
                    };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;

                    return {
                        results: $.map(response.data.data, function(item) {
                          
                            return {
                                id: item.id,                   
                                text: item.full_name,
                                phone: item.phone_number || '-'
                            };
                        }),
                        pagination: {
                            more: response.data.next_page_url !== null
                        }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.phone})</small></span>`);
            },
            templateSelection: function(item) {
                if (!item.id) return item.text;
                return item.text;
            }
        });
    }


    
    $('#tableHotel').on('click', '.deleted-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data();
        let hotelName = rowData ? rowData.name : 'Data ini';

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `Anda akan menghapus hotel "${hotelName}". Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', // Merah untuk bahaya
            cancelButtonColor: '#3085d6', // Biru untuk batal
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest( `{{ route('deleteMasterHotel') }}`,'POST',{id : id}, localStorage.getItem("token"))
                .then(response =>{
                    if(response.status == 200){
                        Swal.fire({
                            title: "Hapus hotel sukses",
                            text: "Berhasil hapus",
                            icon: "success"
                        });
                    }
                    table.ajax.reload()
                })
                .catch((err)=>{
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                    //console.log('error select2 invoice',err);
                })
            }
        });

    });

    $("#button_add_hotel").on("click",function(){
        $('#idHotelInput').val(0);
        $('#currency').val(0);
        $("#ref_id").val('');
        $("#d_id_parent_bill").val(0);
    });

    function tambahDataHotel(){
          $("#idHotelInput").val(0)
    }

     table = initGlobalDataTableToken(
        '#tableHotel',
        `{{ route('purchase-bills') }}`,
        columnHotel,
        { "kolom_name": "uuid_from" }
    );

    // --- 2. AJAX SUBMIT ---
    $('#formCreateHotel').on('submit', function(e) {
        $('.select2-account, .select2-paket, .select2-divisi').each(function() {
            if ($(this).data('select2')) {
                $(this).trigger('change');
            }
        });
        e.preventDefault();

        let formData = $(this).serialize();
        let params = new URLSearchParams(formData);
        let idInput = params.get('idHotelInput');
        let id_bill = (idInput && idInput > 0) ? idInput : null;


        let selectedData = {
            id: id_bill,
            uuid_from: params.get('uuid_from'),
            date_req: params.get('date_req'),
            due_date: params.get('due_date'),
            reference: params.get('reference'),
            currency: params.get('currency'),
         

            account_id: $('select[name="account_id[]"]').map(function(){ return $(this).val(); }).get(),
            desc: $('input[name="description[]"]').map(function(){ return $(this).val(); }).get(),
            qty: $('input[name="qty[]"]').map(function(){ return $(this).val(); }).get(),
            unit_price: $('input[name="unit_price[]"]').map(function(){ return $(this).val(); }).get(),
            tax_rate: $('input[name="tax_rate[]"]').map(function(){ return $(this).val(); }).get(),
            paket_tracking_uuid: $('select[name="nama_paket[]"]').map(function(){ return $(this).val(); }).get(),
            divisi_travel_tracking_uuid: $('select[name="divisi[]"]').map(function(){ return $(this).val(); }).get(),
        };

        ajaxRequest(`{{ route('save-p-bills') }}`, 'POST', selectedData, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                        $('#modalCreateHotel').modal('hide');
                        table.ajax.reload();
                    }
                })
                .catch((err) => {
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                    console.log('error submit', err);
                });
            });


    $('#modalCreateHotel').on('show.bs.modal', function () {
        $('#header-tab').tab('show');
        $('#itemTable tbody').empty();
        initAllSelect2();
        addNewRow(); // tambah 1 baris kosong otomatis
    });

});

    function addNewRow() {
        let rowCount = $('#itemTable tbody tr').length + 1;

        let newRow = `
            <tr>
                <td>${rowCount}</td>
                <td><input type="text" class="form-control" required name="description[]" placeholder="Deskripsi item"></td>
                <td><input type="number" class="form-control" required name="qty[]" value="1"></td>
                <td><input type="number" class="form-control" required name="unit_price[]" step="0.01"></td>
                <td>
                    <select class="select2-account form-control" required name="account_id[]" style="width:100%;">
                        <option value="">Pilih Account...</option>
                    </select>
                </td>
                <td><input type="text" class="form-control" name="tax_rate[]" value="0"></td>
                <td>
                    <select class="select2-paket form-control" name="nama_paket[]" style="width:100%;">
                        <option value="">Pilih Paket...</option>
                    </select>
                </td>
                <td>
                    <select class="select2-divisi form-control" name="divisi[]" style="width:100%;">
                        <option value="">Pilih Divisi...</option>
                    </select>
                </td>
                <td><input type="text" class="form-control text-right" name="amount[]" readonly></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button></td>
            </tr>`;

        $('#itemTable tbody').append(newRow);

        let $lastRow = $('#itemTable tbody tr:last');

        // ====================== ACCOUNT SELECT2 ======================
        $lastRow.find('.select2-account').select2({
            placeholder: "Pilih Account...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('get-all-coa-select2') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return {
                        keyword: params.term || '',
                        page: params.page || 1
                    };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data?.data || [], function(item) {
                            return {
                                id: item.id,
                                text: item.name,
                                account_type: item.account_type || '-'
                            };
                        }),
                        pagination: {
                            more: response.data?.next_page_url !== null
                        }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.account_type})</small></span>`);
            }
        });

        // ====================== PAKET SELECT2 ======================
        $lastRow.find('.select2-paket').select2({
            placeholder: "Pilih Paket...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() {
                    return {
                        name_parent_category: 'nama paket'
                    };
                },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) {
                        return { results: [] };
                    }

                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return {
                                id: item.item_uuid_category || item.id,
                                text: item.item_name_category
                            };
                        })
                    };
                },
                cache: true
            }
        });

        // ====================== DIVISI SELECT2 ======================
        $lastRow.find('.select2-divisi').select2({
            placeholder: "Pilih Divisi...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() {
                    return {
                        name_parent_category: 'divisi'
                    };
                },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) {
                        return { results: [] };
                    }

                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return {
                                id: item.item_uuid_category || item.id,
                                text: item.item_name_category
                            };
                        })
                    };
                },
                cache: true
            }
        });


        const $qty = $lastRow.find('input[name="qty[]"]');
        const $unitPrice = $lastRow.find('input[name="unit_price[]"]');
        const $amount = $lastRow.find('input[name="amount[]"]');

        function calculateAmount() {
            let qty = parseFloat($qty.val()) || 0;
            let price = parseFloat($unitPrice.val()) || 0;
            let total = qty * price;
            $amount.val(total.toFixed(2));
            calculateGrandTotal()
        }

        $qty.on('input keyup', calculateAmount);
        $unitPrice.on('input keyup', calculateAmount);

        calculateAmount();

    }

    
    
    
    function switchToDetailTab() {
        $('#detail-tab').tab('show');
    }

    function calculateGrandTotal() {
        let total_grand = 0;
        $('#itemTable tbody tr').each(function() {
            let amountStr = $(this).find('input[name="amount[]"]').val();
            let amount_row = parseFloat(amountStr) || 0;
            total_grand += amount_row;
        });

        // Update or create footer total
        let $tfoot = $('#itemTable tfoot');
        if ($tfoot.length === 0) {
            $('#itemTable').append(`
                <tfoot>
                    <tr class="table-info">
                        <td colspan="8" class="text-right font-weight-bold">Total Amount</td>
                        <td class="text-right font-weight-bold" id="grandTotal" style="font-size: 1.1em;">0.00</td>
                        <td></td>
                    </tr>
                </tfoot>
            `);
        }

        $('#grandTotal').text(formatCurrency(parseFloat(total_grand).toFixed(2)));
        console.log('totalnya',total_grand)
    }

    function removeRow(btn) {
        $(btn).closest('tr').remove();
        calculateGrandTotal();
    }

</script>
@endpush
