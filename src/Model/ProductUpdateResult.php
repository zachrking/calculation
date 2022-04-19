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

namespace App\Model;

/**
 * Contains result of updated products.
 *
 * @author Laurent Muller
 */
class ProductUpdateResult implements \Countable
{
    /**
     * @var array<array<string, mixed>>
     */
    private array $products = [];

    /**
     * Add a product to the list of updated products.
     *
     * @param array<string, mixed> $values
     */
    public function addProduct(array $values): self
    {
        $this->products[] = $values;

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return \count($this->products);
    }

    /**
     * Gets the updated products.
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * Returns if the update is valid.
     */
    public function isValid(): bool
    {
        return !empty($this->products);
    }
}
