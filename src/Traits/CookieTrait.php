<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Traits;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trait to manage cookies.
 */
trait CookieTrait
{
    /**
     * Clears a cookie in the browser.
     */
    protected function clearCookie(Response $response, string $key, string $prefix = '', string $path = '/'): void
    {
        $name = $this->getCookieName($key, $prefix);
        $response->headers->clearCookie($name, $path);
    }

    protected function getCookieBoolean(Request $request, string $key, string $prefix = '', bool $default = false): bool
    {
        $name = $this->getCookieName($key, $prefix);

        return $request->cookies->getBoolean($name, $default);
    }

    protected function getCookieFloat(Request $request, string $key, string $prefix = '', float $default = 0): float
    {
        $name = $this->getCookieName($key, $prefix);

        return (float) $request->cookies->get($name, (string) $default);
    }

    protected function getCookieInt(Request $request, string $key, string $prefix = '', int|\BackedEnum $default = 0): int
    {
        $name = $this->getCookieName($key, $prefix);
        if ($default instanceof \BackedEnum) {
            $default = (int) $default->value;
        }

        return $request->cookies->getInt($name, $default);
    }

    /**
     * Gets the cookie name.
     */
    protected function getCookieName(string $key, string $prefix = ''): string
    {
        return '' === $prefix ? \strtoupper($key) : \strtoupper($prefix . '_' . $key);
    }

    protected function getCookieString(Request $request, string $key, string $prefix = '', string|\BackedEnum $default = null): string|null
    {
        $name = $this->getCookieName($key, $prefix);
        if ($default instanceof \BackedEnum) {
            $default = (string) $default->value;
        }

        return $request->cookies->get($name, $default);
    }

    /**
     * Sets a cookie in the browser.
     */
    protected function setCookie(Response $response, string $key, mixed $value, string $prefix = '', string $path = '/', string $modify = '+1 year'): void
    {
        $name = $this->getCookieName($key, $prefix);
        $expire = (new \DateTime())->modify($modify);
        $cookie = new Cookie($name, (string) $value, $expire, $path);
        $response->headers->setCookie($cookie);
    }

    /**
     * Add or remove a cookie depending on the value. If value is null or empty ('') the cookie is removed.
     */
    protected function updateCookie(Response $response, string $key, mixed $value, string $prefix = '', string $path = '/', string $modify = '+1 year'): void
    {
        if (null === $value || '' === (string) $value) {
            $this->clearCookie($response, $key, $prefix, $path);
        } else {
            $this->setCookie($response, $key, $value, $prefix, $path, $modify);
        }
    }
}
