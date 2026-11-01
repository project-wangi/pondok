<?php

use App\Http\Controllers\PendaftaranController;
use App\Http\Controllers\SchoolWebsiteController;
use App\Http\Controllers\SlipGajiPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SchoolWebsiteController::class, 'home'])->name('home');
Route::get('/artikel', [SchoolWebsiteController::class, 'index'])->name('posts.index');
Route::get('/artikel/{post:slug}', [SchoolWebsiteController::class, 'show'])->name('posts.show');
Route::get('/slip-gaji/print', SlipGajiPrintController::class)->name('slip-gaji.print');

Route::get('/pendaftaran', [PendaftaranController::class, 'create'])->name('pendaftaran.create');
Route::post('/pendaftaran', [PendaftaranController::class, 'store'])->middleware('throttle:5,1')->name('pendaftaran.store');
Route::get('/pendaftaran/sukses', [PendaftaranController::class, 'sukses'])->name('pendaftaran.sukses');
Route::get('/pendaftaran/akun/{token}', [PendaftaranController::class, 'masukLewatTautan'])->middleware('throttle:20,1')->name('pendaftaran.masuk.tautan');
