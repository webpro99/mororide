<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

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

/*
| One-time web installer: enter MySQL + admin details, it creates all tables,
| seeds reference data, creates the admin, then locks itself.
| Visit https://your-domain/install once after uploading the code.
*/
Route::get('/install', [InstallController::class, 'show']);
Route::post('/install', [InstallController::class, 'run']);

/*
| Admin web console — a token-based SPA that talks to the /api/admin/* endpoints.
| `/admin/login` signs in and redirects to `/admin`, which guards on the token.
*/
Route::view('/admin/login', 'admin.login')->name('admin.login');
Route::view('/admin', 'admin.dashboard')->name('admin.dashboard');

// The old browser demo admin page is replaced by the real console.
Route::redirect('/app/admin', '/admin');

Route::get('/app/{screen?}', function (?string $screen = 'home') {
    $allowed = ['home', 'rider', 'concierge', 'driver'];

    abort_unless(in_array($screen, $allowed, true), 404);

    return view('app', ['screen' => $screen]);
});
