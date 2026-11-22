<?php

use App\Http\Controllers\InvoiceController;
use Illuminate\Support\Facades\Route;

Route::get('/invoice/{invoice}/download', [InvoiceController::class, 'download'])->name('invoice.download');
