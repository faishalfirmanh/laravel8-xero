<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product & Services</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- JQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.css" rel="stylesheet">00
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
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
        .form-check-input {
            /* Mengubah border menjadi hitam solid */
            border: 2px solid black !important;
        }
        /* Opsional: Agar saat dicentang bordernya tetap hitam (tidak berubah jadi biru) */
        .form-check-input:checked {
            border-color: black !important;
            background-color: black !important; /* Ubah warna isi saat dicentang (opsional) */
        }
    </style>
</head>
<body>

    <div id="fullPageLoader" class="d-none position-fixed top-0 start-0 w-100 h-100 d-flex justify-content-center align-items-center" style="background: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="text-center">
            <div class="spinner-border text-light" style="width: 3rem; height: 3rem;" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    </div>

    <div class="container">

        <!-- CONTACT CREATION FORM -->
        <div class="card shadow mb-5">
            <div class="card-header">
               Simpan data product
            </div>
            <div class="card-body">
                <form id="createContactForm">
                      @csrf
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="Name" class="form-label">Nama Paket <span class="text-danger">*</span></label>
                            <input type="text" disabled class="form-control" id="Name" name="Name" required>
                        </div>
                         <div class="col-md-4">
                            <label for="Code" class="form-label">Code</label>
                            <input type="text" disabled class="form-control" id="Code" name="Code">
                        </div>
                        <div class="col-md-4">
                            <label for="Description" class="form-label">Penjelasan</label>
                            <input type="text" disabled class="form-control" id="Description" name="Description">
                        </div>
                        <div class="col-md-4">
                            <label for="UnitPrice" class="form-label">
                                Harga
                                <span id="info_harga"></span>
                            </label>
                            <input type="text" class="form-control" id="UnitPrice" name="UnitPrice">
                            <input type="hidden"  id="account_id_item" name="account_id_item">
                            <input type="hidden" id="unit_price_save" name="unit_price_save"/>
                        </div>
                        <div class="col-12 text-center">
                            <button type="submit" class="btn btn-primary w-50" id="submitBtn">
                                Simpan Product <span class="spinner-border spinner-border-sm d-none" role="status" aria-hidden="true" id="submitSpinner"></span>
                            </button>
                        </div>
                        <div class="col-12" id="notif">

                        </div>
                    </div>
                </form>
                <div id="formMessage" class="mt-3 alert d-none"></div>
            </div>
        </div>

        <!-- CONTACT LIST TABLE -->
     <div class="card shadow">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Daftar Products dan Services</h5>
        <div class="d-flex gap-2">
            <input type="text" class="form-control form-control-sm" id="searchInput" placeholder="Search Code/Name...">

            <button class="btn btn-info btn-sm" id="searchBtn">
                <i class="bi bi-search"></i> Cari
            </button>

            {{-- <button class="btn btn-light btn-sm" id="refreshBtn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-arrow-clockwise" viewBox="0 0 16 16">
                    <path fill-rule="evenodd" d="M8 3a5 5 0 1 0 4.546 2.914.5.5 0 0 1 .917-.184A6 6 0 1 1 8 2z"/>
                    <path d="M8 4.464a.5.5 0 0 1 .5.5v3.427a.5.5 0 0 1-.5.5zM8 10a.5.5 0 0 1-.5-.5V6.073a.5.5 0 0 1 1 0v3.427a.5.5 0 0 1-.5.5z"/>
                </svg>
                Refresh
            </button> --}}
        </div>
    </div>
    <div class="card-body">
        <div id="contactListContainer">
            <div class="loader d-none" id="listLoader">.</div>
        </div>

        <table class="table table-striped table-bordered mt-3 d-none" id="contactTable">
            <thead class="table-dark">
                <tr>
                    <th style="color: black">#</th>
                    <th style="color: black">Nama Paket</th>
                    <th style="color: black">Code</th>
                    <th style="color: black">Harga</th>
                    <th style="color: black">Penjelasan</th>
                    <th style="color: black">Action</th>
                </tr>
            </thead>
            <tbody id="contactTableBody">
            </tbody>
        </table>

        <div class="d-flex justify-content-between align-items-center mt-3" id="paginationControls">
            <button class="btn btn-secondary btn-sm" id="prevPageBtn" disabled>Previous</button>
            <span>Page <span id="currentPageDisplay">1</span></span>
            <button class="btn btn-secondary btn-sm" id="nextPageBtn">Next</button>
        </div>
    </div>
</div>


    <div class="card shadow mb-5">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">List Invoice <span style="color: white" id="name_paket_saved"></span> </h5>
            </div>
            <div class="card-header d-flex justify-content-end align-items-center" style="background-color: white">
                <button class="btn btn-success btn-sm" id="btnSaveInvoicev2">
                    <i class="fas fa-save"></i> Save Data Update
                </button>
            </div>

            {{-- <div class="p-3 bg-light border-bottom">
                <div class="row g-2">
                    <div class="col-md-4">
                        <input type="text" id="searchInvoiceInput" class="form-control form-control-sm" placeholder="Cari Nama Jamaah...">
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-primary btn-sm w-100" id="btnSearchInvoice">
                            <i class="bi bi-search"></i> Cari
                        </button>
                    </div>
                </div>
            </div> --}}

            <div class="card-body">
                <div id="ListInvoiceContainer">
                    <div class="loader d-none" id="listInvoiceLoader"></div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-striped table-bordered mt-0 d-none" id="invoiceTable">
                    <thead class="table-dark">
                        <tr>
                            <th style="color:black">#</th>
                            <th style="color:black">No Invoice</th>
                            <th style="color:black">Name Jamaah</th>
                            <th style="color:black">Name Paket</th>
                            <th style="color:black">Date</th>
                            <th style="color:black">Due Date</th>
                            <th style="color:black">Nominal Paid</th>
                            <th style="color:black">Total</th>
                            <th style="color:black">Status</th>
                            <th style="color:black" class="text-center" width="10%">
                                <div class="form-check d-flex flex-column align-items-center justify-content-center">
                                    <input class="form-check-input" type="checkbox" id="checkAll">
                                    <label class="form-check-label small mt-1" for="checkAll" style="cursor: pointer;">
                                        All
                                    </label>
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody id="invoiceTableBody">
                    </tbody>
                </table>
            </div>

            <div class="card-footer d-flex justify-content-end align-items-center" id="invoicePagination">
                <button class="btn btn-success btn-sm" id="btnSaveInvoice">
                    <i class="fas fa-save"></i> Save Data Update
                </button>
            </div>

            <div class="card-body">
                <div id="notif_save_checbox">
                    <div class="loader d-none" id="invoice_update_checkbox"></div>
                </div>
            </div>
        </div>

    </div>



    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- JQuery and AJAX Logic -->
    <script src="{{ asset('assets/js/product.js?v.33') }}"></script>
    <script>
         // URL endpoint sesuai dengan Lumen route yang telah diperbaiki
        $(document).ready(function(){
            $('#UnitPrice').on('input', function() {
            let inputVal = $(this).val();
            let cleanVal = inputVal.replace(/[^0-9]/g, '');

            if (cleanVal === "") {
                $('#info_harga').text("");
                return;
            }
            let formatted = new Intl.NumberFormat('id-ID').format(cleanVal);
                $('#info_harga').text("(Rp " + formatted + ")");
            });
        })
    </script>

</body>
</html>
