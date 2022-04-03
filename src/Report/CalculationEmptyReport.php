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

namespace App\Report;

use App\Controller\AbstractController;
use App\Traits\MathTrait;

/**
 * Report for calculations with empty items.
 *
 * @author Laurent Muller
 */
class CalculationEmptyReport extends AbstractCalculationItemsReport
{
    use MathTrait;

    /**
     * The price label.
     */
    private string $priceLabel;

    /**
     * The quantity label.
     */
    private string $quantityLabel;

    /**
     * Constructor.
     *
     * @param AbstractController $controller the parent controller
     * @param array              $items      the items to render
     */
    public function __construct(AbstractController $controller, array $items)
    {
        parent::__construct($controller, $items, 'empty.title', 'empty.description');
        $this->priceLabel = $this->trans('calculationitem.fields.price');
        $this->quantityLabel = $this->trans('calculationitem.fields.quantity');
    }

    /**
     * {@inheritdoc}
     */
    protected function computeItemsCount(array $items): int
    {
        return \array_reduce($items, fn (int $carry, array $item) => $carry + \count((array) $item['items']), 0);
    }

    /**
     * {@inheritdoc}
     */
    protected function formatItems(array $items): string
    {
        $result = \array_map(function (array $item): string {
            $founds = [];
            if ($this->isFloatZero((float) $item['price'])) {
                $founds[] = $this->priceLabel;
            }
            if ($this->isFloatZero((float) $item['quantity'])) {
                $founds[] = $this->quantityLabel;
            }

            return \sprintf('%s (%s)', (string) $item['description'], \implode(', ', $founds));
        }, $items);

        return \implode("\n", $result);
    }

    /**
     * {@inheritdoc}
     */
    protected function transCount(array $parameters): string
    {
        return $this->trans('empty.count', $parameters);
    }
}
