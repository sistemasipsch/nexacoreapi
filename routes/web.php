<?php

use Illuminate\Support\Facades\Route;

// Serve storage files WITHOUT middleware (fix for shared hosting/missing sessions table)
// Handles /storage/..., /public/storage/..., /api/storage/..., or /public/api/storage/...
Route::get('{fullPath}', function (string $fullPath) {
    // Extract the part after 'storage/' allowing for public/ and/or api/ prefixes
    if (preg_match('/(?:(?:public\/)?(?:api\/)?)?storage\/(.+)$/', $fullPath, $matches)) {
        $cleanPath = ltrim(str_replace(['storage/', 'public/', 'api/'], '', $matches[1]), '/');
        
        $candidates = [
            storage_path('app/public/' . $cleanPath),
            public_path('storage/' . $cleanPath),
            storage_path('app/' . $cleanPath),
            public_path($cleanPath),
        ];

        foreach ($candidates as $absolutePath) {
            if (file_exists($absolutePath) && !is_dir($absolutePath)) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
                $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
                if (in_array($ext, $allowed)) {
                    return response()->file($absolutePath);
                }
                abort(403, 'Extension not allowed');
            }
        }
    }
    abort(404);
})->where('fullPath', '(public\/)?(api\/)?storage\/.*')
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
