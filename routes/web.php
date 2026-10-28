<?php

use App\Http\Controllers\Web\AnnouncementController;
use App\Http\Controllers\Web\SessionController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('announcements.index')
        : view('auth.login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:6,1')->name('login.store');
});

Route::post('/logout', [SessionController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:admin,staff'])->prefix('announcements')->name('announcements.')->controller(AnnouncementController::class)->group(function (): void {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{announcement}', 'update')->name('update');
    Route::delete('/{announcement}', 'destroy')->name('destroy');
});
