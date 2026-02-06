<?php

use App\Http\Controllers\MasterData\HotelApiController;
use App\Http\Controllers\Xero\ConfigController;
use App\Http\Controllers\Xero\ContactController;
use App\Http\Controllers\Xero\InvoicesController;
use App\Http\Controllers\Xero\InvoicesDuplicateController;
use App\Http\Controllers\Xero\ProductAndServiceController;
use App\Http\Controllers\Xero\TaxRateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Xero\TrackingController;
use App\Http\Controllers\Xero\WebhookController;
use App\Http\Controllers\Xero\PaymentController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\Config\ConfigCurrencyApiController;
use App\Http\Controllers\Xero\XeroSyncInvoicePaidController;
use App\Http\Controllers\Xero\XeroContactController;
use App\Http\Controllers\GlobalExternal\CurrencyController;
use App\Http\Controllers\Xero\InvoiceItem2Controller;
use App\Http\Controllers\Xero\PaymentHistoryController;
use App\Http\Controllers\Xero\BankController;
//master data
use App\Http\Controllers\MasterData\PengeluaranNameController;
use App\Http\Controllers\MasterData\DataApiJamaahController;
use App\Http\Controllers\MasterData\MasterMaskapaiController;

//master data
//location

use App\Http\Controllers\MasterData\LocationCityController;
use App\Http\Controllers\MasterData\LocationDistrictController;
use App\Http\Controllers\MasterData\LocationProvinceController;
use App\Http\Controllers\MasterData\LocationVillageController;
//location
//transaction
use App\Http\Controllers\Transaction\Revenue\RPaymentHotelApiController;
use App\Http\Controllers\Transaction\Revenue\RHotelApiController;

use App\Http\Controllers\Transaction\Revenue\XeroTransaksiController;

use App\Http\Controllers\Transaction\Expenses\ExpensesPackageApiController;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/register', [AuthController::class, 'register'])->name('register');
//Route::post('/logout', [AuthController::class, 'logout']);
Route::middleware('auth:sanctum')->post('/logout', [AuthController::class, 'logout'])->name('logout');

//local contact-cron-job contact



//xero refresh token
// 1. Route untuk inisiasi login (Jalankan ini saat xero_token.json masih kosong)
Route::prefix("xero")->group(function () {
    Route::get('connect', [XeroContactController::class, 'connect']);
    Route::get('callback', [XeroContactController::class, 'callback']);
    Route::get('contacts', [XeroContactController::class, 'getContacts'])->middleware('xero.limit');
    Route::get('get_contact_byid', [ContactController::class, 'getContactsById'])->name('get-contact-byuuid');
    Route::get('contacts_search', [ContactController::class, 'getContactsSearch'])->name('search-contact-select2');//untuk select2
    Route::get('sync-invoice-paid', [XeroSyncInvoicePaidController::class, 'getInvoicePaidArrival'])->name('sync-invoice-paid');//pindah invoice, detail dan item xero ke local db
    Route::get('sync-item-paket', [XeroSyncInvoicePaidController::class, 'getPaketHajiUmroh'])->name('sync-item-paket');
});

Route::prefix("xero-integrasi")->group(function () {
    Route::get('/get-data', [ContactController::class, 'getContact']);

    Route::get('/get-contact-local', [ContactController::class, 'getContactLocal']);//used
    Route::get('getCodeBeforeToken', [ConfigController::class, 'getAuthUrl']);
    Route::post('getToken', [ConfigController::class, 'getToken']);
    Route::get('/xero/login', [ConfigController::class, 'redirect']);
    //Route::get('/xero/callback', [ConfigController::class, 'callback']);
    Route::post('/create-data', [ContactController::class, 'createContact']);

    //
    Route::post('/save-data-product', [ProductAndServiceController::class, 'updateProduct']);//used
    //proudct
    Route::get('/get-data-product', [ProductAndServiceController::class, 'getProduct'])->name('xero-list-product-paging');//used
    Route::get('/get-data-no-limit', [ProductAndServiceController::class, 'getProductAllNoBearer']);//used
    Route::get('/get-product-withoutsame', [ProductAndServiceController::class, 'getProductNoSame']);
    Route::get('/get-by-id/{id}', [ProductAndServiceController::class, 'getProductById'])->name('xero-product-by-id');//used

    //kategory (tracking)
    Route::get('/get_divisi', [TrackingController::class, 'getKategory']);//used
    Route::get('/get_agent', [TrackingController::class, 'getAgent']);//used

    //save per rows
    Route::post('/invoice/item/save', [InvoiceItem2Controller::class, 'saveItem'])->name('invoice.item.save');//used
    Route::delete('/invoice/item/{id}', [InvoiceItem2Controller::class, 'deleteItem'])->name('invoice.item.delete');//used
    //tax rate
    Route::get('/tax_rate', [TaxRateController::class, 'getTaxRate']);//

    //getAcountDetailAcount Invoice
    Route::get('/getAllAccount', [PaymentController::class, 'getGroupedAccounts']);//used

    //local insert yang sudah paid saja.
    Route::get('/tes-cron', [PaymentHistoryController::class, 'insertToHistory'])->name('cron-insert-history-payment-local');//used  //dibikin button saja

    Route::get('/get-history-invoice/{invoice_id}', [PaymentHistoryController::class, 'getHistoryInvoice']);//used
    Route::get('/getDetailInvoice/{idInvoice}', [InvoicesController::class, 'getDetailInvoice']);//used
    Route::post('/updateDateInv', [InvoicesDuplicateController::class, 'updateHeaderDetailInv']);//used

    //invoice
    Route::get('/getInvoiceByIdPaket', [InvoicesController::class, 'getInvoiceByIdPaket']);//used
    Route::post('/submitUpdateinvoices', [InvoicesDuplicateController::class, 'updateInvoiceSelected']);//update semua select submit

    //hapus invoice untuk clean data //forceDeleteCreditNote.,forceVoidOverpayment
    Route::post('/delete-invoice-byuuid/{uuid_inv}', [InvoicesController::class, 'forceDeleteInvoice'])->name('delete_invoice_uuid');
    Route::post('/delete-creditnote-byuuid/{creditNoteId}', [InvoicesController::class, 'forceDeleteCreditNote'])->name('delete_creditnote_uuid');
    Route::post('/delete-overpayment-byuuid/{overpaymentId}', [InvoicesController::class, 'forceVoidOverpayment'])->name('delete_overpayment_uuid');
});

Route::prefix("admin-web")->group(function () {
    Route::get('/get-invoice-local', [XeroSyncInvoicePaidController::class, 'getAllInvoiceLocal'])->name('list-invoice-select2');//untuk select2 approved
    Route::get('/get-item-byinvoice', [XeroSyncInvoicePaidController::class, 'getDetaPaketByInvoice'])->name('get-item-byinvoice');//multi
    Route::get('/get-paket-local', [XeroSyncInvoicePaidController::class, 'getAllPaketLocal'])->name('list-paket-select2');
    //bawah untuk select2 paket by invoice->approve
    Route::get('/get-paket-filterby-invoice', [XeroSyncInvoicePaidController::class, 'getPaketByUuuidInvoice'])->name('get-paket-filterby-invoice');
    Route::get('/getInvoicesAll', [InvoicesController::class, 'getInvoicesAll'])->name('list-invoice-web');
    Route::get('/getInvoicesAll', [InvoicesController::class, 'getInvoicesAll'])->name('list-invoice-web');
    Route::get('list-transaksi', [XeroTransaksiController::class, 'listTransaksi'])->name('xero-list-invoice');// LIST

    Route::middleware(['auth:sanctum', 'xss'])->prefix("transaksi")->group(function () {
        Route::prefix('revenue')->group(function () {
            Route::prefix('hotel')->group(function () {
                Route::get('/get', [RHotelApiController::class, 'getAllPaginate'])->name('list-revanue-hotel');
                Route::get('/getTotalAmount', [RHotelApiController::class, 'getTotalAmount'])->name('total-amount-revanue-hotel');
                Route::post('/store', [RHotelApiController::class, 'savedRhotel'])->name('save-revenue-hotel');
                Route::get('/getById', [RHotelApiController::class, 'getInvoiceReveueHotel'])->name('byid-revanue-hotel');
                Route::post('/deleted', [RHotelApiController::class, 'deleteInvoiceReveueHotel'])->name('deleted-revanue-hotel');
                //payment
                Route::get('/getAllPaymentByIdInv', [RPaymentHotelApiController::class, 'getAllByIdInv'])->name('get-allpayment-hotelby-idinvoice');
                Route::get('/getRowById', [RPaymentHotelApiController::class, 'by_id_row'])->name('get-by-row-payment-hotel');
                Route::post('/deleteRow', [RPaymentHotelApiController::class, 'deleted_row'])->name('delete-by-row-payment-hotel');
                Route::post('/savedRowCreate', [RPaymentHotelApiController::class, 'store'])->name('create-by-row-payment-hotel');
                Route::post('/updateRowPayment', [RPaymentHotelApiController::class, 'updated_row'])->name('updated-by-row-payment-hotel');
            });
        });

        Route::prefix('expenses')->group(function () {
            Route::prefix("/package-profit")->group(function () {
                Route::get('/getData', [ExpensesPackageApiController::class, 'getAllPaginate'])->name('t_pp_package_getall');
                Route::get('/get_by_id', [ExpensesPackageApiController::class, 'getById'])->name('t_gbyid_pengeluaran');
                Route::post('/delete', [ExpensesPackageApiController::class, 'deletedExpenses'])->name('t_pp_package_delete');
                Route::post('/save', [ExpensesPackageApiController::class, 'store'])->name('t_pp_package_create');
                Route::post('/saveDetail', [ExpensesPackageApiController::class, 'storeDetail'])->name('t_pp_package_createdetail');
                Route::post('/deletedRow', [ExpensesPackageApiController::class, 'deleteDetail'])->name('t_pp_package_deleteddetail');
            });
        });
    });

    Route::middleware(['auth:sanctum', 'xss'])->prefix("master-data")->group(function () {

        //keterangna pengeluaran
        Route::prefix("pengeluaran")->group(function () {
            Route::get('/getData', [PengeluaranNameController::class, 'getData'])->name('md_g_pengeluaran');
            Route::get('/get_by_id', [PengeluaranNameController::class, 'getById'])->name('md_gbyid_pengeluaran');
            Route::post('/save', [PengeluaranNameController::class, 'store'])->name('md_store_pengeluaran');
            Route::get('/get_select_2', [PengeluaranNameController::class, 'getAllNamePengeluaranLocal'])->name('md_select2_name_pengeluaran');
            Route::get('delete_local_sync_inv', [XeroSyncInvoicePaidController::class, 'deletedDataLocal'])->name('delete-sync-invoice-paid');
        });

        Route::prefix('hotel')->group(function () {
            Route::get('/get', [HotelApiController::class, 'getAllPaginate'])->name('getAllHotelApi');
            Route::get('/search_hotel', [HotelApiController::class, 'SearchHotel'])->name('search_hotel_select2');
            Route::post('/save', [HotelApiController::class, 'store'])->name('saveMasterHotel');
            Route::post('/delete', [HotelApiController::class, 'delete'])->name('deleteMasterHotel');
        });

        Route::prefix("contact-xero")->group(function () {
            Route::get('/get', [DataApiJamaahController::class, 'getAllPaginate'])->name('getAllContactApi');
            Route::get('/get_by_id', [DataApiJamaahController::class, 'getById'])->name('getByIdContact');
        });
        Route::prefix("maskapai")->group(function(){
            Route::get('/get-data', [MasterMaskapaiController::class, 'getData'])->name('maskapai.getdata');
            Route::get('/get-by-id', [MasterMaskapaiController::class, 'getById'])->name('maskapai.getbyid');
            Route::post('/save', [MasterMaskapaiController::class, 'store'])->name('maskapai.save');

    });
        });

    });

    Route::prefix("config-currency")->group(function () {
        Route::get('/getById', [ConfigCurrencyApiController::class, 'fingById'])->name('getByIdCurrency');
        Route::post('/save', [ConfigCurrencyApiController::class, 'store'])->name('saveConfigCurrency');
    });


Route::post('/xero-webhook', [WebhookController::class, 'handleXero'])->name('xero-webhook');



//payment
Route::post('/updateDeletedPayment/{payment_id}/{status}', [PaymentController::class, 'updatePaymentStatus']);
Route::get('/getDetailPayment/{idPayment}', [InvoicesController::class, 'getDetailPayment']);
Route::post('/createPayments', [PaymentController::class, 'createPayments']);
Route::get('/getInvoiceByIdPaketPaging/{itemCode}', [InvoicesController::class, 'getInvoiceByIdPaketPaging']);
Route::get('/get-invoices', [InvoicesController::class, 'getAllInvoices']);
Route::get('/all-invoices-no-limit', [InvoicesController::class, 'listAllInvoices']);
Route::post('/updatePerbaris/{parent_id}/{amount_input}/{line_item_id}', [InvoicesController::class, 'updateInvoicePerRows']);//untuk testing

//paid
Route::get('/get-bank/{paymentId}', [InvoiceItem2Controller::class, 'getBankAccountFromPayment']);
Route::post('/bank-overpayment', [BankController::class, 'postBankOverpayment']);
Route::get('/get-all-bank', [BankController::class, 'getAllBank']);

//currency
Route::get('/convert/idr-to-sar', [CurrencyController::class, 'idrToSar']);
Route::get('/convert/sar-to-idr', [CurrencyController::class, 'sarToIdr']);
Route::get('/convert/idr-to-usd', [CurrencyController::class, 'idrToUsd']);
Route::get('/convert/usd-to-idr', [CurrencyController::class, 'usdToIdr']);

//

Route::prefix("master-data")->group(function () {



    Route::prefix("location")->group(function () {

        Route::prefix('city')->group(function () {
            Route::post('/get-city', [LocationCityController::class, 'getAllCityByIdProf'])->name('getAllCityByIdProf');
            Route::post('/search-city', [LocationCityController::class, 'SearchCityByIdProf'])->name('SearchCityByIdProf');
            Route::post('/getCityById', [LocationCityController::class, 'getCityById'])->name('getCityById');
        });

        Route::prefix('province')->group(function () {
            Route::post('/get-all', [LocationProvinceController::class, 'getAllProf'])->name('getAllProf');
            Route::post('/search-prof', [LocationProvinceController::class, 'SearchProf'])->name('SearchProf');
            Route::post('/getProvById', [LocationProvinceController::class, 'getProvById'])->name('getProvById');
        });

        //
        Route::prefix('subdistrict')->group(function () {
            Route::post('/get-kec', [LocationDistrictController::class, 'getAllSubdisByCityId'])->name('getAllSubdisByCityId');
            Route::post('/search-sub', [LocationDistrictController::class, 'SearchSubdis'])->name('SearchSubdis');
            Route::post('/getSubById', [LocationDistrictController::class, 'getSubdisById'])->name('getSubdisById');
        });

        Route::prefix('village')->group(function () {
            Route::post('/get-village', [LocationVillageController::class, 'getAllVillageBySubdisId'])->name('getAllVillageBySubdisId');
            Route::post('/search-village', [LocationVillageController::class, 'SearchVillage'])->name('SearchVillage');
            Route::post('/getVillageById', [LocationVillageController::class, 'getVillageById'])->name('getVillageById');
        });
// // List transaksi Xero
// Route::get('admin/xero/list-transaksi', [XeroTransaksiController::class, 'listTransaksi']);

// // Delete invoice (DRAFT / SUBMITTED)
// Route::post('admin/xero/delete/{id}', [XeroTransaksiController::class, 'deleteInvoice']);

// // Void invoice (AUTHORISED)
// Route::post('admin/xero/void/{id}', [XeroTransaksiController::class, 'voidInvoice']);
});
    
});

