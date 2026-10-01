<?php

declare(strict_types=1);

return [
    'label' => 'Smart IP Blocker',
    'description' => 'Bans an IP address that sends more requests per minute than allowed, on the site and in Nova.',
    'on' => 'On: more than :requests requests a minute bans an IP for :hours h.',
    'off' => 'Off: no IP is rate-limited.',

    'fields' => [
        'enabled' => 'Enable the Smart IP Blocker',
        'requests_per_minute' => 'Requests per minute',
        'ban_hours' => 'Ban duration (hours)',
        'view' => 'Blocked page view',
        'max_tracked_ips' => 'Tracked IPs limit',
        'excluded_ips' => 'Excluded IPs',
        'ip' => 'IP or subnet',
        'excluded_headers' => 'Excluded headers',
        'header' => 'Header',
        'value' => 'Contains',
    ],

    'help' => [
        'enabled' => 'Counts the requests of every IP address over a one-minute window, on the web routes and in Nova.',
        'requests_per_minute' => 'An IP that sends more requests than this within a minute is banned (1 to 10000).',
        'ban_hours' => 'How long a banned IP gets 429 Too Many Requests (1 to 720).',
        'view' => 'The Blade view a banned visitor sees. It must exist; JSON requests get a JSON answer.',
        'max_tracked_ips' => 'At most this many IPs are counted at once; past it the oldest count is dropped, never a ban. 0 counts every IP (up to 10000).',
        'excluded_ips' => 'Never counted: an IP address (203.0.113.10) or a subnet (10.0.0.0/8). Your IP, :ip, must stay in the list while the blocker is on.',
        'excluded_headers' => 'Never counted when the header contains the value (case-insensitive), for well-behaved bots. A client can send any header: anyone who knows this rule can bypass the limit.',
    ],

    'validation' => [
        'ip' => 'Enter an IP address or a subnet in CIDR notation.',
        'view' => 'That view does not exist.',
        'current_ip' => 'Add your own IP address, :ip, or a subnet that contains it, to the excluded IPs, so that the blocker cannot ban you.',
    ],

    'blocked' => [
        'title' => 'Too many requests',
        'message' => 'Your IP address sent too many requests and is temporarily blocked.',
        'retry' => 'Please try again in :minutes minute.|Please try again in :minutes minutes.',
    ],

    'checks' => [
        'off' => 'The Smart IP Blocker is off.',
        'cache' => [
            'label' => 'Smart IP Blocker cache',
            'pass' => 'Counts and bans are kept in the ":driver" cache.',
            'fail' => 'The ":driver" cache forgets counts and bans after each request: nobody is ever banned. Use redis, memcached, database or file.',
        ],
        'headers' => [
            'label' => 'Smart IP Blocker excluded headers',
            'pass' => 'No request is excluded by a header.',
            'warn' => 'Requests are excluded by a header the client sends itself (:headers): anyone who sends it bypasses the limit.',
        ],
    ],
];
