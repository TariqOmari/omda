<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\SupplierController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\SaleController;

use App\Http\Controllers\LoanController;

use App\Http\Controllers\SaleInvoiceController;

    use App\Http\Controllers\ProductController;
    use App\Http\Controllers\ProductPurchaseBillController;
// Public
Route::post('/login', [AdminController::class, 'login']);

// Protected
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AdminController::class, 'logout']);
    Route::get('/me', [AdminController::class, 'me']);


});

Route::post('/products/{product}/pay', [ProductController::class, 'pay']);

    Route::apiResource('admins', AdminController::class);

    // 👇 ADD THIS
    Route::apiResource('suppliers', SupplierController::class);


// ... existing routes ...

Route::apiResource('products', ProductController::class);

// Pricing calculator
Route::post('/products/{product}/calculate', [ProductController::class, 'calculate']);

Route::apiResource('purchase-bills', ProductPurchaseBillController::class)
    ->only(['index', 'show', 'destroy']);

    Route::get('/suppliers/{supplier}/full', [SupplierController::class, 'full']);

    Route::post('/products/barcode', [ProductController::class, 'findByBarcode']);

    Route::get('/products/{product}/stock', [ProductController::class, 'stock']);

    Route::get('/products-stock', [ProductController::class, 'stockAll']);




    

Route::apiResource('customers', CustomerController::class);



Route::prefix('sales')->group(function () {
    Route::get('/',           [SaleController::class, 'index']);
    Route::post('/',          [SaleController::class, 'store']);
    Route::post('/preview',   [SaleController::class, 'preview']);   // 👈 قبل از {sale}
    Route::get('/{sale}',     [SaleController::class, 'show']);
    Route::delete('/{sale}',  [SaleController::class, 'destroy']);
    Route::post('/{sale}/pay',[SaleController::class, 'pay']);


});





// فروش
Route::apiResource('sales', SaleController::class);
Route::post('sales/preview', [SaleController::class, 'preview']);
Route::post('sales/{sale}/pay', [SaleController::class, 'pay']);

// 📄 فاکتورهای فروش
Route::get('sales/{sale}/invoice', [SaleInvoiceController::class, 'showBySale']);
Route::get('sale-invoices/stats', [SaleInvoiceController::class, 'stats']);
Route::post('sale-invoices/{saleInvoice}/cancel', [SaleInvoiceController::class, 'cancel']);
Route::apiResource('sale-invoices', SaleInvoiceController::class)->except(['store', 'update']);



/* ============ LOANS ============ */

// خلاصه کل
Route::get('loans/summary', [LoanController::class, 'summary']);

// مشتریان
Route::get('loans/customers',              [LoanController::class, 'customerLoans']);
Route::get('loans/customers/{customer}',   [LoanController::class, 'singleCustomerLoans']);

// تأمین‌کنندگان
Route::get('loans/suppliers',              [LoanController::class, 'supplierLoans']);
Route::get('loans/suppliers/{supplier}',   [LoanController::class, 'singleSupplierLoans']);

Route::post('/products/{product}/add-stock', [ProductController::class, 'addStock']);