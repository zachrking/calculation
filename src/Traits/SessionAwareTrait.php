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

use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Contracts\Service\Attribute\SubscribedService;

/**
 * Trait to get or set values within session.
 */
trait SessionAwareTrait
{
    private ?RequestStack $requestStack = null;

    #[SubscribedService]
    public function getRequestStack(): RequestStack
    {
        if (null === $this->requestStack) {
            /** @phpstan-var RequestStack $result */
            $result = $this->container->get(__CLASS__ . '::' . __FUNCTION__);
            $this->requestStack = $result;
        }

        return $this->requestStack;
    }

    public function setRequestStack(RequestStack $requestStack): static
    {
        $this->requestStack = $requestStack;

        return $this;
    }

    /**
     * Gets the session.
     */
    protected function getSession(): ?SessionInterface
    {
        try {
            return $this->getRequestStack()->getSession();
        } catch (SessionNotFoundException) {
            return null;
        }
    }

    /**
     * Gets a session attribute, as date value.
     *
     * @param string                  $key     the attribute name
     * @param \DateTimeInterface|null $default the default value if not found
     *
     * @return \DateTimeInterface|null the session value, if found; the default value otherwise
     */
    protected function getSessionDate(string $key, \DateTimeInterface $default = null): ?\DateTimeInterface
    {
        /** @var int|\DateTimeInterface|null $value */
        $value = $this->getSessionValue($key, $default);
        if (\is_int($value)) {
            return (new \DateTime())->setTimestamp($value);
        }

        return $value;
    }

    /**
     * Gets a session attribute, as float value.
     *
     * @param string $key     the attribute name
     * @param ?float $default the default value if not found
     *
     * @return float|null the session value, if found; the default value otherwise
     *
     * @psalm-return ($default is null ? (float|null) : float)
     */
    protected function getSessionFloat(string $key, ?float $default): ?float
    {
        return (float) $this->getSessionValue($key, $default);
    }

    /**
     * Gets a session attribute, as integer value.
     *
     * @param string $key     the attribute name
     * @param ?int   $default the default value if not found
     *
     * @return int|null the session value, if found; the default value otherwise
     *
     * @psalm-return ($default is null ? (int|null) : int)
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
     * @param string  $key     the attribute name
     * @param ?string $default the default value if not found
     *
     * @return string|null the session value, if found; the default value otherwise
     *
     * @psalm-return ($default is null ? (string|null) : string)
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
     */
    protected function getSessionValue(string $key, mixed $default = null): mixed
    {
        return $this->getSession()?->get($this->getSessionKey($key), $default) ?? $default;
    }

    /**
     * Checks if an attribute is defined.
     *
     * @param string $key the attribute name
     *
     * @return bool true if the attribute is defined, false otherwise
     */
    protected function hasSessionValue(string $key): bool
    {
        return $this->getSession()?->has($this->getSessionKey($key)) ?? false;
    }

    /**
     * Gets a session attribute, as boolean value.
     *
     * @param string $key     the attribute name
     * @param bool   $default the default value if not found
     *
     * @return bool the session value, if found; the default value otherwise
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
     */
    protected function removeSessionValue(string $key): mixed
    {
        return $this->getSession()?->remove($this->getSessionKey($key)) ?? null;
    }

    /**
     * Removes session attributes.
     *
     * @param string ...$keys the attribute names to remove
     *
     * @return array the removed values
     */
    protected function removeSessionValues(string ...$keys): array
    {
        return \array_map($this->removeSessionValue(...), $keys);
    }

    /**
     * Sets a session attribute.
     *
     * @param string $key   the attribute name
     * @param mixed  $value the attribute value or null to remove
     */
    protected function setSessionValue(string $key, mixed $value): static
    {
        if (null === $value) {
            $this->removeSessionValue($key);
        } else {
            $this->getSession()?->set($this->getSessionKey($key), $value);
        }

        return $this;
    }

    /**
     * Sets session attributes (values).
     *
     * @param array<string, mixed> $attributes the keys and values to save
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
