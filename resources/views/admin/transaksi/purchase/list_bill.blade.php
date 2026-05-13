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

    .dropdown-menu {
       z-index: 1060 !important;
    }
</style>

<div class="card shadow mb-5">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Hotel</h5>

        <div>
            {{-- Tambahan Opsional: Tombol untuk mengecek row yang di-select --}}
            <button type="button" id="btnProsesSelected" class="btn btn-success me-2">
                <i class="ti ti-check me-1"></i> Cek Data Terpilih
            </button>

            <button type="button" onclick="" id="button_add_hotel" class="btn btn-primary" data-toggle="modal" data-target="#modalCreateHotel">
                <i class="ti ti-plus me-1"></i> Tambah Bills
            </button>
        </div>
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

                <div class="modal-footer d-flex justify-content-end">
                    <!-- Hidden input untuk menyimpan nilai pilihan (1, 2, atau 3) -->
                    <input type="hidden" name="action_type" id="actionTypeValue" value="">

                    <button type="button" class="btn btn-secondary mr-2" data-dismiss="modal">Cancel</button>

                    <!-- Tombol Save yang HANYA berfungsi sebagai pembuka dropdown -->
                    <div class="btn-group dropup">
                        <button type="button" class="btn btn-primary dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            Save
                        </button>
                        
                        <!-- Menu Dropdown, disejajarkan ke kanan -->
                        <div class="dropdown-menu dropdown-menu-right shadow">
                            
                            <!-- Pilihan 1 (Value: 1) -->
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="1">
                                <i class="ti ti-calendar mr-2" style="font-size: 1.2rem;"></i>
                                <span>Approve</span>
                            </button>
                            
                            <button type="submit" class="dropdown-item d-flex align-items-center text-primary font-weight-bold action-submit" value="0">
                                <i class="ti ti-bookmark mr-2" style="font-size: 1.2rem;"></i>
                                <span>Save draft</span>
                            </button>

                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(document).ready(function() {
    var table;

    // --- HELPER FUNCTION ---
    function formatCurrency(amount) {
        return new Intl.NumberFormat('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(amount);
    }

    // --- 1. DATATABLE CONFIG ---
    let columnBills = [
        {
            data: null,
            className: "text-center",
            render: function(data, type, row, meta) {
                return meta.row + meta.settings._iDisplayStart + 1;
            },
        },
        { data: 'name_contact', name: 'name_contact' },
        {
            data: 'status',
            name: 'status',
            render: function(data) {
                if(data == 0) return '<span class="badge badge-secondary">Draft</span>'; 
                if(data == 1) return '<span class="badge badge-success">Awaiting Payment</span>'; 
                if(data == 2) return '<span class="badge badge-info">Madinah</span>'; 
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
                let btnEdit = `<a href="javascript:;" data-id="${data}" class="text-primary edit-hotel mr-2"><i class="ti ti-pencil"></i></a>`;
               // let btnHapus = `<a href="javascript:;" data-id="${data}" class="text-danger deleted-hotel"><i class="ti ti-trash"></i></a>`;
                return btnEdit ;
            },
        }
    ];

    // FIX: Menggunakan initGlobalDataTableToken dan menambahkan logika addClass/removeClass
    table = initGlobalDataTableTokenSelected(
        '#tableHotel',
        `{{ route('purchase-bills') }}`,
        columnBills,
        { "kolom_name": "uuid_from" }, // Parameter 4: Extra Params
        {                              // Parameter 5: Options DataTables khusus untuk select row
            rowCallback: function(row, data) {
                $(row).css('cursor', 'pointer'); // Ubah kursor jadi tangan
                $(row).off('click').on('click', function() {
                    // Jika baris sudah terpilih, maka batalkan pilihannya
                    if ($(this).hasClass('selected')) {
                        $(this).removeClass('selected table-active');
                    } else {
                        // Jika baris belum terpilih, hapus pilihan dari baris lain, lalu pilih baris ini
                        table.$('tr.selected').removeClass('selected table-active');
                        $(this).addClass('selected table-active');
                    }
                });
            }
        }
    );

    // FIX: Aksi tombol uji coba select row
    $('#btnProsesSelected').on('click', function() {
        let selectedRowData = table.row('.selected').data();
        if (!selectedRowData) {
            Swal.fire('Oops!', 'Pilih data terlebih dahulu dengan mengklik salah satu baris di tabel!', 'warning');
            return;
        }
        console.log("Data yang dipilih:", selectedRowData);
        Swal.fire('Berhasil', 'Anda memilih Reference: ' + selectedRowData.reference, 'success');
        // Lakukan aksi dengan selectedRowData.id, selectedRowData.reference, dll
    });

    // --- 2. INITIALIZE GLOBAL SELECT2 ---
    function initAllSelect2() {
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

    initAllSelect2();

    // --- 3. MODAL & BUTTON EVENTS ---
    $("#button_add_hotel").on("click", function(){
        $('#idHotelInput').val(0);
        $('#cur_id').val(0); // FIX: selector val() currency
        $("#ref_id").val('');
        $("#d_id_parent_bill").val(0);
        $('#formCreateHotel')[0].reset();
        
        $('.modal-title').text('Tambah Invoice / Bill Baru');
    });

    $('#modalCreateHotel').on('show.bs.modal', function () {
        $('#header-tab').tab('show');
        
        if ($('#idHotelInput').val() == 0) {
            $('#contact_id').empty().trigger('change'); 
            $('#itemTable tbody').empty();
            addNewRow(); 
        }
    });

    // --- 4. EDIT FUNCTIONALITY ---
    $('#tableHotel').on('click', '.edit-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data(); 

        $('#idHotelInput').val(id);
        $('.modal-title').text('Edit Bill ' + (rowData.reference || ''));
        
        loadBills(id);
        
        $('#modalCreateHotel').modal('show');
    });

    function loadBills(id){
        $("#idHotelInput").val(id);
        $('#itemTable tbody').empty(); 
        $('#contact_id').prop('disabled', true); 

        ajaxRequest( `{{ route('detail-bills') }}`,'GET',{id : id}, localStorage.getItem("token"))
            .then(response =>{
                if(response.status == 200){
                    let data_res = response.data.data;
                    
                    let contactId = data_res.uuid_from;
                    let contactName = data_res.get_contact_from ? data_res.get_contact_from.full_name : 'Nama tidak ditemukan';
                 
                    let newOption = new Option(contactName, contactId, true, true);
                    $('#contact_id').empty().append(newOption).trigger('change');
                    
                    $("#ref_id").val(data_res.reference || '');
                    $('#cur_id').val(data_res.currency).trigger('change');
                    $('#date_req').val(data_res.date_req).trigger('change');
                    $('#due_date').val(data_res.due_date).trigger('change');

                    let details = data_res.get_detail;
                    if (details && details.length > 0) {
                        details.forEach(function(item) {
                            addNewRow(item); 
                        });
                    } else {
                        addNewRow(); 
                    }
                    
                    calculateGrandTotal();
                }
            })
            .catch((err)=>{
                console.log('error',err);
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            })
            .finally(() => {
                $('#contact_id').prop('disabled', false); 
            });
    }

    // --- 5. DELETE FUNCTIONALITY ---
    $('#tableHotel').on('click', '.deleted-hotel', function() {
        let id = $(this).data('id');
        let rowData = table.row($(this).parents('tr')).data();
        let refName = rowData ? rowData.reference : 'Data ini';

        Swal.fire({
            title: 'Apakah Anda yakin?',
            text: `Anda akan menghapus bill "${refName}". Data yang dihapus tidak dapat dikembalikan!`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33', 
            cancelButtonColor: '#3085d6', 
            confirmButtonText: 'Ya, Hapus!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                ajaxRequest( `{{ route('deleteMasterHotel') }}`,'POST',{id : id}, localStorage.getItem("token"))
                .then(response =>{
                    if(response.status == 200){
                        Swal.fire({ title: "Sukses", text: "Berhasil hapus", icon: "success" });
                        table.ajax.reload(null, false); // Reload tanpa refresh page number
                    }
                })
                .catch((err)=>{
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                });
            }
        });
    });


    $('.action-submit').on('click', function() {
        let actionValue = $(this).val();
        $('#actionTypeValue').val(actionValue);
    });

    // --- 6. SAVE FUNCTIONALITY ---
    $('#formCreateHotel').on('submit', function(e) {
        e.preventDefault();
        
        $('.select2-account, .select2-paket, .select2-divisi').each(function() {
            if ($(this).data('select2')) { $(this).trigger('change'); }
        });

        let formData = $(this).serialize();
        let params = new URLSearchParams(formData);
        let idInput = params.get('idHotelInput');
        let id_bill = (idInput && idInput > 0) ? idInput : null;
        let action_selected = params.get('action_type');

        console.log('actyn',action_selected)

        let selectedData = {
            id: id_bill,
            uuid_from: params.get('uuid_from'),
            date_req: params.get('date_req'),
            due_date: params.get('due_date'),
            reference: params.get('reference'),
            currency: params.get('currency'),
            action_save : action_selected,
         
            account_id: $('select[name="account_id[]"]').map(function(){ return $(this).val(); }).get(),
            desc: $('input[name="description[]"]').map(function(){ return $(this).val(); }).get(),
            qty: $('input[name="qty[]"]').map(function(){ return $(this).val(); }).get(),
            unit_price: $('input[name="unit_price[]"]').map(function(){ return $(this).val(); }).get(),
            tax_rate: $('input[name="tax_rate[]"]').map(function(){ return $(this).val(); }).get(),
            paket_tracking_uuid: $('select[name="nama_paket[]"]').map(function(){ return $(this).val(); }).get(),
            divisi_travel_tracking_uuid: $('select[name="divisi[]"]').map(function(){ return $(this).val(); }).get(),
            id_detail:$('input[name="id_detail[]"]').map(function(){ return $(this).val(); }).get(),
        };

        ajaxRequest(`{{ route('save-p-bills') }}`, 'POST', selectedData, localStorage.getItem("token"))
            .then(response => {
                if(response.status == 200){
                    Swal.fire('Sukses!', 'Data berhasil disimpan.', 'success');
                    $('#modalCreateHotel').modal('hide');
                    table.ajax.reload(null, false);
                }
            })
            .catch((err) => {
                Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
            });
    });

    // --- 7. GLOBAL FUNCTIONS EXPOSED TO WINDOW ---
    window.switchToDetailTab = function() {
        $('#detail-tab').tab('show');
    };

    window.removeRow = function(btn) {
        $(btn).closest('tr').remove();
        calculateGrandTotal();
    };

    window.addNewRow = function(item = null) {
        let rowCount = $('#itemTable tbody tr').length + 1;

        let id_detail_row = item ? item.id : 0;
        let desc = item ? (item.desc || '') : '';
        let qty = item ? item.qty : 1;
        let price = item ? parseFloat(item.unit_price) : '';
        let taxRate = item ? (item.tax_rate !== null ? item.tax_rate : 0) : 0;
        let amount = item ? parseFloat(item.amount) : '';

        let newRow = `
            <tr>
                <td class="text-center">${rowCount}</td>
                <input type="hidden" name="id_detail[]" value="${id_detail_row}"/>
                <td><input type="text" class="form-control" required name="description[]" value="${desc}" placeholder="Deskripsi item"></td>
                <td><input type="number" class="form-control" required name="qty[]" min="1" value="${qty}"></td>
                <td><input type="number" class="form-control" required name="unit_price[]" min="1" step="0.01" value="${price}"></td>
                <td>
                    <select class="select2-account form-control" required name="account_id[]" style="width:100%;">
                        <option value="">Pilih Account...</option>
                    </select>
                </td>
                <td><input type="text" class="form-control" name="tax_rate[]" value="${taxRate}"></td>
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
                <td><input type="text" class="form-control text-right" name="amount[]" value="${amount}" readonly></td>
                <td><button type="button" class="btn btn-sm btn-danger" onclick="removeRow(this)">×</button></td>
            </tr>`;

        $('#itemTable tbody').append(newRow);

        let $lastRow = $('#itemTable tbody tr:last');

        // --- INIT DETAIL SELECT2 ---
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
                    return { keyword: params.term || '', page: params.page || 1 };
                },
                processResults: function(response, params) {
                    params.page = params.page || 1;
                    return {
                        results: $.map(response.data?.data || [], function(item) {
                            return { id: item.id, text: item.name, account_type: item.account_type || '-' };
                        }),
                        pagination: { more: response.data?.next_page_url !== null }
                    };
                },
                cache: true
            },
            templateResult: function(item) {
                if (!item.id) return item.text;
                return $(`<span>${item.text} <small class="text-muted">(${item.account_type})</small></span>`);
            }
        });

        $lastRow.find('.select2-paket').select2({
            placeholder: "Pilih Paket...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() { return { name_parent_category: 'nama paket' }; },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                        })
                    };
                },
                cache: true
            }
        });

        $lastRow.find('.select2-divisi').select2({
            placeholder: "Pilih Divisi...",
            allowClear: true,
            dropdownParent: $('#modalCreateHotel'),
            ajax: {
                url: "{{ route('tracking-by-parent') }}",
                type: "GET",
                dataType: 'json',
                delay: 250,
                data: function() { return { name_parent_category: 'divisi' }; },
                processResults: function(response) {
                    if (!response.status || !response.data?.lines_category) return { results: [] };
                    return {
                        results: $.map(response.data.lines_category, function(item) {
                            return { id: item.item_uuid_category || item.id, text: item.item_name_category };
                        })
                    };
                },
                cache: true
            }
        });

        // --- SET DETAIL SELECT2 VALUES (EDIT MODE) ---
        if (item) {
            if (item.account_id_coa) {
                ajaxRequest(`{{ route('coaDetail') }}`, 'get', {id: item.account_id_coa}, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        let coa_data = response.data.data;
                        let accName = coa_data.name || ('Akun ID: ' + coa_data.account_type);
                        let accOpt = new Option(accName, item.account_id_coa, true, true);
                        $lastRow.find('.select2-account').append(accOpt).trigger('change');
                    }
                })
                .catch((err) => {
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                });
            }

            if (item.paket_tracking_uuid) {
                 ajaxRequest(`{{ route('tracking-detail') }}`, 'get', {name_parent_category: 'nama paket',id:item.paket_tracking_uuid}, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        let data_paket = response.data.data
                        let pktName = data_paket.item_name_category || ('Paket ID: ' + data_paket.item_uuid_category);
                        let pktOpt = new Option(pktName, item.paket_tracking_uuid, true, true);
                        $lastRow.find('.select2-paket').append(pktOpt).trigger('change');
                    }
                })
                .catch((err) => {
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                });
            }

            if (item.divisi_travel_tracking_uuid) {
                ajaxRequest(`{{ route('tracking-detail') }}`, 'get', {name_parent_category: 'divisi',id:item.divisi_travel_tracking_uuid}, localStorage.getItem("token"))
                .then(response => {
                    if(response.status == 200){
                        let data_divisi = response.data.data
                        let divName =  data_divisi.item_name_category || ('Divisi ID: ' + data_divisi.item_uuid_category);
                        let divOpt = new Option(divName, item.divisi_travel_tracking_uuid, true, true);
                        $lastRow.find('.select2-divisi').append(divOpt).trigger('change');
                    }
                })
                .catch((err) => {
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                });
            }
        }

        // --- CALCULATION LOGIC FOR THIS ROW ---
        const $qty = $lastRow.find('input[name="qty[]"]');
        const $unitPrice = $lastRow.find('input[name="unit_price[]"]');
        const $amount = $lastRow.find('input[name="amount[]"]');

        function calculateAmount() {
            let rowQty = parseFloat($qty.val()) || 0;
            let rowPrice = parseFloat($unitPrice.val()) || 0;
            let total = rowQty * rowPrice;
            $amount.val(total.toFixed(2));
            calculateGrandTotal();
        }

        $qty.on('input keyup', calculateAmount);
        $unitPrice.on('input keyup', calculateAmount);

        if (!item) { calculateAmount(); }
    };

    window.calculateGrandTotal = function() {
        let total_grand = 0;
        $('#itemTable tbody tr').each(function() {
            let amountStr = $(this).find('input[name="amount[]"]').val();
            let amount_row = parseFloat(amountStr) || 0;
            total_grand += amount_row;
        });

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
        $('#grandTotal').text(formatCurrency(total_grand));
    };

});
</script>
@endpush