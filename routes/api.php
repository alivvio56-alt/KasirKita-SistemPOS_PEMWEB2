<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| REST API KasirKita (prefix /api).
| Endpoint ditambahkan oleh Orang 2 (branch "api").
*/

Route::get('/ping', fn () => response()->json(['success' => true, 'message' => 'KasirKita API aktif']));

Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');
