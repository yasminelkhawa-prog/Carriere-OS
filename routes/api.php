<?php

use App\Http\Controllers\Api\AggregatorJobsFeedController;
use Illuminate\Support\Facades\Route;

Route::get('/feeds/jobs.xml', [AggregatorJobsFeedController::class, 'show'])
    ->defaults('board', 'indeed')
    ->name('api.feeds.jobs.xml');
Route::get('/feeds/indeed.xml', [AggregatorJobsFeedController::class, 'show'])
    ->defaults('board', 'indeed')
    ->name('api.feeds.indeed.xml');
Route::get('/feeds/syndication.xml', [AggregatorJobsFeedController::class, 'show'])
    ->defaults('board', 'syndication')
    ->name('api.feeds.syndication.xml');
