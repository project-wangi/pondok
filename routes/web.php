<?php

use App\Http\Controllers\SchoolWebsiteController;
use App\Http\Controllers\SlipGajiPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SchoolWebsiteController::class, 'home'])->name('home');
Route::get('/artikel', [SchoolWebsiteController::class, 'index'])->name('posts.index');
Route::get('/artikel/{post:slug}', [SchoolWebsiteController::class, 'show'])->name('posts.show');
Route::get('/slip-gaji/print', SlipGajiPrintController::class)->name('slip-gaji.print');
