<?php

namespace App\Support;

/** Captures the same explicit props used by the Blade mount for this request only. */
class FrontendPagePayload
{
    public const ROUTES = [
        'dashboard' => ['page' => 'dashboard', 'flag' => 'dashboard'],
        'profile' => ['page' => 'profile', 'flag' => 'profile'],
        'edit-device' => ['page' => 'edit-device', 'flag' => 'device_edit'],
        'devices.show' => ['page' => 'view-device', 'flag' => 'view_device'],
    ];

    private string $page;
    private ?array $props = null;

    public function __construct(string $routeName)
    {
        $this->page = self::ROUTES[$routeName]['page'];
    }

    public static function workspaceEnabled(): bool
    {
        if (!config('frontend.vue3.workspace')) {
            return false;
        }
        foreach (self::ROUTES as $route) {
            if (!config('frontend.vue3.'.$route['flag'])) {
                return false;
            }
        }

        return true;
    }

    public static function supportsPath(string $path): bool
    {
        // Laravel also accepts aliases such as trailing slashes or zero-padded IDs.
        // Keep those URLs as document islands rather than mounting an unmatched router.
        return (bool) preg_match('#^/(?:dashboard|profile|(?:edit-device|devices)/[1-9][0-9]*)$#D', $path);
    }

    public static function modalEnabled(string $routeName, string $path): bool
    {
        return in_array($routeName, ['edit-device', 'devices.show'], true)
            && config('frontend.vue3.'.self::ROUTES[$routeName]['flag'])
            && self::supportsPath($path);
    }

    public static function record(string $page, array $props): bool
    {
        $capture = request()->attributes->get(self::class);
        if (!$capture instanceof self || $page !== $capture->page) {
            return false;
        }
        $capture->props = $props;

        return self::workspaceEnabled() && self::supportsPath(request()->getPathInfo());
    }

    public function envelope(string $url): ?array
    {
        return $this->props === null ? null : ['page' => $this->page, 'props' => $this->props, 'url' => $url];
    }
}
