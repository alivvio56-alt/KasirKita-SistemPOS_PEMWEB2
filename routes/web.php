<?php

use Illuminate\Support\Facades\Route;

/*
| Frontend (Blade + JavaScript) yang mengonsumsi REST API /api.
| Autentikasi halaman dilakukan di sisi klien memakai Bearer token Sanctum;
| seluruh otorisasi tetap ditegakkan oleh API.
*/

Route::redirect('/', '/dashboard');

Route::view('/login', 'auth.login')->name('login');
Route::view('/register', 'auth.register')->name('register');
