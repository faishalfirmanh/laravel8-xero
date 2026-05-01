<?php

use App\Http\Controllers\MasterData\HotelApiController;
use App\Http\Controllers\Web\MasterData\HotelController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Xero\ContactController;
use App\Http\Controllers\Xero\InvoicesController;
use App\Http\Controllers\Web\Config\CurrencyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Xero\ProductAndServiceController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Transaction\Revenue\XeroTransaksiController;
use App\Http\Controllers\MasterData\MasterMaskapaiController;
use App\Http\Controllers\MasterData\RoleUserController;
use App\Http\Controllers\Transaction\Revenue\RHotelApiController;
use App\Http\Controllers\Report\LogHistoryController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


// Route::get('/', function () {
//     return 'hello dunia';
// });

Route::view('/game', 'game', ['name' => 'game']);
Route::get('/cek-path', function () {
    return [
        'base_path' => base_path(),
        'public_path' => public_path(),
        'storage_path' => storage_path(),
    ];
});
Route::get('/', [DashboardController::class, 'index'])->name('home.index');
Route::prefix('travel')->group(function () {

    Route::prefix('admin')->group(function () {

        Route::get('/list-pembelian-hotel', [DashboardController::class, 'getWebListPembelianHotel'])->name('admin-list-pembelian-hotel');
        Route::get('/list-pengeluaran', [DashboardController::class, 'getWebListPengeluaran'])->name('admin-list-pengeluaran');
        Route::get('/list-invoice', [DashboardController::class, 'getWebListInvoice'])->name('admin-list-invoice');
        Route::get('/hotel-sales-list', [DashboardController::class, 'getHotelSalesList'])->name('admin-list-invoice-sales-hotel');


        Route::prefix('transaction')->group(function () {
            Route::get('inv-local', [DashboardController::class, 'viewListInvXeroLocal'])->name('admin-list-inv-xero-web');
        });

        Route::prefix('master-data')->group(function () {
            Route::get('/hotel', [HotelController::class, 'index'])->name('admin-master-hotel');
            Route::get('/jamaah', [DashboardController::class, 'getJamaah'])->name('admin-master-jamaah');//semua client
            Route::get('/tracking-category', [DashboardController::class, 'getTrackingKategoryist'])->name('admin-master-tracking');
            Route::get('/coa', [DashboardController::class, 'getCoaList'])->name('admin-master-coa');
            Route::get('/role-user', [RoleUserController::class, 'index'])->name('role-user.index');
            Route::get('/maskapai', [MasterMaskapaiController::class, 'index'])->name('maskapai.index');
            Route::get('/business-line', [DashboardController::class, 'getBusiness'])->name('business.index');
            Route::get('/travel', [DashboardController::class, 'getTravel'])->name('travel.index');
        });

        Route::prefix('config')->group(function () {

            Route::get('/currency', [DashboardController::class, 'getConfigCurrency'])->name('config-currency-web');
            Route::get('/role-user', [DashboardController::class, 'getConfigRoleUser'])->name('config-role-user');
            Route::get('/role-menu', [DashboardController::class, 'getConfigRolesMenu'])->name('config-role-menu');//nama prefix di api
        });


        Route::prefix('transaksi')->group(function () {
            Route::get('sales-invoice', [DashboardController::class, 'getTransInvoice'])->name('web-sales-inv');
            Route::get('purchase-orders', [DashboardController::class, 'getTransPurchaseOrder'])->name('web-purchase-or');
            Route::get('purchase-bills', [DashboardController::class, 'getTransPurchaseBill'])->name('web-purchase-bill');
        });



        Route::prefix('report')->group(function () {
            Route::get('/log-history', [LogHistoryController::class, 'index'])->name('web-log-history-list');
        });
    });
});

//print-pdf
Route::get('/invoice/print/{id}', [RHotelApiController::class, 'printInvoice'])->name('invoice_hotel_print');

Route::get('/coba_redirect', function () {
    return "aaa";
});

Route::get('/contactnya', [ContactController::class, 'viewContackForm']);
Route::get('/login_view', [HomeController::class, 'getLogin']);
Route::post('/createContact', [ContactController::class, 'createContact']);
Route::get('/getContact', [ContactController::class, 'getContact']);

//get product &

Route::get('/list_productAndService', [ProductAndServiceController::class, 'viewProduct']);
Route::get('/detailInvoiceWeb/{invoiceId}', [InvoicesController::class, 'viewDetailInvoice']);


Route::get('/xero/list-transaksi', [XeroTransaksiController::class, 'index'])
    ->name('xero-list-transaksi');

Route::get('/api/xero/list-transaksi', [XeroTransaksiController::class, 'listTransaksi']);
Route::post('/api/xero/void/{id}', [XeroTransaksiController::class, 'voidInvoice']);


