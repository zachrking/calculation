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

namespace App\Repository;

use App\Entity\CalculationState;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * Repository for calculation state entity.
 *
 * @author Laurent Muller
 *
 * @see \App\Entity\CalculationState
 */
class CalculationStateRepository extends AbstractRepository
{
    /**
     * Constructor.
     *
     * @param ManagerRegistry $registry The connections and entity managers registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CalculationState::class);
    }

    /**
     * Gets states with the number and the sum of calculations.
     *
     * @return array a array with the state, the number and the sum of calculations
     */
    public function getByState(): array
    {
        $builder = $this->createQueryBuilder('s')
            ->select('s.id')
            ->addSelect('s.code')
            ->addSelect('s.editable')
            ->addSelect('s.color')
            ->addSelect('COUNT(c.id)         as count')
            ->addSelect('SUM(c.overallTotal) as total')
            ->innerJoin('s.calculations', 'c')
            ->groupBy('s.id')
            ->orderBy('s.editable', Criteria::DESC)
            ->addOrderBy('s.code');

        try {
            // update percent
            return $builder->getQuery()->getArrayResult();
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Gets the query builder for the list of states sorted by code.
     */
    public function getSortedBuilder(): QueryBuilder
    {
        $field = (string) $this->getSortFields('code');

        return $this->createQueryBuilder(self::DEFAULT_ALIAS)
            ->orderBy($field, Criteria::ASC);
    }
}
