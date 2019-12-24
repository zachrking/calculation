<?php
/*
 * This file is part of the Calculation package.
 *
 * Copyright (c) 2019 bibi.nu. All rights reserved.
 *
 * This computer code is protected by copyright law and international
 * treaties. Unauthorised reproduction or distribution of this code, or
 * any portion of it, may result in severe civil and criminal penalties,
 * and will be prosecuted to the maximum extent possible under the law.
 */

declare(strict_types=1);

namespace App\Report;

use App\Controller\BaseController;

/**
 * Report for calculations with duplicate items.
 *
 * @author Laurent Muller
 */
class CalculationDuplicateReport extends CalculationItemsReports
{
    /**
     * Constructor.
     *
     * @param BaseController $controller the parent controller
     */
    public function __construct(BaseController $controller)
    {
        parent::__construct($controller, 'duplicate.title', 'duplicate.description');
    }

    /**
     * {@inheritdoc}
     */
    protected function countItems(array $calculations): int
    {
        return  \array_reduce($this->calculations, function (int $carry, array $calculation) {
            foreach ($calculation['items'] as $item) {
                $carry += $item['count'];
            }

            return $carry;
        }, 0);
    }

    /**
     * {@inheritdoc}
     */
    protected function formatItem(array $item): string
    {
        $count = $this->localeInt($item['count']);

        return \sprintf('%s (%s)', $item['description'], $count);
    }

    /**
     * {@inheritdoc}
     */
    protected function transCount(array $parameters)
    {
        return $this->trans('duplicate.count', $parameters);
    }
}
