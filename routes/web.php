<?php

use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('/health', fn () => response()->json(['app' => 'gotry', 'runtime' => 'laravel', 'status' => 'ok']));
Route::middleware('guest')->group(function (): void {
    Route::view('/account', 'auth.account', ['mode' => 'login'])->name('login');
    Route::view('/register', 'auth.account', ['mode' => 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:signup');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
});
Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', function (Request $request) {
        return redirect()->route(match ($request->user()->role) {
            'parent' => 'parent.dashboard',
            'provider' => 'provider.dashboard',
            'admin' => 'admin.dashboard',
            default => abort(403),
        });
    })->name('dashboard');
    foreach (['parent', 'provider', 'admin'] as $role) {
        Route::view('/'.$role, 'dashboard', ['role' => $role])
            ->middleware('role:'.$role)->name($role.'.dashboard');
    }
});
