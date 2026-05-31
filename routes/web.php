<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\InvoiceController;

Route::get('/invoice/{invoice}/download', [InvoiceController::class, 'download'])->name('invoice.download');
