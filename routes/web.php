<?php

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
Route::get('/', [DashboardController::class, 'index']);
Route::prefix('admin')->group(function(){
    Route::get('/list-invoice',[DashboardController::class, 'getWebListInvoice'])->name('admin-list-invoice');
});

Route::get('/coba_redirect',function(){
    return "aaa";
});

Route::get('/contactnya', [ContactController::class, 'viewContackForm']);
Route::get('/login_view', [HomeController::class, 'getLogin']);
Route::post('/createContact', [ContactController::class, 'createContact']);
Route::get('/getContact', [ContactController::class, 'getContact']);

//get product &

Route::get('/list_productAndService', [ProductAndServiceController::class, 'viewProduct']);
Route::get('/detailInvoiceWeb/{invoiceId}', [InvoicesController::class, 'viewDetailInvoice']);
