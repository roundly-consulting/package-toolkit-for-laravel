<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('toolbox/disabled', static fn (): string => 'nope')->name('toolbox.disabled');
