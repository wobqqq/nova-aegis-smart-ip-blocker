<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker\Http\Middleware;

use Closure;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlocker;
use Wobqqq\AegisSmartIpBlocker\SmartIpBlockerSettings;

final readonly class BlockExcessiveRequests
{
    /**
     * Marks a request already counted, as it may pass both the web and a Nova group.
     */
    private const COUNTED = 'aegis.smart-ip-blocker.counted';

    public function __construct(private SmartIpBlocker $blocker, private ViewFactory $views)
    {
    }

    /**
     * @param Closure(Request): Response $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->attributes->has(self::COUNTED)) {
            return $next($request);
        }

        $request->attributes->set(self::COUNTED, true);

        try {
            $retryAfter = $this->blocker->hit($request);
        } catch (Throwable $e) {
            report($e);
            $retryAfter = null;
        }

        return $retryAfter === null ? $next($request) : $this->tooManyRequests($request, $retryAfter);
    }

    private function tooManyRequests(Request $request, int $retryAfter): Response
    {
        $headers = ['Retry-After' => (string)max(1, $retryAfter)];
        $message = (string)__('aegis-smart-ip-blocker::smart-ip-blocker.blocked.message');

        if ($request->expectsJson()) {
            return new JsonResponse(['message' => $message], Response::HTTP_TOO_MANY_REQUESTS, $headers);
        }

        $view = $this->blocker->settings()->view;
        /** @var view-string $view */
        $view = $this->views->exists($view) ? $view : SmartIpBlockerSettings::DEFAULT_VIEW;

        return new IlluminateResponse(
            $this->views->make($view, ['retryAfter' => max(1, $retryAfter), 'message' => $message])->render(),
            Response::HTTP_TOO_MANY_REQUESTS,
            $headers,
        );
    }
}
