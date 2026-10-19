<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment('Build something you are excited to open your editor for.');
})->purpose('Display a little inspiration');
