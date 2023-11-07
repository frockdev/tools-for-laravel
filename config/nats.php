<?php

return [
    'address'=>env('NATS_ADDRESS', 'nats.nats'),
    'autoconnectDisabled'=>env('NATS_AUTOCONNECT_DISABLED', false),
];
