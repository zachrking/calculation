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

namespace App\Listener;

use App\Entity\Calculation;
use App\Interfaces\ParentCalculationInterface;
use Doctrine\ORM\UnitOfWork;

/**
 * Listener to update calculations when groups, categories or items are modified.
 *
 * @author Laurent Muller
 */
final class CalculationListener extends TimestampableListener
{
    /**
     * {@inheritDoc}
     */
    protected function getEntities(UnitOfWork $unitOfWork): array
    {
        /** @var array $entities */
        $entities = [
            ...$unitOfWork->getScheduledEntityUpdates(),
            ...$unitOfWork->getScheduledEntityDeletions(),
            ...$unitOfWork->getScheduledEntityInsertions(),
        ];

        if ([] === $entities) {
            return [];
        }

        $result = [];
        foreach ($entities as $entity) {
            if (null !== $calculation = $this->getParentCalculation($entity)) {
                $result[(int) $calculation->getId()] = $calculation;
            }
        }

        if ([] === $result) {
            return $result;
        }

        // exclude deleted and inserted calculations
        $deleted = $this->getCalculations($unitOfWork->getScheduledEntityDeletions());
        $inserted = $this->getCalculations($unitOfWork->getScheduledEntityInsertions());

        return \array_diff($result, $deleted, $inserted);
    }

    /**
     * @return Calculation[]
     */
    private function getCalculations(array $entities): array
    {
        // @phpstan-ignore-next-line
        return \array_filter($entities, static function ($entity): bool {
            return $entity instanceof Calculation;
        });
    }

    /**
     * @param mixed $entity
     */
    private function getParentCalculation($entity): ?Calculation
    {
        if ($entity instanceof ParentCalculationInterface) {
            return $entity->getCalculation();
        }

        return null;
    }
}
