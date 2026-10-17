<?php

use App\Http\Controllers\SchoolWebsiteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [SchoolWebsiteController::class, 'home'])->name('home');
Route::get('/artikel', [SchoolWebsiteController::class, 'index'])->name('posts.index');
Route::get('/artikel/{post:slug}', [SchoolWebsiteController::class, 'show'])->name('posts.show');
