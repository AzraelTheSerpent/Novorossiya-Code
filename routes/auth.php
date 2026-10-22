<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:5,1');

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])->middleware('throttle:3,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/confirm-password', [App\Http\Controllers\Auth\AccountSecurityController::class, 'confirmForm'])->name('password.confirm');
    Route::post('/confirm-password', [App\Http\Controllers\Auth\AccountSecurityController::class, 'confirm']);
    Route::put('/password', [App\Http\Controllers\Auth\AccountSecurityController::class, 'password'])->name('password.update');
    Route::get('/verify-email', [App\Http\Controllers\Auth\AccountSecurityController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [App\Http\Controllers\Auth\AccountSecurityController::class, 'verify'])->middleware(['signed','throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [App\Http\Controllers\Auth\AccountSecurityController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');
    Route::post('logout', [LogoutController::class, 'destroy'])->name('logout');
    Route::get('logout', [LogoutController::class, 'guard']);
});
