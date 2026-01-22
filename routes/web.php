<?php

use App\Http\Controllers\MasterData\HotelApiController;
use App\Http\Controllers\Web\MasterData\HotelController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Xero\ContactController;
use App\Http\Controllers\Xero\InvoicesController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Xero\ProductAndServiceController;
use App\Http\Controllers\HomeController;

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
Route::get('/cek-path', function () {
    return [
        'base_path' => base_path(),
        'public_path' => public_path(),
        'storage_path' => storage_path(),
    ];
});
Route::get('/', [DashboardController::class, 'index']);
Route::prefix('admin')->group(function () {
    Route::get('/list-pembelian-hotel', [DashboardController::class, 'getWebListPembelianHotel'])->name('admin-list-pembelian-hotel');
    Route::get('/list-pengeluaran', [DashboardController::class, 'getWebListPengeluaran'])->name('admin-list-pengeluaran');
    Route::get('/list-invoice', [DashboardController::class, 'getWebListInvoice'])->name('admin-list-invoice');

    Route::prefix('master_data')->group(function () {
        Route::get('/list-hotel', [HotelController::class, 'index'])->name('admin-master-hotel');
    });

});

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
