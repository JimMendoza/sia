<?php

use App\Http\Controllers\Api\Chatbot\PublicChatbotController;
use Illuminate\Support\Facades\Route;

Route::prefix('chatbot')->group(function () {
    Route::post('chat', [PublicChatbotController::class, 'chat']);
});
