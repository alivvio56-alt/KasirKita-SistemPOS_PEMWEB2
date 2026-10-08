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

Route::view('/dashboard', 'pages.dashboard')->name('dashboard');
Route::view('/pos', 'pages.pos')->name('pos');
Route::view('/orders', 'pages.orders')->name('orders');
Route::view('/customers', 'pages.customers')->name('customers');
Route::view('/products', 'pages.products')->name('products');
Route::view('/categories', 'pages.categories')->name('categories');
Route::view('/stock', 'pages.stock')->name('stock');
Route::view('/users', 'pages.users')->name('users');
Route::view('/reports', 'pages.reports')->name('reports');
