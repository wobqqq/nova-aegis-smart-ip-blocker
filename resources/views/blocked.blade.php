<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('aegis-smart-ip-blocker::smart-ip-blocker.blocked.title') }}</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; background: #f8fafc; color: #1e293b; }
        main { max-width: 32rem; padding: 2rem; text-align: center; }
        h1 { font-size: 1.5rem; margin: 0 0 1rem; }
        p { margin: 0.5rem 0; color: #475569; }
    </style>
</head>
<body>
<main>
    <h1>{{ __('aegis-smart-ip-blocker::smart-ip-blocker.blocked.title') }}</h1>
    <p>{{ $message }}</p>
    <p>{{ trans_choice('aegis-smart-ip-blocker::smart-ip-blocker.blocked.retry', (int)ceil($retryAfter / 60), ['minutes' => (int)ceil($retryAfter / 60)]) }}</p>
</main>
</body>
</html>
