<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule; 

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/**
 * Task scheduler of the aggregator
 */
Schedule::command('parse:prices')
    ->everyThirtyMinutes()
    ->appendOutputTo(storage_path('logs/parsing.log')) 
    ->withoutOverlapping(); // Do not start a new parsing if the old one is not finished yet
