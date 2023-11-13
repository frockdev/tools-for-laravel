<?php

return [
    'address'=>env('NATS_ADDRESS', 'nats.nats'),
    'autoconnectDisabled'=>env('NATS_AUTOCONNECT_DISABLED', false),
    'user'=>env('NATS_USER', null),
    'pass'=>env('NATS_PASS', null),
];
