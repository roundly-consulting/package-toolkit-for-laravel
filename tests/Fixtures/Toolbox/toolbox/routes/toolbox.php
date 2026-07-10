<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('toolbox/ping', static fn (): string => 'pong')->name('toolbox.ping');
