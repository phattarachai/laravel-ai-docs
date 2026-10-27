<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Phattarachai\AiDocs\Http\Controllers\NotesController;

Route::get('notes', [NotesController::class, 'index'])->name('index');
Route::post('notes', [NotesController::class, 'store'])->name('store');
Route::get('notes/{path}', [NotesController::class, 'show'])->where('path', '.*')->name('show');
Route::delete('notes/{path}', [NotesController::class, 'destroy'])->where('path', '.*')->name('destroy');
