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

namespace App\BootstrapTable;

use App\Repository\AbstractRepository;
use App\Repository\CalculationRepository;
use App\Repository\CalculationStateRepository;
use App\Service\ApplicationService;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Twig\Environment;

/**
 * The calculations table for margin below.
 *
 * @author Laurent Muller
 */
class CalculationBelowTable extends CalculationTable
{
    /**
     * The application service.
     */
    private ApplicationService $service;

    /**
     * Constructor.
     */
    public function __construct(CalculationRepository $repository, CalculationStateRepository $stateRepository, Environment $twig, ApplicationService $service)
    {
        parent::__construct($repository, $stateRepository, $twig);
        $this->service = $service;
    }

    /**
     * {@inheritdoc}
     */
    protected function count(): int
    {
        return $this->countFiltered($this->createDefaultQueryBuilder());
    }

    /**
     * {@inheritdoc}
     */
    protected function createDefaultQueryBuilder(string $alias = AbstractRepository::DEFAULT_ALIAS): QueryBuilder
    {
        $param = 'minMargin';
        $itemsField = "{$alias}.itemsTotal";
        $overallField = "{$alias}.overallTotal";
        $minMargin = $this->getMinMargin();

        return parent::createDefaultQueryBuilder($alias)
            ->andWhere("$itemsField != 0")
            ->andWhere("($overallField / $itemsField) < :$param")
            ->setParameter($param, $minMargin, Types::FLOAT);
    }

    /**
     * {@inheritdoc}
     */
    protected function updateParameters(array $parameters): array
    {
        // $parameters = parent::updateParameters($parameters);
        $parameters['min_margin'] = $this->getMinMargin();

        return $parameters;
    }

    /**
     * Gets the minimum margin, in percent, for a calculation.
     */
    private function getMinMargin(): float
    {
        return $this->service->getMinMargin();
    }
}
