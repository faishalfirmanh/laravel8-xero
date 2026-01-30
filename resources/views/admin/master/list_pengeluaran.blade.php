@extends('layouts.app')

@section('content')


<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0 fw-bold">Daftar Pengeluaran Paket</h5>
    <div class="w-25">
        <input type="text" id="searchInput" class="form-control" placeholder="Cari nama paket..." onkeyup="handleSearch(event)">
    </div>
</div>


<div class="card shadow mb-5">
    <div id="loadingIndicator" class="text-center my-4" style="display:none;">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="mt-2">Loading data...</div>
    </div>
    <div class="table-responsive">
        <table class="table table-striped table-bordered mt-0" id="spending_web_list">
            <thead class="table-dark">
                <tr>
                    <th style="">No </th>
                    <th style="">Name </th>
                    <th style="">Status</th>
                    <th style="">Action</th>
                </tr>
            </thead>
            <tbody id="spendingBody">
            </tbody>
        </table>
    </div>
</div>

<div id="fullScreenLoader" class="position-fixed w-100 h-100 flex-column justify-content-center align-items-center"
     style="display: none; top: 0; left: 0; background-color: rgba(0,0,0,0.7); z-index: 9999;">

    <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
        <span class="sr-only">Loading...</span>
    </div>
    <h4 class="text-white mt-3 font-weight-bold">Sedang Sinkronisasi Data...</h4>
    <p class="text-white-50">Mohon tunggu sebentar</p>
</div>

<div class="modal fade" id="modalEditMasterPengeluaran" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <form id="formMasterPengeluaran">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="id_pengeluaran_modal" id="id_pengeluaran_modal">
                    <div class="form-group"> <label for="pengeluaran_name">Nama Pengeluaran</label>
                        <input type="text" class="form-control" id="pengeluaran_name" name="pengeluaran_name">
                    </div>

                    <div class="form-group">
                        <label for="is_active_pengeluaran">Is Active</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active_check" id="radio1" value="1" checked>
                            <label class="form-check-label" for="radio1">
                                Aktif
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="is_active_check" id="radio2" value="0">
                            <label class="form-check-label" for="radio2">
                               Tidak Aktif
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary" id="btnSave">Simpan</button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // 1. Inisialisasi Variabel Global
    let currentPage = 1;
    let limit = 10; // Jumlah data per halaman
    let searchKeyword = '';
    let isLoading = false;

    var table_m_pengeluaran = '';
    // Load data saat halaman pertama kali dibuka
    $(document).ready(function() {
        getData();
    });


    // 2. Fungsi Utama Fetch Data
    function getData() {
        if (isLoading) return; // Cegah double request

        isLoading = true;
        $('#loadingIndicator').show();
        $('#spendingBody').hide(); // Sembunyikan tabel lama saat loading

          ajaxRequest( `{{ route('md_g_pengeluaran') }}`,'GET',
            {
                page: currentPage,
                limit: limit,
                search: searchKeyword
            }, localStorage.getItem("token"))
                .then(response =>{
                    console.log('token data',localStorage.getItem("token"))
                    if(response.status){
                        renderTable(response.data.data.data);
                        updatePagination(response.data.data);
                    }else{
                        console.log("not sucess",response)
                    }
                    isLoading = false;
                    $('#loadingIndicator').hide();
                    $('#spendingBody').fadeIn(); //
                })
                .catch((err)=>{
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                    console.error(xhr);
                    isLoading = false;
                    $('#loadingIndicator').hide();
                    $('#spendingBody').fadeIn(); //
                })
    }

    // 3. Fungsi Render HTML Tabel
    function renderTable(data) {
        let html = '';
        let startNumber = (currentPage - 1) * limit + 1;

        if (data.length > 0) {
            data.forEach((item, index) => {
                // Sesuaikan 'item.nama_paket' dengan nama kolom database Anda
                html += `
                    <tr>
                        <td>${startNumber++}</td>
                        <td class="fw-bold">${item.nama_pengeluaran || '-'}</td>
                        <td>${item.is_active ? '<span class="badge badge-primary">active</span>' : '<span class="badge badge-danger">not active</span>'}</td>
                        <td class="text-center">
                            <button class="btn btn-sm btn-info text-white" onclick="editData('${item.id}')">
                                 <i class="ti ti-pencil me-1"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        } else {
            html = `<tr><td colspan="5" class="text-center text-muted py-4">Tidak ada data ditemukan</td></tr>`;
        }

        $('#spendingBody').html(html);
    }

    // 4. Fungsi Update Pagination
    function updatePagination(pagination) {
        // Update Info Text
        $('#pageInfo').text(`Halaman ${pagination.current_page} dari ${pagination.total_page} (Total: ${pagination.total_data} data)`);

        // Update Tombol Prev
        if (pagination.prev_url) {
            $('#btnPrev').prop('disabled', false);
        } else {
            $('#btnPrev').prop('disabled', true);
        }

        // Update Tombol Next
        if (pagination.next_url) {
            $('#btnNext').prop('disabled', false);
        } else {
            $('#btnNext').prop('disabled', true);
        }
    }

    // 5. Helper: Navigasi Halaman
    function changePage(direction) {
        if (direction === 'next') {
            currentPage++;
        } else if (direction === 'prev' && currentPage > 1) {
            currentPage--;
        }
        getData();
    }

    // 6. Helper: Search (dengan Debounce sederhana)
    let timeout = null;
    function handleSearch(e) {
        clearTimeout(timeout);
        timeout = setTimeout(function() {
            searchKeyword = e.target.value;
            currentPage = 1; // Reset ke halaman 1 setiap kali search berubah
            getData();
        }, 500); // Tunggu 500ms setelah user berhenti mengetik
    }

    $("#formMasterPengeluaran").on("submit",function(e){
         e.preventDefault();
        let formData = $(this).serialize();
        let params = new URLSearchParams(formData);
        let idInput = params.get('id_pengeluaran_modal');
        let json_data = {
            id: idInput,
            nama_pengeluaran: params.get('pengeluaran_name'),
            is_active: params.get('is_active_check')
        };
        ajaxRequest( `{{ route('md_store_pengeluaran') }}`,'POST',
            json_data, localStorage.getItem("token"))
                .then(response =>{
                    $('#modalEditMasterPengeluaran').modal('hide');

                    if(response.status){
                        Swal.fire({
                            icon: 'success',
                            title: 'Simpan Berhasil!',
                            html: `
                                <div style="text-align: left; font-size: 14px;">
                                    <p class="mb-1"> berhasil simpan hotel </p>
                                    <hr>
                                </div>
                            `,
                            confirmButtonText: 'Sukses'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.reload();
                            }
                        });
                    }
                   // window.location.reload();
                })
                .catch((err)=>{
                     $('#modalEditMasterPengeluaran').modal('hide');
                     Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                })
    })

    // Placeholder fungsi Edit/Delete
    function editData(id){
          $('#modalEditMasterPengeluaran').modal('show');
           ajaxRequest( `{{ route('md_gbyid_pengeluaran') }}`,'GET',
            {
                id: id,
            }, localStorage.getItem("token"))
                .then(response =>{
                   //console.log(response.data.data)
                    if(response.status){
                        $("#id_pengeluaran_modal").val(response.data.data.id)
                        $("#pengeluaran_name").val(response.data.data.nama_pengeluaran)
                        if(response.data.data.is_active == 1){
                            $("#radio1").prop("checked", true);
                            $("#radio2").prop("checked", false);
                        } else{
                            $("#radio1").prop("checked", false);
                            $("#radio2").prop("checked", true);
                        }
                    }
                })
                .catch((err)=>{
                    Swal.fire('Gagal!', err.message || 'Terjadi kesalahan.', 'error');
                    console.error(xhr);
                })
    }
    function deleteData(id){ console.log("Delete", id); }
</script>
@endpush




