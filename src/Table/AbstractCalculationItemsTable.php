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

namespace App\Table;

use App\Repository\CalculationRepository;
use App\Util\FileUtils;
use Doctrine\Common\Collections\Criteria;

/**
 * Abstract Calculation table to display items.
 */
abstract class AbstractCalculationItemsTable extends AbstractTable implements \Countable
{
    /**
     * Constructor.
     */
    public function __construct(protected readonly CalculationRepository $repository)
    {
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityClassName(): ?string
    {
        return $this->repository->getClassName();
    }

    /**
     * Gets the repository.
     */
    public function getRepository(): CalculationRepository
    {
        return $this->repository;
    }

    /**
     * Formats the invalid calculation items.
     *
     * @param array $items the invalid calculation items
     *
     * @return string the formatted items
     *
     * @psalm-param array<array{
     *     description: string,
     *     quantity: float,
     *     price: float,
     *     count: int}> $items
     */
    abstract protected function formatItems(array $items): string;

    /**
     * {@inheritdoc}
     */
    protected function getColumnDefinitions(): string
    {
        return FileUtils::buildPath(__DIR__, 'Definition', 'calculation_items.json');
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOrder(): array
    {
        return ['id' => self::SORT_DESC];
    }

    /**
     * Gets the entities.
     *
     * @param string $orderColumn    the order column
     * @param string $orderDirection the order direction ('ASC' or 'DESC')
     *
     * @psalm-return array<int, array{
     *      id: int,
     *      date: \DateTimeInterface,
     *      stateCode: string,
     *      customer: string,
     *      description: string,
     *      items: array<array{
     *          description: string,
     *          quantity: float,
     *          price: float,
     *          count: int}>
     *      }>
     */
    abstract protected function getEntities(string $orderColumn = 'id', string $orderDirection = Criteria::DESC): array;

    /**
     * Compute the number of calculation items.
     *
     * @param array $items the invalid calculation items
     *
     * @psalm-param array<int, array{
     *      id: int,
     *      date: \DateTimeInterface,
     *      stateCode: string,
     *      customer: string,
     *      description: string,
     *      items: array<array{
     *          description: string,
     *          quantity: float,
     *          price: float,
     *          count: int}>
     *      }> $items
     */
    abstract protected function getItemsCount(array $items): int;

    /**
     * {@inheritDoc}
     */
    protected function handleQuery(DataQuery $query): DataResults
    {
        $results = parent::handleQuery($query);

        // find entities
        $entities = $this->getEntities($query->sort, $query->order);
        $results->totalNotFiltered = $results->filtered = \count($entities);

        // limit and and map entities
        $entities = \array_slice($entities, $query->offset, $query->limit);
        $results->rows = $this->mapEntities($entities);

        // ajax?
        if (!$query->callback) {
            $results->customData = [
                'itemsCount' => $this->getItemsCount($entities),
                'allow_search' => false,
            ];
        }

        return $results;
    }

    /**
     * {@inheritDoc}
     */
    protected function updateResults(DataQuery $query, DataResults &$results): void
    {
        parent::updateResults($query, $results);
        if (!$query->callback) {
            $results->addAttribute('row-style', 'styleTextMuted');
        }
    }
}
