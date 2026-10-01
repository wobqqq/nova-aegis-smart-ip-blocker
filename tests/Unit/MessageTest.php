<?php

declare(strict_types=1);

use Wobqqq\AegisSmartIpBlocker\Support\Message;

it('returns the translated line', function (): void {
    expect(Message::get('aegis-smart-ip-blocker::smart-ip-blocker.label'))->toBe(__('aegis-smart-ip-blocker::smart-ip-blocker.label'))
        ->and(Message::get('aegis-smart-ip-blocker::smart-ip-blocker.label'))->not->toBe('aegis-smart-ip-blocker::smart-ip-blocker.label');
});

it('answers the key itself for a key that names a group of lines', function (): void {
    expect(Message::get('aegis-smart-ip-blocker::smart-ip-blocker.fields'))->toBe('aegis-smart-ip-blocker::smart-ip-blocker.fields');
});
