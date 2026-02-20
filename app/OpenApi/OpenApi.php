<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'SIA Chatbot API',
    description: 'Fase 1: JWT (admin) + endpoint publico del chatbot (mock).',
)]
#[OA\Server(
    url: '/',
    description: 'Default',
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT',
)]
class OpenApi
{
}
