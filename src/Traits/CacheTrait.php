<?php
/*
 * This file is part of the Calculation package.
 *
 * (c) bibi.nu. <bibi@bibi.nu>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Traits;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Trait to save or load data from a cache.
 *
 * @author Laurent Muller
 */
trait CacheTrait
{
    /**
     * The cache adapter.
     */
    protected ?CacheItemPoolInterface $adapter = null;

    /**
     * Remove all reserved characters that cannot be used in a key.
     *
     * @param string $key the key to clean
     *
     * @return string a valid key
     */
    public function cleanKey(string $key): string
    {
        /** @var string[]|null $reservedCharacters */
        static $reservedCharacters;
        if (!$reservedCharacters) {
            $reservedCharacters = \str_split(ItemInterface::RESERVED_CHARACTERS);
        }

        return \str_replace($reservedCharacters, '_', $key);
    }

    /**
     * Removes the item from the cache pool.
     *
     * @param string $key The key to delete
     *
     * @throws \InvalidArgumentException If the $key string is not a legal value
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     */
    public function deleteCacheItem(string $key): bool
    {
        if (null !== $this->adapter) {
            return $this->adapter->deleteItem($this->cleanKey($key));
        }

        return false;
    }

    /**
     * Removes multiple items from the cache pool.
     *
     * @param string[] $keys An array of keys that should be removed from the pool
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \InvalidArgumentException If any of the keys in $keys are not a legal value
     */
    public function deleteCacheItems(array $keys): bool
    {
        if (null !== $this->adapter) {
            $keys = \array_map(function (string $key) {
                return $this->cleanKey($key);
            }, $keys);

            return $this->adapter->deleteItems($keys);
        }

        return false;
    }

    /**
     * Gets the cache item for the given key.
     *
     * @param string $key the key for which to return the corresponding item
     *
     * @return CacheItemInterface|null the cache item, if found; null otherwise
     *
     * @throws \InvalidArgumentException If the $key string is not a legal value
     */
    public function getCacheItem(string $key): ?CacheItemInterface
    {
        if (null !== $this->adapter) {
            return $this->adapter->getItem($this->cleanKey($key));
        }

        return null;
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys An indexed array of keys of items to retrieve
     *
     * @return array|iterable A traversable collection of Cache Items keyed by the cache keys of
     *                        each item. A Cache item will be returned for each key, even if that
     *                        key is not found.
     *
     * @throws \InvalidArgumentException If any of the keys in $keys are not a legal value
     */
    public function getCacheItems(array $keys)
    {
        if (null !== $this->adapter) {
            $keys = \array_map(function (string $key) {
                return $this->cleanKey($key);
            }, $keys);

            return $this->adapter->getItems($keys);
        }

        return []; // @phpstan-ignore-line
    }

    /**
     * Gets the value from this cache for the given key.
     *
     * @param string $key     The key for which to return the corresponding value
     * @param mixed  $default The default value to return or a callable function to get the default value.
     *                        If the callable function returns a value, this value is saved to the cache.
     *
     * @return mixed the value, if found; the default otherwise
     */
    public function getCacheValue(string $key, mixed $default = null): mixed
    {
        // clean key
        $key = $this->cleanKey($key);

        if (($item = $this->getCacheItem($key)) && $item->isHit()) {
            return $item->get();
        }

        if (\is_callable($default)) {
            $value = \call_user_func($default);
            if (null !== $value) {
                $this->setCacheValue($key, $value);

                return $value;
            }

            return null;
        }

        return $default;
    }

    /**
     * Sets the adapter.
     */
    public function setAdapter(CacheItemPoolInterface $adapter): void
    {
        $this->adapter = $adapter;
    }

    /**
     * Save the given value to the cache.
     *
     * @param string                 $key   The key for which to save the value
     * @param mixed                  $value The value to save. If null, the key item is removed from the cache.
     * @param int|\DateInterval|null $time  The period of time from the present after which the item must be considered
     *                                      expired. An integer parameter is understood to be the time in seconds until
     *                                      expiration. If null is passed, a default value (60 minutes) is used.
     */
    public function setCacheValue(string $key, mixed $value, int|\DateInterval|null $time = null): self
    {
        // clean key
        $key = $this->cleanKey($key);

        // value?
        if (null === $value) {
            $this->deleteCacheItem($key);
        } elseif ($this->adapter && $item = $this->getCacheItem($key)) {
            // save
            $item->expiresAfter($time ?? 3600)
                ->set($value);
            $this->adapter->save($item);
        }

        return $this;
    }
}
