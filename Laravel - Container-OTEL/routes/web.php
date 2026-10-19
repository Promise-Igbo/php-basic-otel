<?php

use App\Http\Controllers\PollController;
use Illuminate\Support\Facades\Route;

Route::get('/', [PollController::class, 'index'])->name('poll.index');
Route::post('/', [PollController::class, 'vote'])->name('poll.vote');
Route::post('/session/clear', [PollController::class, 'clearSession'])->name('poll.clear-session');