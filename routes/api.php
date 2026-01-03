<?php

use App\Http\Controllers\Xero\ConfigController;
use App\Http\Controllers\Xero\ContactController;
use App\Http\Controllers\Xero\InvoicesController;
use App\Http\Controllers\Xero\InvoicesDuplicateController;
use App\Http\Controllers\Xero\ProductAndServiceController;
use App\Http\Controllers\Xero\TaxRateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Xero\TrackingController;
use App\Http\Controllers\Xero\PaymentController;
use App\Http\Controllers\InvoiceItemController;
use App\Http\Controllers\Xero\XeroContactController;
use App\Http\Controllers\GlobalExternal\CurrencyController;
use App\Http\Controllers\Xero\InvoiceItem2Controller;
use App\Http\Controllers\Xero\PaymentHistoryController;
use App\Http\Controllers\Xero\BankController;
//location
use App\Http\Controllers\MasterData\LocationCityController;
use App\Http\Controllers\MasterData\LocationDistrictController;
use App\Http\Controllers\MasterData\LocationProvinceController;
use App\Http\Controllers\MasterData\LocationVillageController;
//location

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);

//local contact-cron-job contact



//xero refresh token
// 1. Route untuk inisiasi login (Jalankan ini saat xero_token.json masih kosong)
Route::prefix("xero")->group(function(){
    Route::get('connect', [XeroContactController::class, 'connect']);
    Route::get('callback', [XeroContactController::class, 'callback']);
    Route::get('contacts', [XeroContactController::class, 'getContacts']);
});

Route::prefix("xero-integrasi")->group(function(){
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
    Route::get('/get-data-product', [ProductAndServiceController::class, 'getProduct']);//used
    Route::get('/get-data-no-limit', [ProductAndServiceController::class, 'getProductAllNoBearer']);//used
    Route::get('/get-product-withoutsame', [ProductAndServiceController::class, 'getProductNoSame']);
    Route::get('/get-by-id/{id}', [ProductAndServiceController::class, 'getProductById']);//used

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

    //local contact-cron-job payment history
    Route::get('/tes-cron', [PaymentHistoryController::class, 'insertToHistory'])->name('cron-insert-history-payment-local');//used
    Route::get('/get-history-invoice/{invoice_id}', [PaymentHistoryController::class, 'getHistoryInvoice']);//used
    Route::get('/getDetailInvoice/{idInvoice}', [InvoicesController::class, 'getDetailInvoice']);//used

    //invoice
    Route::get('/getInvoiceByIdPaket/{itemCode}', [InvoicesController::class, 'getInvoiceByIdPaket']);//used
    Route::post('/submitUpdateinvoices', [InvoicesDuplicateController::class, 'updateInvoiceSelected']);//update semua select submit
});

//payment
Route::post('/updateDeletedPayment/{payment_id}/{status}', [PaymentController::class, 'updatePaymentStatus']);
Route::get('/getDetailPayment/{idPayment}', [InvoicesController::class, 'getDetailPayment']);
Route::post('/createPayments', [PaymentController::class, 'createPayments']);
Route::get('/getInvoiceByIdPaketPaging/{itemCode}', [InvoicesController::class, 'getInvoiceByIdPaketPaging']);
Route::get('/get-invoices', [InvoicesController::class, 'getAllInvoices']);
Route::get('/all-invoices-no-limit',[InvoicesController::class,'listAllInvoices']);
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

Route::prefix("master-data")->group(function(){

    Route::prefix("location")->group(function(){

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

    });
});

