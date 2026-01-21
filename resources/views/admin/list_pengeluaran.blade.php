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

@endsection

@push('scripts')
<script>
    // 1. Inisialisasi Variabel Global
    let currentPage = 1;
    let limit = 10; // Jumlah data per halaman
    let searchKeyword = '';
    let isLoading = false;

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

        $.ajax({
            url: `{{ route('md_g_pengeluaran') }}`, // Pastikan nama route benar
            method: 'GET',
            data: {
                page: currentPage,
                limit: limit,
                search: searchKeyword
            },
            success: function(response) {
                console.log('data',response)
                if (response.status) {
                    // Render Tabel
                    renderTable(response.data.data);

                    // Update Pagination UI
                    updatePagination(response.data);
                } else {
                    Swal.fire('Error', 'Gagal memuat data format tidak sesuai', 'error');
                }
            },
            error: function(xhr) {
                console.error(xhr);
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Terjadi kesalahan saat mengambil data server.'
                });
            },
            complete: function() {
                isLoading = false;
                $('#loadingIndicator').hide();
                $('#spendingBody').fadeIn(); // Tampilkan tabel kembali
            }
        });
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
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="deleteData('${item.id}')">
                                <i class="fas fa-trash"></i>
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

    // Placeholder fungsi Edit/Delete
    function editData(id){ console.log("Edit", id); }
    function deleteData(id){ console.log("Delete", id); }
</script>
@endpush




