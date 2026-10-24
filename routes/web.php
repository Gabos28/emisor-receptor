<?php

use App\Http\Controllers\ChessController;
use App\Http\Controllers\RemoteStyleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/ajedrez'));
Route::get('/emisor', [RemoteStyleController::class, 'emisor'])->name('emisor');
Route::post('/emitir', [RemoteStyleController::class, 'emitir'])->name('emitir');
Route::get('/receptor', [RemoteStyleController::class, 'receptor'])->name('receptor');

// Ajedrez en tiempo real (mismo patrón: controlador + evento + canal de Reverb)
Route::get('/ajedrez', [ChessController::class, 'lobby'])->name('chess.lobby');
Route::post('/ajedrez', [ChessController::class, 'create'])->name('chess.create');
Route::post('/ajedrez/unirse', [ChessController::class, 'join'])->name('chess.join');
Route::get('/ajedrez/{room}/estado', [ChessController::class, 'state'])->name('chess.state');
Route::post('/ajedrez/{room}/movimiento', [ChessController::class, 'move'])->name('chess.move');
Route::post('/ajedrez/{room}/mensaje', [ChessController::class, 'message'])->name('chess.message');
Route::post('/ajedrez/{room}/reiniciar', [ChessController::class, 'reset'])->name('chess.reset');
Route::get('/ajedrez/{room}', [ChessController::class, 'board'])->name('chess.board');
