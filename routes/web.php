<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProviderActivityController;
use App\Http\Controllers\AdminActivityController;
use App\Http\Controllers\ActivityDiscoveryController;
use App\Http\Controllers\ParentInterestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::get('/activities', [ActivityDiscoveryController::class, 'index'])->name('activities.index');
Route::get('/activities/{activity}', [ActivityDiscoveryController::class, 'show'])->whereNumber('activity')->name('activities.show');
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
    Route::middleware('role:parent')->group(function (): void {
        Route::get('/parent', [ParentInterestController::class, 'index'])->name('parent.dashboard');
        Route::middleware('throttle:interest-writes')->group(function (): void {
            Route::post('/activities/{activity}/interest', [ParentInterestController::class, 'store'])->whereNumber('activity')->block(10, 10)->name('parent.interest');
            Route::post('/parent/interests/{interest}/withdraw', [ParentInterestController::class, 'withdraw'])->whereNumber('interest')->block(10, 10)->name('parent.withdraw');
            Route::post('/parent/interests/{interest}/restore', [ParentInterestController::class, 'restore'])->whereNumber('interest')->block(10, 10)->name('parent.restore');
        });
    });
    Route::middleware('role:provider')->group(function (): void {
        Route::get('/provider', [ProviderActivityController::class, 'index'])->name('provider.dashboard');
        Route::get('/provider/new/{step?}', [ProviderActivityController::class, 'create'])->where('step', '[1-5]')->block(10, 10)->name('provider.create');
        Route::post('/provider/new/{step}', [ProviderActivityController::class, 'save'])->where('step', '[1-4]')->block(10, 10)->name('provider.save');
        Route::post('/provider/submit', [ProviderActivityController::class, 'submit'])->block(10, 10)->name('provider.submit');
        Route::get('/provider/activities/{activity}', [ProviderActivityController::class, 'show'])->whereNumber('activity')->name('provider.activity');
    });
    Route::middleware('role:admin')->group(function (): void {
        Route::get('/admin', [AdminActivityController::class, 'index'])->name('admin.dashboard');
        Route::get('/admin/activities/{activity}', [AdminActivityController::class, 'show'])->whereNumber('activity')->name('admin.activity');
        Route::post('/admin/activities/{activity}/decision', [AdminActivityController::class, 'decide'])->whereNumber('activity')->name('admin.decision');
    });
});
