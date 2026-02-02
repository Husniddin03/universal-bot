<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\TelegramBotController;
use App\Models\Movie;
use Telegram\Bot\Laravel\Facades\Telegram;

Route::post('/telegram/webhook', [TelegramBotController::class, 'webhook']);

Route::get('setwebhook', function () {
    $response = Telegram::setWebhook(['url' => env('TELEGRAM_WEBHOOK_URL') . '/telegram/webhook']);
    return $response;
});

Route::get('/', function () {
    $movies = Movie::paginate(10);
    return view('welcome', compact('movies'));
});
