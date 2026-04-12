<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::view('/stripe/success', 'stripe.success')->name('stripe.success');
Route::view('/stripe/cancel', 'stripe.cancel')->name('stripe.cancel');
Route::view('/stripe/account/success', 'stripe.account_success')->name('stripe.account.success');
Route::view('/stripe/account/cancel', 'stripe.account_cancel')->name('stripe.account.cancel');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
require __DIR__ . '/Backend.php';
