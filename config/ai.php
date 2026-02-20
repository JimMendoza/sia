<?php

return [
    'service_url' => env('AI_SERVICE_URL', 'http://localhost:8001'),

    'timeout' => (int) env('AI_SERVICE_TIMEOUT', 8),

    'retrieve_top_k' => (int) env('AI_SERVICE_TOP_K', 5),
];

