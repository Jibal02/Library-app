<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;

Route::prefix('v1')->group(function () {

    // buat publik gapake token
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth');
    Route::get('/books', [BookController::class, 'index']);
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/authors', [BookController::class, 'authors']);

    // harus login dulu baru bisa akses route ini
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/user', function (Request $request) {
            return response()->json([
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'phone' => $request->user()->phone,
                'role' => $request->user()->role,
            ]);
        });

        Route::get('/profile', [ProfileController::class, 'show']);
        Route::get('/profile/loans', [ProfileController::class, 'loans']);
        Route::get('/profile/history', [ProfileController::class, 'history']);

        Route::get('/reservations', [ReservationController::class, 'index']);
        Route::post('/reservations', [ReservationController::class, 'store']);
        Route::delete('/reservations/{id}', [ReservationController::class, 'destroy']);

        Route::get('/books/{id}', [BookController::class, 'show']);

        // khusus admin & staff
        Route::middleware('role:admin,staff')->group(function () {
        Route::post('/books', [BookController::class, 'store']);
        Route::put('/books/{id}', [BookController::class, 'update']);
        Route::delete('/books/{id}', [BookController::class, 'destroy']);
        Route::get('/books/scan/{isbn}', [BookController::class, 'scanByIsbn']);
        Route::post('/loans/issue', [LoanController::class, 'issue']);
        Route::post('/loans/{id}/return', [LoanController::class, 'return']);
        Route::get('/loans', [LoanController::class, 'index']);
        Route::get('/transactions', [LoanController::class, 'transactions']);
        Route::get('/members', [MemberController::class, 'index']);
        Route::patch('/members/{id}', [MemberController::class, 'update']);
        Route::patch('/members/{id}/status', [MemberController::class, 'updateStatus']);
        Route::delete('/members/{id}', [MemberController::class, 'destroy']);
            Route::get('/members/{id}/history', [MemberController::class, 'history']);
            Route::get('/reports/overdue', [ReportController::class, 'overdue']);
        });

    });

});
