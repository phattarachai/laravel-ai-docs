<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Phattarachai\AiDocs\Http\Controllers\AiDocsController;

/*
| Both routes below must stay ahead of the
| catch-all, whose pattern would otherwise swallow them.
*/

Route::get('/_search.json', [AiDocsController::class, 'search'])->name('search');

Route::get('/_media/{path}', [AiDocsController::class, 'media'])
    ->where('path', '.*')
    ->name('media');

Route::get('/{path?}', [AiDocsController::class, 'show'])
    ->where('path', '[\p{L}\p{N}/_.-]*')
    ->name('index');
