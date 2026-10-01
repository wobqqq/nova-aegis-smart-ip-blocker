<?php

declare(strict_types=1);

namespace Wobqqq\AegisSmartIpBlocker;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Wobqqq\Aegis\Checks\CheckResult;
use Wobqqq\Aegis\Contracts\Module;
use Wobqqq\Aegis\Settings\Field;
use Wobqqq\AegisSmartIpBlocker\Rules\CoversCurrentIp;
use Wobqqq\AegisSmartIpBlocker\Rules\IpOrSubnet;
use Wobqqq\AegisSmartIpBlocker\Support\IpRange;

final readonly class SmartIpBlockerModule implements Module
{
    public const KEY = 'smart-ip-blocker';

    /**
     * The core's route that hands the settings form its values.
     */
    private const SETTINGS_ROUTE = 'nova.aegis.settings';

    public function __construct(private Container $container)
    {
    }

    public function key(): string
    {
        return self::KEY;
    }

    public function label(): string
    {
        return (string)__('aegis-smart-ip-blocker::smart-ip-blocker.label');
    }

    public function description(): string
    {
        return (string)__('aegis-smart-ip-blocker::smart-ip-blocker.description');
    }

    public function defaults(): array
    {
        return [
            'enabled' => false,
            'requests_per_minute' => 100,
            'ban_hours' => 1,
            'view' => SmartIpBlockerSettings::DEFAULT_VIEW,
            'max_tracked_ips' => 0,
            'excluded_ips' => $this->presetExcludedIps(),
            'excluded_headers' => [],
        ];
    }

    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:10000'],
            'ban_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'view' => ['required', 'string', 'max:100', 'regex:' . SmartIpBlockerSettings::VIEW_PATTERN, $this->viewExists()],
            'max_tracked_ips' => ['required', 'integer', 'min:0', 'max:10000'],
            'excluded_ips' => ['present', 'array', 'max:150', new CoversCurrentIp($this->request())],
            'excluded_ips.*' => ['array'],
            'excluded_ips.*.ip' => ['nullable', 'string', 'max:100', new IpOrSubnet()],
            'excluded_headers' => ['present', 'array', 'max:150'],
            'excluded_headers.*' => ['array'],
            'excluded_headers.*.header' => ['nullable', 'required_with:excluded_headers.*.value', 'string', 'regex:' . SmartIpBlockerSettings::HEADER_PATTERN],
            'excluded_headers.*.value' => ['nullable', 'required_with:excluded_headers.*.header', 'string', 'max:255'],
        ];
    }

    public function fields(): array
    {
        return [
            Field::toggle('enabled', $this->field('enabled'), $this->help('enabled')),
            Field::number('requests_per_minute', $this->field('requests_per_minute'), $this->help('requests_per_minute')),
            Field::number('ban_hours', $this->field('ban_hours'), $this->help('ban_hours')),
            Field::text('view', $this->field('view'), $this->help('view'), SmartIpBlockerSettings::DEFAULT_VIEW),
            Field::number('max_tracked_ips', $this->field('max_tracked_ips'), $this->help('max_tracked_ips')),
            Field::table('excluded_ips', $this->field('excluded_ips'), [
                Field::text('ip', $this->field('ip'), placeholder: '203.0.113.10 / 10.0.0.0/8'),
            ], $this->help('excluded_ips', ['ip' => (string)$this->request()->ip()])),
            Field::table('excluded_headers', $this->field('excluded_headers'), [
                Field::text('header', $this->field('header'), placeholder: 'User-Agent'),
                Field::text('value', $this->field('value'), placeholder: 'Googlebot'),
            ], $this->help('excluded_headers')),
        ];
    }

    public function status(array $values): CheckResult
    {
        $settings = SmartIpBlockerSettings::fromArray($values);
        $label = $this->label();

        return $settings->enabled
            ? CheckResult::pass(self::KEY, $label, (string)__('aegis-smart-ip-blocker::smart-ip-blocker.on', [
                'requests' => $settings->requestsPerMinute,
                'hours' => $settings->banHours,
            ]))
            : CheckResult::warn(self::KEY, $label, (string)__('aegis-smart-ip-blocker::smart-ip-blocker.off'));
    }

    private function field(string $name): string
    {
        return (string)__('aegis-smart-ip-blocker::smart-ip-blocker.fields.' . $name);
    }

    /**
     * @param array<string, string> $replace
     */
    private function help(string $name, array $replace = []): string
    {
        return (string)__('aegis-smart-ip-blocker::smart-ip-blocker.help.' . $name, $replace);
    }

    /**
     * The administrator who opens the form for the first time finds their own IP excluded already.
     *
     * @return list<array{ip: string}>
     */
    private function presetExcludedIps(): array
    {
        $request = $this->request();
        $ip = IpRange::normalize((string)$request->ip());

        return $ip !== null && $request->routeIs(self::SETTINGS_ROUTE) ? [['ip' => $ip]] : [];
    }

    private function request(): Request
    {
        return $this->container->make(Request::class);
    }

    private function viewExists(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (!is_string($value) || !View::exists($value)) {
                $fail((string)__('aegis-smart-ip-blocker::smart-ip-blocker.validation.view'));
            }
        };
    }
}
