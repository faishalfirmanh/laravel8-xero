<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Kontak (Lumen AJAX)</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            margin-top: 30px;
        }
        .card-header {
            background-color: #0d6efd;
            color: white;
            font-weight: bold;
        }
        .table th {
            background-color: #e9ecef;
        }
        /* Custom loader style */
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #0d6efd;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

    <div class="container">
        <h1 class="text-center mb-4 text-primary">Aplikasi Manajemen Kontak</h1>

        <!-- CONTACT CREATION FORM -->
        <div class="card shadow mb-5">
            <div class="card-header">
                Buat Kontak Baru
            </div>
            <div class="card-body">
                <form id="createContactForm">
                      @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="name" class="form-label">Nama Perusahaan/Kontak <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="Name" required>
                        </div>
                        <div class="col-md-4">
                            <label for="firstName" class="form-label">Nama Depan (FirstName)</label>
                            <input type="text" class="form-control" id="firstName" name="FirstName">
                        </div>
                        <div class="col-md-4">
                            <label for="lastName" class="form-label">Nama Belakang (LastName)</label>
                            <input type="text" class="form-control" id="lastName" name="LastName">
                        </div>
                        <div class="col-12">
                            <label for="emailAddress" class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" id="emailAddress" name="EmailAddress" required>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary w-50" id="submitBtn">
                                Simpan Kontak <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="submitSpinner"></span>
                            </button>
                        </div>
                    </div>
                </form>
                <div id="formMessage" class="mt-3 alert d-none"></div>
            </div>
        </div>

        <!-- CONTACT LIST TABLE -->
        <div class="card shadow">
            <div class="card-header d-flex justify-content-between align-items-center">
                Daftar Kontak
                <button class="btn btn-light btn-sm" id="refreshBtn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                        <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .917-.184A6 6 0 1 1 8 2z"/>
                        <path d="M8 4.464a.5.5 0 0 1 .5.5v3.427a.5.5 0 0 1-.5.5zM8 10a.5.5 0 0 1-.5-.5V6.073a.5.5 0 0 1 1 0v3.427a.5.5 0 0 1-.5.5z"/>
                    </svg>
                    Refresh Data
                </button>
            </div>
            <div class="card-body">
                <div id="contactListContainer">
                    <div class="loader" id="listLoader"></div>
                </div>

                <table class="table table-striped table-bordered mt-3 d-none" id="contactTable">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Nama Kontak</th>
                            <th>Email</th>
                            <th>No. Telepon (Default)</th>
                            <th>Status</th>
                            <th>Mata Uang</th>
                        </tr>
                    </thead>
                    <tbody id="contactTableBody">
                        <!-- Data akan diisi oleh JQuery/AJAX -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JQuery and AJAX Logic -->
    <script>
        $(document).ready(function() {
            // URL endpoint sesuai dengan Lumen route yang telah diperbaiki
            const LIST_URL = 'api/get-data';
            const CREATE_URL = 'api/create-data';
            const BEARER_TOKEN = 'eyJhbGciOiJSUzI1NiIsImtpZCI6IjFDQUY4RTY2NzcyRDZEQzAyOEQ2NzI2RkQwMjYxNTgxNTcwRUZDMTkiLCJ0eXAiOiJKV1QiLCJ4NXQiOiJISy1PWm5jdGJjQW8xbkp2MENZVmdWY09fQmsifQ.eyJuYmYiOjE3NjM5NjE1NTIsImV4cCI6MTc2Mzk2MzM1MiwiaXNzIjoiaHR0cHM6Ly9pZGVudGl0eS54ZXJvLmNvbSIsImF1ZCI6Imh0dHBzOi8vaWRlbnRpdHkueGVyby5jb20vcmVzb3VyY2VzIiwiY2xpZW50X2lkIjoiMzZGMDg3MUQyMjMzNERERTg4RDIzRUZFNDBBQjU2MjgiLCJzdWIiOiI4YzY4ZGYyYWMzOGU1ZjU3YjBkN2M0MzgwOGYzZTI1OSIsImF1dGhfdGltZSI6MTc2Mzk2MTUxNywieGVyb191c2VyaWQiOiJkM2RjMjc4YS0wZWUyLTQxMzEtOTA2Yy1iOWUyNjkyZWE4ODQiLCJnbG9iYWxfc2Vzc2lvbl9pZCI6IjU2MDE2ODY1N2M1NTRmNWJiYjhkYTQzNDliZTAxZGUxIiwic2lkIjoiNTYwMTY4NjU3YzU1NGY1YmJiOGRhNDM0OWJlMDFkZTEiLCJhdXRoZW50aWNhdGlvbl9ldmVudF9pZCI6IjlmZDc4NzA1LWVhOTQtNGI2NS1iMjZiLTE4MTFhMWFmYzEwYiIsImp0aSI6IjU0MkJENzlFNzQxRTk3MDkxM0IyMzIyNkU2MkY4MjM3Iiwic2NvcGUiOlsiYWNjb3VudGluZy5jb250YWN0cyIsImFjY291bnRpbmcuY29udGFjdHMucmVhZCIsImFjY291bnRpbmcuc2V0dGluZ3MiLCJhY2NvdW50aW5nLnRyYW5zYWN0aW9ucyIsIm9mZmxpbmVfYWNjZXNzIl0sImFtciI6WyJwd2QiXX0.txZzH2P7a_eSC91ULS_thvbu9hrkCkksFIDA1J3Lpx0N8UB9y62lc-Cb0hkjqJmbSYdKUDVcB0SE-cu3z-5ESet1Rt9b1CHpnTEojieysC8X0wQwZI-y7iMyIA3UThdTLoda9QLI9CAja5llNkMRSpXd3BoCZ2Zwbtpgv6Wf4IGR11K4d2DKKbmOJIB0Scst0uOPipTnIEGjU_BgDNyILvjo5Y6NVe6i4VY_0VYrpoVVChIs5lVlclymYNphkqui6jQcGJ6qdwQJSr9eeC9Gcj33LkjBnRInmVRH_beBKq5HRc4cUy0Rx1TgBcLpAQBuXkiObO_1ffYv8CNzD8TcpA';

            const XERO_TENANT_ID = '90a3a97b-3d70-41d3-aa77-586bb1524beb';

            /**
             * Fungsi untuk mengambil dan menampilkan daftar kontak.
             */
            function fetchContacts() {
                $('#listLoader').removeClass('d-none');
                $('#contactTable').addClass('d-none');
                $('#contactTableBody').empty();

                $.ajax({
                    url: LIST_URL,
                    type: 'GET',
                    dataType: 'json',
                    // headers: {
                    //     "Authorization": "Bearer " +  localStorage.getItem('api_token'),
                    //     // "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr('content')
                    // },
                    // Gunakan mode success/error untuk simulasi,
                    // karena ini hanya frontend, kita akan gunakan data dummy response
                    success: function(response) {
                        console.log('ssss',response)
                        $('#listLoader').addClass('d-none');
                        $('#contactTable').removeClass('d-none');

                        // Gunakan data dummy response untuk simulasi
                        // Di aplikasi nyata, Anda akan menggunakan 'response' dari server
                        const dummyResponse = {
                            "Id": "89023abf-6d8f-4df9-8543-246ddbc62566",
                            "Status": "OK",
                            "ProviderName": "testing-nam-1",
                            "DateTimeUTC": "/Date(1763955879165)/",
                            "Contacts": [
                                { "ContactID": "b8dbadc9-b696-42a4-87f4-2fc7708f39b8", "AccountNumber": "9818911", "ContactStatus": "ACTIVE", "Name": "customer 1", "EmailAddress": "bokernew@email.com", "Addresses": [ { "AddressType": "POBOX" }, { "AddressType": "STREET" } ], "Phones": [ { "PhoneType": "DDI" }, { "PhoneType": "DEFAULT", "PhoneNumber": "81556680173", "PhoneCountryCode": "62" }, { "PhoneType": "FAX" }, { "PhoneType": "MOBILE" } ], "DefaultCurrency": "IDR" },
                                { "ContactID": "fe882b73-890c-407a-be0a-c1721c6b395d", "ContactStatus": "ACTIVE", "Name": "diah", "EmailAddress": "", "Phones": [ { "PhoneType": "DDI" }, { "PhoneType": "DEFAULT" }, { "PhoneType": "FAX" }, { "PhoneType": "MOBILE" } ], "DefaultCurrency": "IDR" },
                                { "ContactID": "21bd2a4a-bf40-421f-b1fe-61e14f00de3a", "ContactStatus": "ACTIVE", "Name": "sarah editedd", "EmailAddress": "sarah.smith@gmail.com", "Phones": [ { "PhoneType": "DDI" }, { "PhoneType": "DEFAULT" }, { "PhoneType": "FAX" }, { "PhoneType": "MOBILE" } ], "DefaultCurrency": "" },
                                { "ContactID": "b3ae66e8-7174-4692-9e3a-fd6f567e6e1b", "ContactStatus": "ACTIVE", "Name": "dian sasttroo", "EmailAddress": "diansastro@gmaiil.com", "Phones": [ { "PhoneType": "DDI" }, { "PhoneType": "DEFAULT", "PhoneNumber": "182", "PhoneAreaCode": "1", "PhoneCountryCode": "+6281" }, { "PhoneType": "FAX" }, { "PhoneType": "MOBILE" } ], "DefaultCurrency": "IDR" }
                            ]
                        };

                        const contacts = response.Contacts || dummyResponse.Contacts;

                        if (contacts && contacts.length > 0) {
                            let counter = 1;
                            contacts.forEach(contact => {
                                // Cari nomor telepon DEFAULT
                                const defaultPhone = contact.Phones ? contact.Phones.find(p => p.PhoneType === 'DEFAULT') : null;
                                const phoneNumber = defaultPhone && defaultPhone.PhoneNumber ? `${defaultPhone.PhoneNumber}` : '-';

                                // Tentukan badge status
                                const statusClass = contact.ContactStatus === 'ACTIVE' ? 'bg-success' : 'bg-secondary';
                                const statusBadge = `<span class="badge ${statusClass}">${contact.ContactStatus}</span>`;

                                // Buat baris tabel
                                const row = `
                                    <tr>
                                        <td>${counter++}</td>
                                        <td>${contact.Name || '-'}</td>
                                        <td>${contact.EmailAddress || '-'}</td>
                                        <td>${phoneNumber}</td>
                                        <td>${statusBadge}</td>
                                        <td>${contact.DefaultCurrency || 'N/A'}</td>
                                    </tr>
                                `;
                                $('#contactTableBody').append(row);
                            });
                        } else {
                            $('#contactTableBody').append('<tr><td colspan="6" class="text-center">Tidak ada data kontak yang ditemukan.</td></tr>');
                        }
                    },
                    error: function(xhr, status, error) {
                        $('#listLoader').addClass('d-none');
                        $('#contactTable').removeClass('d-none');
                        console.error("Error fetching contacts:", error,status, xhr);
                        $('#contactTableBody').html('<tr><td colspan="6" class="text-center text-danger">Gagal mengambil data kontak dari server.</td></tr>');
                    }
                });
            }

            // Panggil fungsi saat halaman pertama kali dimuat
            fetchContacts();

            // Event listener untuk tombol refresh
            $('#refreshBtn').on('click', fetchContacts);

            // Event listener untuk pengiriman form
            $('#createContactForm').on('submit', function(e) {
                e.preventDefault();

                const $submitBtn = $('#submitBtn');
                const $submitSpinner = $('#submitSpinner');
                const $formMessage = $('#formMessage');

                // Ambil data form
                const formData = {
                    Name: $('#name').val(),
                    FirstName: $('#firstName').val(),
                    LastName: $('#lastName').val(),
                    EmailAddress: $('#emailAddress').val()
                };

                // Format data sesuai permintaan JSON (nested structure)
                const payload = {
                    "Contacts": [formData]
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
                    success: function(response) {
                        console.log('payload',JSON.stringify(payload))
                        console.log('sukses',response)
                        // Simulasi response sukses
                        $formMessage.html('<strong>Sukses!</strong> Kontak berhasil disimpan.').addClass('alert-success').removeClass('d-none');
                        $('#createContactForm')[0].reset(); // Kosongkan form
                        fetchContacts(); // Muat ulang daftar kontak
                    },
                    error: function(xhr) {
                        console.log('error',xhr)
                        // Tampilkan pesan error
                        let errorMessage = 'Gagal menyimpan kontak. Silakan coba lagi.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        $formMessage.html(`<strong>Error!</strong> ${errorMessage}`).addClass('alert-danger').removeClass('d-none');
                        console.error("Error creating contact:", xhr.responseText);
                    },
                    complete: function() {
                        // Sembunyikan loading, aktifkan tombol
                        $submitBtn.prop('disabled', false);
                        $submitSpinner.addClass('d-none');
                    }
                });
            });
        });
    </script>

</body>
</html>
