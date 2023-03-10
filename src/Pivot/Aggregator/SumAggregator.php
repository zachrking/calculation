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

namespace App\Pivot\Aggregator;

/**
 * Aggregator to sum values.
 */
class SumAggregator extends AbstractAggregator
{
    protected float $result = 0.0;

    /**
     * {@inheritdoc}
     */
    public function add($value): static
    {
        if ($value instanceof self) {
            $this->result += $value->result;
        } elseif (null !== $value) {
            $this->result += (float) $value;
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getFormattedResult(): float
    {
        return \round($this->getResult(), 2);
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(): float
    {
        return $this->result;
    }

    /**
     * {@inheritdoc}
     */
    public function init(): static
    {
        $this->result = 0;

        return $this;
    }
}
