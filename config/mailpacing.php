<?php

return [
    'per_minute' => max(1, (int) env('MAIL_PER_MINUTE', 30)),
];
