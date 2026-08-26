<?php

use Illuminate\Support\Facades\Route;

// Serve storage and equipment image files WITHOUT middleware
// Handles /storage/..., /public/storage/..., /api/storage/..., /pcEquipos/..., /equipos/...
Route::get('{fullPath}', function (string $fullPath) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    $clean = ltrim(preg_replace('#^(api/|public/)+#i', '', $fullPath), '/');

    // 1. Try storage path directly (e.g. storage/app/public/...)
    $storageRel = preg_replace('#^storage/#i', '', $clean);
    $storagePath = storage_path('app/public/' . $storageRel);
    if (file_exists($storagePath)) {
        $ext = strtolower(pathinfo($storagePath, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            return response()->file($storagePath);
        }
    }

    // 2. Try public folder directly
    $publicPath = public_path($clean);
    if (file_exists($publicPath) && is_file($publicPath)) {
        $ext = strtolower(pathinfo($publicPath, PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            return response()->file($publicPath);
        }
    }

    // 3. Try storage under pcEquipos or equipos
    foreach (['pcEquipos', 'equipos'] as $folder) {
        $candidate = storage_path("app/public/{$folder}/" . basename($clean));
        if (file_exists($candidate) && is_file($candidate)) {
            return response()->file($candidate);
        }
        $pubCandidate = public_path("{$folder}/" . basename($clean));
        if (file_exists($pubCandidate) && is_file($pubCandidate)) {
            return response()->file($pubCandidate);
        }
    }

    abort(404);
})->where('fullPath', '(public\/)?(api\/)?(storage|pcEquipos|equipos)\/.*')
    ->withoutMiddleware([
        \Illuminate\Session\Middleware\StartSession::class,
        \Illuminate\View\Middleware\ShareErrorsFromSession::class,
        \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
        \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Illuminate\Cookie\Middleware\EncryptCookies::class,
    ]);

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
