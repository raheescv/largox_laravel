<?php

return [
    // Per-server secrets live on the Server model. This is the default fallback
    // and the connect/read timeouts for the HTTP client.
    'default_secret' => env('AGENT_DEFAULT_SECRET'),

    'connect_timeout' => (int) env('AGENT_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('AGENT_REQUEST_TIMEOUT', 600),

    // Tolerated clock skew when signing; must match the agent's window.
    'timestamp_skew' => 300,
];
