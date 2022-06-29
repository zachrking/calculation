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

use App\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Trait for session functions.
 */
trait SessionTrait
{
    /**
     * The request stack used to get session.
     */
    protected ?RequestStack $requestStack = null;

    /**
     * The session instance.
     */
    protected ?SessionInterface $session = null;

    /**
     * Sets the request stack.
     */
    public function setRequestStack(RequestStack $requestStack): void
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Gets the session.
     *
     * @return SessionInterface|null the session, if found; null otherwise
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getSession(): ?SessionInterface
    {
        if (null === $this->session) {
            if (null === $this->requestStack && $this instanceof AbstractController) {
                $this->requestStack = $this->getRequestStack();
            }
            if (null !== $this->requestStack) {
                $this->session = $this->requestStack->getSession();
            }
        }

        return $this->session;
    }

    /**
     * Gets a session attribute, as float value.
     *
     * @param string     $key     the attribute name
     * @param float|null $default the default value if not found
     *
     * @return float|null the session value, if found; the default value otherwise
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getSessionFloat(string $key, ?float $default): ?float
    {
        return (float) $this->getSessionValue($key, $default);
    }

    /**
     * Gets a session attribute, as integer value.
     *
     * @param string   $key     the attribute name
     * @param int|null $default the default value if not found
     *
     * @return int|null the session value, if found; the default value otherwise
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getSessionInt(string $key, ?int $default): ?int
    {
        return (int) $this->getSessionValue($key, $default);
    }

    /**
     * Gets the attribute name used to manipulate session attributes.
     *
     * The default implementation returns the key argument. Class can override
     * to use, for example, a prefix or a suffix.
     *
     * @param string $key the attribute name
     */
    protected function getSessionKey(string $key): string
    {
        return $key;
    }

    /**
     * Gets a session attribute, as string value.
     *
     * @param string      $key     the attribute name
     * @param string|null $default the default value if not found
     *
     * @return string|null the session value, if found; the default value otherwise
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getSessionString(string $key, ?string $default = null): ?string
    {
        return (string) $this->getSessionValue($key, $default);
    }

    /**
     * Gets a session attribute.
     *
     * @param string $key     the attribute name
     * @param mixed  $default the default value if not found
     *
     * @return mixed the session value, if found; the default value otherwise
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function getSessionValue(string $key, mixed $default = null): mixed
    {
        if ($session = $this->getSession()) {
            $sessionKey = $this->getSessionKey($key);

            return $session->get($sessionKey, $default);
        }

        return $default;
    }

    /**
     * Checks if an attribute is defined.
     *
     * @param string $key the attribute name
     *
     * @return bool true if the attribute is defined, false otherwise
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function hasSessionValue(string $key): bool
    {
        if ($session = $this->getSession()) {
            $sessionKey = $this->getSessionKey($key);

            return $session->has($sessionKey);
        }

        return false;
    }

    /**
     * Gets a session attribute, as boolean value.
     *
     * @param string $key     the attribute name
     * @param bool   $default the default value if not found
     *
     * @return bool the session value, if found; the default value otherwise
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function isSessionBool(string $key, bool $default = false): bool
    {
        return (bool) $this->getSessionValue($key, $default);
    }

    /**
     * Removes a session attribute.
     *
     * @param string $key the attribute name
     *
     * @return mixed the removed value or null when attribute does not exist
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function removeSessionValue(string $key): mixed
    {
        if ($session = $this->getSession()) {
            $sessionKey = $this->getSessionKey($key);

            return $session->remove($sessionKey);
        }

        return null;
    }

    /**
     * Removes session attributes.
     *
     * @param string[] $keys the attribute names to remove
     *
     * @return array the removed values
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function removeSessionValues(array $keys): array
    {
        return \array_map(fn (string $key): mixed => $this->removeSessionValue($key), $keys);
    }

    /**
     * Sets this session within the given request.
     *
     * @return bool true if the session is set; false otherwise
     */
    protected function setSessionFromRequest(Request $request): bool
    {
        if ($request->hasSession()) {
            $this->session = $request->getSession();

            return true;
        }

        return false;
    }

    /**
     * Sets a session attribute.
     *
     * @param string $key   the attribute name
     * @param mixed  $value the attribute value or null to remove
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function setSessionValue(string $key, mixed $value): static
    {
        if ($session = $this->getSession()) {
            $sessionKey = $this->getSessionKey($key);
            if (null === $value) {
                $session->remove($key);
            } else {
                $session->set($sessionKey, $value);
            }
        }

        return $this;
    }

    /**
     * Sets session attributes.
     *
     * @param array<string, mixed> $attributes the keys and values to save
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function setSessionValues(array $attributes): static
    {
        /** @psalm-var mixed $value */
        foreach ($attributes as $key => $value) {
            $this->setSessionValue($key, $value);
        }

        return $this;
    }
}
