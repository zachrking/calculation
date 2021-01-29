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
use App\Util\Utils;
use Doctrine\ORM\Query\Expr\Orx;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * Abstract table for entities.
 *
 * @author Laurent Muller
 */
abstract class AbstractEntityTable extends AbstractTable
{
    /**
     * The where part name of the query builder.
     */
    private const WHERE_PART = 'where';

    /**
     * The respository.
     */
    protected AbstractRepository $repository;

    /**
     * Constructor.
     */
    public function __construct(AbstractRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getEntityClassName(): string
    {
        return $this->repository->getClassName();
    }

    /**
     * Gets the repository.
     */
    public function getRepository(): AbstractRepository
    {
        return $this->repository;
    }

    /**
     * {@inheritdoc}
     */
    public function handleRequest(Request $request): array
    {
        // builder
        $builder = $this->createDefaultQueryBuilder();

        // count all
        $totalNotFiltered = $filtered = $this->count();

        // search
        $search = $this->addSearch($request, $builder);

        // count filtered
        if (!empty($builder->getDQLPart(self::WHERE_PART))) {
            $filtered = $this->countFiltered($builder);
        }

        // sort
        [$sort, $order] = $this->addOrderBy($request, $builder);

        // limit and page
        [$offset, $limit, $page] = $this->addLimit($request, $builder);

        // get result and map entities
        $entities = $builder->getQuery()->getResult();
        $rows = $this->mapEntities($entities);

        // ajax?
        if ($request->isXmlHttpRequest()) {
            return [
                'totalNotFiltered' => $totalNotFiltered,
                'total' => $filtered,
                'rows' => $rows,
            ];
        }

        // page list
        $pageList = $this->getPageList($totalNotFiltered);
        $limit = \min($limit, \max($pageList));

        // render
        return [
            'columns' => $this->getColumns(),
            'rows' => $rows,

            'card' => $this->getParamCard($request),
            'id' => $this->getParamId($request),

            'totalNotFiltered' => $totalNotFiltered,
            'total' => $filtered,

            'page' => $page,
            'limit' => $limit,
            'offset' => $offset,
            'search' => $search,
            'pageList' => $pageList,

            'sort' => $sort,
            'order' => $order,
        ];
    }

    /**
     * Add the limit and the maximum result to return.
     *
     * @param Request      $request the request
     * @param QueryBuilder $builder the query builder to update
     *
     * @return int[] the offset, the limit and the page parameters
     */
    protected function addLimit(Request $request, QueryBuilder $builder): array
    {
        $offset = (int) $request->get(self::PARAM_OFFSET, 0);
        $limit = (int) $this->getRequestValue($request, self::PARAM_LIMIT, self::PAGE_SIZE);
        $page = 1 + (int) \floor($this->safeDivide($offset, $limit));

        $builder->setFirstResult($offset)
            ->setMaxResults($limit);

        return [$offset, $limit, $page];
    }

    /**
     * Update the given query builder by adding the order by clause.
     *
     * @param Request      $request the request
     * @param QueryBuilder $builder the query builder to update
     *
     * @return string[] the sort field and order parameters
     */
    protected function addOrderBy(Request $request, QueryBuilder $builder): array
    {
        $orderBy = [];
        $sort = (string) $this->getRequestValue($request, self::PARAM_SORT, '');
        $order = (string) $this->getRequestValue($request, self::PARAM_ORDER, Column::SORT_ASC);

        if (Utils::isString($sort)) {
            $this->updateOrderBy($orderBy, $sort, $order);
        }

        // default column
        if (!Utils::isString($sort) && $column = $this->getDefaultColumn()) {
            $this->updateOrderBy($orderBy, $column->getField(), $column->getOrder());
        }

        // default order
        $defaultSort = $this->getDefaultOrder();
        foreach ($defaultSort as $defaultField => $defaultOrder) {
            $this->updateOrderBy($orderBy, $defaultField, $defaultOrder);
            if (!Utils::isString($sort)) {
                $sort = $defaultField;
            }
        }

        // apply sort
        foreach ($orderBy as $key => $value) {
            $builder->addOrderBy($key, $value);
        }

        return [$sort, \strtolower($order)];
    }

    /**
     * Adds the search clause, if applicable.
     *
     * @param Request      $request the request
     * @param QueryBuilder $builder the query builder to update
     *
     * @return string the seeach parameter
     */
    protected function addSearch(Request $request, QueryBuilder $builder): string
    {
        $search = (string) $request->get(self::PARAM_SEARCH, '');
        if (Utils::isString($search)) {
            $expr = new Orx();
            $columns = $this->getColumns();
            $repository = $this->repository;
            foreach ($columns as $column) {
                if ($column->isSearchable()) {
                    $fields = (array) $repository->getSearchFields($column->getField());
                    foreach ($fields as $field) {
                        $expr->add($field . ' LIKE :' . self::PARAM_SEARCH);
                    }
                }
            }
            if ($expr->count()) {
                $builder->andWhere($expr)
                    ->setParameter(self::PARAM_SEARCH, "%{$search}%");
            }
        }

        return $search;
    }

    /**
     * Gets the total number of unfiltered entities.
     */
    protected function count(): int
    {
        return $this->repository->count([]);
    }

    /**
     * Count the number of filtered entities.
     *
     * @param QueryBuilder $builder the source builder
     */
    protected function countFiltered(QueryBuilder $builder): int
    {
        $alias = $builder->getRootAliases()[0];
        $field = $this->repository->getSingleIdentifierFieldName();
        $select = "COUNT($alias.$field)";
        $cloned = (clone $builder)->select($select);

        return (int) $cloned->getQuery()->getSingleScalarResult();
    }

    /**
     * Creates a default query builder.
     *
     * @param string $alias the entity alias
     */
    protected function createDefaultQueryBuilder(string $alias = AbstractRepository::DEFAULT_ALIAS): QueryBuilder
    {
        return $this->repository->createDefaultQueryBuilder($alias);
    }

    /**
     * Gets the default order to apply.
     *
     * @return array an array where each key is the column name and the value is the order direction ('asc' or 'desc')
     */
    protected function getDefaultOrder(): array
    {
        return [];
    }

    /**
     * Update the order by clause.
     *
     * @param array  $orderBy    the order by clause to update
     * @param string $orderField the order field to add
     * @param string $orderSort  the order direction to add
     */
    private function updateOrderBy(array &$orderBy, string $orderField, string $orderSort): void
    {
        $fields = (array) $this->repository->getSortFields($orderField);
        foreach ($fields as $field) {
            if (!\array_key_exists($field, $orderBy)) {
                $orderBy[$field] = $orderSort;
            }
        }
    }
}