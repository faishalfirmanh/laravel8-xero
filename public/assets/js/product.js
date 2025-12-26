const LIST_URL = 'api/get-data-product';
const CREATE_URL = 'api/save-data-product';
const URL_INVOICE = 'api/getInvoiceByIdPaket/'
const URL_INVOICE_PAGING = 'api/getInvoiceByIdPaketPaging/'
const URL_DETAIL_Item = 'api/get-by-id/'
const baseUrlOrigin = window.location.origin;
let currentPage = 1;
let currentSearch = '';


let currentInvPage = 1;
let currentInvSearch = '';
let currentIdPaket = 0;

function getDataEdit(id) {
    //
    const element_form_ubahHarga = document.getElementById("createContactForm");
    element_form_ubahHarga.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });

    $.ajax({
        url: `api/get-by-id/${id}`,
        type: 'GET',
        dataType: 'json',
        success: function (response) {
            let data_nya = response.Items;
            data_nya.forEach(element => {
                $("#Name").val(element.Name)
                $("#Code").val(element.Code)
                $("#Description").val(element.Description)
                $("#UnitPrice").val(element.SalesDetails.UnitPrice)
                $("#unit_price_save").val(element.SalesDetails.UnitPrice)

            });
        },
        error: function (xhr, status, error) {
            console.log('eeeeee rrorr', error)
            Swal.fire({
                title: 'Erros!',
                text: `load data by id ${error}`,
                icon: 'error',
                confirmButtonText: 'Ok'
            })
            // Tampilkan pesan error

        }
    });
}

function loadDataItem(idPaket){
    if (idPaket) currentIdPaket = idPaket;
    console.log("idpake",idPaket)
    $('#listInvoiceLoader').removeClass('d-none');
    $('#invoiceTable').addClass('d-none');
    $('#invoiceTableBody').empty();

    $.ajax({
        url: `${URL_INVOICE}${currentIdPaket}`,
        type: 'GET',
        dataType: 'json',
        success: function (response) {

            var notifContainer = $('#notif_save_checbox');
            notifContainer.empty();
            $('#listInvoiceLoader').addClass('d-none');
            $('#invoiceTable').removeClass('d-none');

            let price_afer_save = $("#unit_price_save").val();
            let rows = '';
            let counter = 0;
            response.forEach((item, key) => {
                let date = formatDateIndo(item.tanggal);
                let dueDate = item.tanggal_due_date ? formatDateIndo(item.tanggal_due_date) : '-';
                let nominalPaid = formatRupiah(item.amount_paid);
                let statusBadge = getStatusBadge(item.status);
                let finalUrl = `${baseUrlOrigin}/detailInvoiceWeb/${item.parent_invoice_id}`;
                let cek_item_payment = (item.payment && item.payment.length > 0) ? item.payment[0].PaymentID : 'kosong';

                rows += `
                    <tr>
                        <td>${counter++}</td>
                        <td>${item.no_invoice}</td>
                        <td>${item.nama_jamaah}</td>
                        <td>${item.paket_name || '-'}</td>
                        <td>${date}</td>
                        <td>${dueDate}</td>
                        <td class="text-end">${nominalPaid}</td>
                        <td>${formatRupiah(item.total)}</td>
                        <td class="text-center">${statusBadge}</td>
                        <td class="text-center">
                            <a href="${finalUrl}" target="_blank" class="btn btn-primary btn-sm mb-1">Detail</a>
                            <div class="form-check d-flex justify-content-center">
                                <input class="form-check-input invoice-checkbox"
                                    type="checkbox"
                                    value="${key}"
                                    data-no-invoice="${item.parent_invoice_id}_${item.line_item_id}_${item.status}_${item.no_invoice}_${cek_item_payment}"
                                    data-amount="${price_afer_save}">
                            </div>
                        </td>
                    </tr>
                `;
            });
            $('#invoiceTableBody').html(rows);
        },
        error: function (xhr, status, error) {
            $('#listInvoiceLoader').addClass('d-none');
            $('#invoiceTable').removeClass('d-none');
            console.error("Error fetching invoice:", xhr);
            console.log("error",status)
            console.log("errorssss",error)
            $('#invoiceTableBody').html('<tr><td colspan="10" class="text-center text-danger">Gagal mengambil data.</td></tr>');
        }
    });
}


function formatDateIndo(dateString) {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return new Intl.DateTimeFormat('id-ID', {
        day: '2-digit', month: 'short', year: 'numeric'
    }).format(date);
}

// Helper: Format Rupiah
function formatRupiah(amount) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(amount);
}

// Helper: Badge Warna Status
function getStatusBadge(status) {
    let color = 'secondary';
    if (status === 'PAID') color = 'success';
    else if (status === 'AUTHORISED') color = 'primary'; // Biru untuk Authorised (Open)
    else if (status === 'DRAFT') color = 'warning';
    else if (status === 'VOIDED') color = 'danger';

    return `<span class="badge bg-${color}">${status}</span>`;
}

function formatRupiah(angka) {
    const number = Number(angka) || 0;
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0, // Ubah jadi 2 jika ingin ada sen (,00)
        maximumFractionDigits: 0
    }).format(number);
}
$(document).ready(function () {

    //invoice by id paket
    $('#btnSearchInvoice').click(function() {
        currentInvSearch = $('#searchInvoiceInput').val(); // Ambil value dari input
        currentInvPage = 1; // Reset ke halaman 1 setiap search baru
        fetchDataInvoice(); // Panggil ulang dengan ID Paket yang sedang aktif
    });
    $('#nextInvPage').click(function() {
        currentInvPage++;
        fetchDataInvoice();
    });
    $('#prevInvPage').click(function() {
        if (currentInvPage > 1) {
            currentInvPage--;
            fetchDataInvoice();
        }
    });
    $('#searchInvoiceInput').keypress(function(e) {
        if(e.which == 13) { // 13 adalah kode tombol Enter
            $('#btnSearchInvoice').click();
        }
    });
    //invoice by id paket

    $('#searchBtn').click(function () {
        currentSearch = $('#searchInput').val();
        currentPage = 1; // Reset ke halaman 1 saat search baru
        fetchContacts(1);
    });


    $('#searchInput').keypress(function (e) {
        if (e.which == 13) { // Kode tombol Enter
            performSearch();
        }
    });

    $('#nextPageBtn').click(function () {
        currentPage++;
        fetchContacts(1);
    });

    function performSearch() {
        currentSearch = $('#searchInput').val();
        currentPage = 1;
        fetchContacts();
    }

    $('#prevPageBtn').click(function () {
        if (currentPage > 1) {
            currentPage--;
            fetchContacts(1);
        }
    });

    $('#checkAll').on('change', function () {
        let isChecked = $(this).prop('checked');
        $('.invoice-checkbox').prop('checked', isChecked);
    });

    $(document).on('change', '.invoice-checkbox', function () {
        let totalCheckbox = $('.invoice-checkbox').length;
        let totalChecked = $('.invoice-checkbox:checked').length;
        $('#checkAll').prop('checked', totalCheckbox === totalChecked);
    });


    function fetchContacts(btnAtasCek = 0) {
        $('#listLoader').removeClass('d-none');
        $('#contactTable').addClass('d-none');
        $('#contactTableBody').empty();
        if(btnAtasCek < 1){
            $("#listInvoiceLoader").removeClass('d-none');
        }

        // Disable buttons saat loading
        $('#prevPageBtn, #nextPageBtn').prop('disabled', true);

        $.ajax({
            url: LIST_URL, // Pastikan variabel ini mengarah ke route controller Anda
            type: 'GET',
            dataType: 'json',
            data: {
                page: currentPage,
                search: currentSearch
            },
            success: function (response) {
                $('#listLoader').addClass('d-none');
                $('#contactTable').removeClass('d-none');

                // Update Page Display dari response server agar sinkron
                $('#currentPageDisplay').text(response.current_page);

                // PERUBAHAN 1: Ambil dari 'data', bukan 'Items'
                const contacts = response.data;//Items;


                if (contacts && contacts.length > 0) {
                    // PERUBAHAN 2: Counter dikali 10 (sesuai limit controller baru)
                    let counter = ((currentPage - 1) * 10) + 1;

                    contacts.forEach(contact => {
                        //console.log("asss",contact)
                        const price = contact.SalesDetails && contact.SalesDetails.UnitPrice
                            ? formatRupiah(contact.SalesDetails.UnitPrice)
                            : '0';

                        const description = contact.Description
                            ? contact.Description.substring(0, 50) + (contact.Description.length > 50 ? '...' : '')
                            : '-';

                        const row = `
                        <tr>
                            <td>${counter++}</td>
                            <td>${contact.Name || '-'}</td>
                            <td>${contact.Code || '-'}</td>
                            <td>Rp. ${price}</td>
                            <td>${description}</td>
                            <td>
                                <button type="button" onclick="getDataEdit('${contact.ItemID}')" class="btn btn-primary btn-sm">Edit</button>
                                <button type="button" onclick="loadDataItem('${contact.Code}')" class="btn btn-success btn-sm">Load Invoice</button>
                            </td>
                        </tr>
                    `;
                        $('#contactTableBody').append(row);
                    });

                    // PERUBAHAN 3: Logic Tombol Next menggunakan flag 'has_more' dari Controller
                    if (response.has_more === true) {
                        $('#nextPageBtn').prop('disabled', false);
                    } else {
                        $('#nextPageBtn').prop('disabled', true);
                    }

                } else {
                    $('#contactTableBody').append('<tr><td colspan="6" class="text-center">Tidak ada data ditemukan.</td></tr>');
                    $('#nextPageBtn').prop('disabled', true);
                }

                // Logic Tombol Prev (Tetap sama)
                if (currentPage > 1) {
                    $('#prevPageBtn').prop('disabled', false);
                } else {
                    $('#prevPageBtn').prop('disabled', true);
                }
            },
            error: function (xhr, status, error) {
                Swal.fire({
                    title: 'Erros!',
                    text: `load data product & service ${error}`,
                    icon: 'error',
                    confirmButtonText: 'Ok'
                })
                $('#listLoader').addClass('d-none');
                $('#contactTable').removeClass('d-none');
                console.error("Error fetching contacts:", error);
                $('#contactTableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal mengambil data dari server.</td></tr>');
                $('#listInvoiceLoader').addClass('d-none');
            }
        });
    }

    function submitSaveInvoiceCheck(){
         $('#fullPageLoader').removeClass('d-none');
        let harga_update = $("#UnitPrice").val()
        let id_account_item = $("#account_id_item").val();
        $('#invoice_update_checkbox').removeClass('d-none');
        let selectedItems = [];
        $('.invoice-checkbox:checked').each(function () {
            let checkbox = $(this);
            // console.log('checkbox',checkbox)
            let data = {
                key: checkbox.val(), // Mengambil value="${key}"
                combinedInfo: checkbox.data('no-invoice'),
                amount: checkbox.data('amount')
            };
            let parts = String(data.combinedInfo).split('_');
            data.parentId = parts[0];
            data.lineItemId = parts[1];
            data.status = parts[2];
            data.no_invoice = parts[3];
            data.no_payment = parts[4];

            selectedItems.push(data);
        });

        if (selectedItems.length === 0) {
            alert('Harap pilih minimal satu invoice!');
            return;
        }

        $.ajax({
            url: 'api/submitUpdateinvoices',
            type: 'POST',
            dataType: 'json',
            data: JSON.stringify({
                price_update: harga_update,
                items: selectedItems,
                account_id_item: id_account_item
            }),
            success: function (response) {
                var notifContainer = $('#notif_save_checbox');
                notifContainer.empty();
                // if(response.errors < 1){
                var listItems = '';
                $.each(response, function (index, item) {
                    listItems += `<li>Invoice <strong>${item.no_invoice}</strong> : ${item.status}</li>`;
                });
                var alertHtml = `
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <strong>Update Berhasil!</strong>
                                <ul class="mb-0 pl-3 mt-1">
                                    ${listItems}
                                </ul>
                                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                        `;
                notifContainer.html(alertHtml);
                // }else{
                //     var alertHtml = ``
                //     var listItems = '';
                //     $.each(response, function(index, item) {
                //         console.log("error get inv",item)
                //         listItems += `<li>Invoice <strong>${item.no_invoice}</strong> :</li>`;
                //     });
                //      var alertHtml = `
                //         <div class="alert alert-danger alert-dismissible fade show" role="alert">
                //             <strong>Update Gagal!</strong>
                //             <ul class="mb-0 pl-3 mt-1">
                //                 ${listItems}
                //             </ul>
                //             <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                //                 <span aria-hidden="true">&times;</span>
                //             </button>
                //         </div>
                //     `;
                //      notifContainer.html(alertHtml);
                //      console.log("erros",response)
                // }

                // setTimeout(function() {
                //     notifContainer.fadeOut('slow', function(){
                //         $(this).empty().show(); // Reset container
                //     });
                // }, 5000);
                $('#invoice_update_checkbox').addClass('d-none');
            },
            error: function (xhr, err) {

            },
            complete: function () {
                $('#fullPageLoader').addClass('d-none');
            }
        })
    }

    $("#btnSaveInvoicev2").on('click',function(e){
        submitSaveInvoiceCheck()
    });

    $("#btnSaveInvoice").on('click', function (e) {
         submitSaveInvoiceCheck()
    })


    function loadViewNoPaging(response){
        if (!response.length === 0) {
            $('#invoiceTableBody').html('<tr><td colspan="10" class="text-center">Data tidak ditemukan</td></tr>');
            $('#totalInvData').text(response.length);
            $('#totalInvPage').text(1);
            return;
        }

        let price_afer_save = $("#unit_price_save").val();
        let rows = '';
        let counter = 0;
        response.forEach((item, key) => {
            let date = formatDateIndo(item.tanggal);
            let dueDate = item.tanggal_due_date ? formatDateIndo(item.tanggal_due_date) : '-';
            let nominalPaid = formatRupiah(item.amount_paid);
            let statusBadge = getStatusBadge(item.status);
            let finalUrl = `${baseUrlOrigin}/detailInvoiceWeb/${item.parent_invoice_id}`;
            let cek_item_payment = (item.payment && item.payment.length > 0) ? item.payment[0].PaymentID : 'kosong';

            rows += `
                <tr>
                    <td>${counter++}</td>
                    <td>${item.no_invoice}</td>
                    <td>${item.nama_jamaah}</td>
                    <td>${item.paket_name || '-'}</td>
                    <td>${date}</td>
                    <td>${dueDate}</td>
                    <td class="text-end">${nominalPaid}</td>
                    <td>${formatRupiah(item.total)}</td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-center">
                        <a href="${finalUrl}" target="_blank" class="btn btn-primary btn-sm mb-1">Detail</a>
                        <div class="form-check d-flex justify-content-center">
                            <input class="form-check-input invoice-checkbox"
                                type="checkbox"
                                value="${key}"
                                data-no-invoice="${item.parent_invoice_id}_${item.line_item_id}_${item.status}_${item.no_invoice}_${cek_item_payment}"
                                data-amount="${price_afer_save}">
                        </div>
                    </td>
                </tr>
            `;
        });
        $('#invoiceTableBody').html(rows);
    }

    function loadViewPaging(response){
         // Cek data kosong
        if (!response.data || response.data.length === 0) {
            $('#invoiceTableBody').html('<tr><td colspan="10" class="text-center">Data tidak ditemukan</td></tr>');
            $('#totalInvData').text(0);
            $('#totalInvPage').text(1);
            return;
        }

        // Update Info Pagination
        const meta = response.meta;
        $('#currentInvPage').text(meta.current_page);
        $('#totalInvPage').text(meta.total_pages);
        $('#totalInvData').text(meta.total_data);

        // Logic Tombol Next/Prev
        $('#prevInvPage').prop('disabled', meta.current_page <= 1);
        $('#nextInvPage').prop('disabled', meta.current_page >= meta.total_pages);

        let rows = '';
        // Hitung nomor urut: (page-1) * limit + 1
        let counter = ((meta.current_page - 1) * meta.limit) + 1;
        let price_afer_save = $("#unit_price_save").val();

        response.data.forEach((item, key) => {
            let date = formatDateIndo(item.tanggal);
            let dueDate = item.tanggal_due_date ? formatDateIndo(item.tanggal_due_date) : '-';
            let nominalPaid = formatRupiah(item.amount_paid);
            let statusBadge = getStatusBadge(item.status);
            let finalUrl = `${baseUrlOrigin}/detailInvoiceWeb/${item.parent_invoice_id}`;
            let cek_item_payment = (item.payment && item.payment.length > 0) ? item.payment[0].PaymentID : 'kosong';

            rows += `
                <tr>
                    <td>${counter++}</td>
                    <td>${item.no_invoice}</td>
                    <td>${item.nama_jamaah}</td>
                    <td>${item.paket_name || '-'}</td>
                    <td>${date}</td>
                    <td>${dueDate}</td>
                    <td class="text-end">${nominalPaid}</td>
                    <td>${formatRupiah(item.total)}</td>
                    <td class="text-center">${statusBadge}</td>
                    <td class="text-center">
                        <a href="${finalUrl}" target="_blank" class="btn btn-primary btn-sm mb-1">Detail</a>
                        <div class="form-check d-flex justify-content-center">
                            <input class="form-check-input invoice-checkbox"
                                type="checkbox"
                                value="${key}"
                                data-no-invoice="${item.parent_invoice_id}_${item.line_item_id}_${item.status}_${item.no_invoice}_${cek_item_payment}"
                                data-amount="${price_afer_save}">
                        </div>
                    </td>
                </tr>
            `;
        });
    }

   function fetchDataInvoice(idPaket) {
        // Simpan ID Paket ke variable global agar bisa dipakai saat klik Next/Prev
        if (idPaket) currentIdPaket = idPaket;

        $('#listInvoiceLoader').removeClass('d-none');
        $('#invoiceTable').addClass('d-none');
        $('#invoiceTableBody').empty();

        // Disable pagination buttons while loading
       // $('#prevInvPage, #nextInvPage').prop('disabled', true);

        $.ajax({
            url: `${URL_INVOICE}${currentIdPaket}`,//URL_INVOICE_PAGING
            type: 'GET',
            dataType: 'json',
            // data: {
            //     page: currentInvPage,
            //     limit: 10,
            //     search: currentInvSearch
            // },
            success: function (response) {

                var notifContainer = $('#notif_save_checbox');
                notifContainer.empty();
                $('#listInvoiceLoader').addClass('d-none');
                $('#invoiceTable').removeClass('d-none');
                loadViewNoPaging(response)
            },
            error: function (xhr) {
                $('#listInvoiceLoader').addClass('d-none');
                $('#invoiceTable').removeClass('d-none');
                console.error("Error fetching invoice:", xhr);
                $('#invoiceTableBody').html('<tr><td colspan="10" class="text-center text-danger">Gagal mengambil data.</td></tr>');
            }
        });
   }


    // Panggil fungsi saat halaman pertama kali dimuat
    fetchContacts(1);

    function fetchDataAccountCodeByItem(idItem) {
        $.ajax({
            url: `${URL_DETAIL_Item}${idItem}`,
            type: 'GET',
            contentType: 'application/json',
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
            },
            success: function (response) {
                let item_list = response.Items//[0].SalesDetails.AccountCode
                item_list.forEach((x) => {
                    if (x.SalesDetails.AccountCode) {
                        $("#account_id_item").val(x.SalesDetails.AccountCode)
                    } else {
                        $("#account_id_item").val(0)
                    }
                })
            },
            error: function (xhr, status, error) {
                console.log('eeeeee rrorr', error)
                Swal.fire({
                    title: 'Erros!',
                    text: `load data by id ${error}`,
                    icon: 'error',
                    confirmButtonText: 'Ok'
                })
                // Tampilkan pesan error

            }
        });
    }
    // Event listener untuk tombol refresh
    $('#refreshBtn').on('click', fetchContacts);

    // Event listener untuk pengiriman form
    $('#createContactForm').on('submit', function (e) {
        e.preventDefault();

        const $submitBtn = $('#submitBtn');
        const $submitSpinner = $('#submitSpinner');
        const $formMessage = $('#formMessage');

        // Ambil data form
        const formData = {
            Code: $("#Code").val(),
            SalesDetails: {
                UnitPrice: $("#UnitPrice").val()
            }
        };

        // Format data sesuai permintaan JSON (nested structure)
        const payload = {
            "Items": [formData]
        };

        // Tampilkan loading, nonaktifkan tombol
        $submitBtn.prop('disabled', true);
        $submitSpinner.removeClass('d-none');
        $formMessage.addClass('d-none').removeClass('alert-success alert-danger');





        $.ajax({
            url: CREATE_URL,
            type: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
            headers: {
                "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content")
            },
            success: function (response) {
                console.log("update harga save", response.Items[0])
                $formMessage.html('<strong>Sukses!</strong> Proudct & Service berhasil disimpan.').addClass('alert-success').removeClass('d-none');
                //    $('#createContactForm')[0].reset(); // Kosongkan form
                fetchContacts(); // Muat ulang daftar kontak
                fetchDataInvoice(payload.Items[0].Code)
                fetchDataAccountCodeByItem(response.Items[0].Code)
                $("#name_paket_saved").html(response.Items[0].Name)
            },
            error: function (xhr) {
                console.log('error', xhr)
                // Tampilkan pesan error
                let errorMessage = 'Gagal menyimpan product. Silakan coba lagi.';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                $formMessage.html(`<strong>Error!</strong> ${errorMessage}`).addClass('alert-danger').removeClass('d-none');
                console.error("Error creating contact:", xhr.responseText);
            },
            complete: function () {
                // Sembunyikan loading, aktifkan tombol
                $submitBtn.prop('disabled', false);
                $submitSpinner.addClass('d-none');
            }
        });
    });
});
