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

use App\Interfaces\TableInterface;
use App\Traits\MathTrait;
use App\Util\FormatUtils;
use App\Util\Utils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;

/**
 * The abstract table.
 *
 * @author Laurent Muller
 */
abstract class AbstractTable
{
    use MathTrait;

    /**
     * The column definitions.
     *
     * @var Column[]
     */
    protected ?array $columns = null;

    /**
     * The session prefix.
     */
    private ?string $prefix = null;

    public function formatAmount(float $value): string
    {
        return FormatUtils::formatAmount($value);
    }

    public function formatCountable(\Countable $value): string
    {
        return $this->formatInt($value->count());
    }

    public function formatDate(\DateTimeInterface $value): string
    {
        return FormatUtils::formatDate($value);
    }

    public function formatId(int $value): string
    {
        return FormatUtils::formatId($value);
    }

    public function formatInt(int $value): string
    {
        return FormatUtils::formatInt($value);
    }

    public function formatPercent(float $value): string
    {
        return FormatUtils::formatPercent($value);
    }

    /**
     * Gets the column definitions.
     *
     * @return Column[]
     */
    public function getColumns(): array
    {
        if (null === $this->columns) {
            $this->columns = $this->createColumns();
        }

        return $this->columns;
    }

    /**
     * Gets the data query from the given request.
     */
    public function getDataQuery(Request $request): DataQuery
    {
        $query = new DataQuery();

        // offset, limit and page
        $query->offset = (int) $request->get(TableInterface::PARAM_OFFSET, 0);
        $query->limit = (int) $this->getRequestValue($request, TableInterface::PARAM_LIMIT, TableInterface::PAGE_SIZE);
        $query->page = 1 + (int) \floor($this->safeDivide($query->offset, $query->limit));

        // sort and order
        if ($column = $this->getDefaultColumn()) {
            $query->sort = $column->getField();
            $query->order = $column->getOrder();
        }
        $query->sort = (string) $this->getRequestValue($request, TableInterface::PARAM_SORT, $query->sort);
        $query->order = (string) $this->getRequestValue($request, TableInterface::PARAM_ORDER, $query->order);

        // other parameters
        $query->id = $this->getParamId($request);
        $query->card = $this->getParamCard($request);
        $query->search = (string) $request->get(TableInterface::PARAM_SEARCH, '');
        $query->callback = $request->isXmlHttpRequest();

        return $query;
    }

    /**
     * Gets the entity class name or null if not applicable.
     */
    abstract public function getEntityClassName(): ?string;

    /**
     * Process the given query and returns the results.
     *
     * @param DataQuery $query the query to handle
     *
     * @return DataResults the results
     */
    public function processQuery(DataQuery $query): DataResults
    {
        $results = $this->handleQuery($query);
        $this->updateResults($query, $results);

        return $results;
    }

    /**
     * Save the request parameter value to the session.
     *
     * @param Request $request the request to get value from
     * @param string  $name    the parameter name
     * @param mixed   $default the default value if not found
     *
     * @return bool true if the parameter value is saved to the session; false otherwise
     */
    public function saveRequestValue(Request $request, string $name, $default = null): bool
    {
        if ($request->hasSession()) {
            $session = $request->getSession();
            $key = $this->getSessionKey($name);
            $default = $session->get($key, $default);
            $value = $request->get($name, $default);
            if (null === $value) {
                $session->remove($key);
            } else {
                $session->set($key, $value);
            }

            return true;
        }

        return false;
    }

    /**
     * Create the columns.
     *
     * @return Column[] the columns
     */
    protected function createColumns(): array
    {
        $path = $this->getColumnDefinitions();

        return Column::fromJson($this, $path);
    }

    /**
     * Gets the allowed page list.
     *
     * @param int $totalNotFiltered the number of not filtered entities
     *
     * @return int[] the allowed page list
     */
    protected function getAllowedPageList(int $totalNotFiltered): array
    {
        $sizes = TableInterface::PAGE_LIST;
        for ($i = 0, $count = \count($sizes); $i < $count; ++$i) {
            if ($sizes[$i] >= $totalNotFiltered) {
                return \array_slice($sizes, 0, $i + 1);
            }
        }

        return $sizes;
    }

    /**
     * Gets the JSON file containing the column definitions.
     */
    abstract protected function getColumnDefinitions(): string;

    /**
     * Gets the default sorting column.
     */
    protected function getDefaultColumn(): ?Column
    {
        $columns = $this->getColumns();
        foreach ($columns as $column) {
            if ($column->isDefault()) {
                return $column;
            }
        }
        foreach ($columns as $column) {
            if ($column->isVisible()) {
                return $column;
            }
        }

        return null;
    }

    /**
     * Gets the default order to apply.
     *
     * @return array an array where each key is the field name and the value is the order direction ('asc' or 'desc')
     */
    protected function getDefaultOrder(): array
    {
        return [];
    }

    /**
     * Gets the request parameter value.
     *
     * @param Request $request the request to get value from
     * @param string  $name    the parameter name
     * @param mixed   $default the default value if not found
     *
     * @return mixed the parameter value
     */
    protected function getRequestValue(Request $request, string $name, $default = null)
    {
        $key = $this->getSessionKey($name);
        $session = $request->hasSession() ? $request->getSession() : null;

        if ($session) {
            $default = $session->get($key, $default);
        }

        $value = $request->get($name, $default);

        if ($session) {
            $session->set($key, $value);
        }

        return $value;
    }

    /**
     * Gets the session key for the given name.
     *
     * @param string $name the parameter name
     */
    protected function getSessionKey(string $name): string
    {
        if (null === $this->prefix) {
            $this->prefix = Utils::getShortName($this);
        }

        return "{$this->prefix}.$name";
    }

    /**
     * Handle the query parameters.
     *
     * @param DataQuery $query the query parameters
     *
     * @return DataResults the data results
     */
    protected function handleQuery(DataQuery $query): DataResults
    {
        $results = new DataResults();
        $results->status = Response::HTTP_PRECONDITION_FAILED;

        return $results;
    }

    /**
     * Implode the given page list.
     *
     * @param int[] $pageList the page list
     */
    protected function implodePageList(array $pageList): string
    {
        return '[' . \implode(',', $pageList) . ']';
    }

    /**
     * Maps the given entities.
     *
     * @param array $entities the entities to map
     *
     * @return array the mapped entities
     */
    protected function mapEntities(array $entities): array
    {
        if (!empty($entities)) {
            $columns = $this->getColumns();
            $accessor = PropertyAccess::createPropertyAccessor();

            return \array_map(function ($entity) use ($columns, $accessor) {
                return $this->mapValues($entity, $columns, $accessor);
            }, $entities);
        }

        return [];
    }

    /**
     * Map the given object to an array where the keys are the column field.
     *
     * @param mixed            $objectOrArray the object to map
     * @param Column[]         $columns       the column definitions
     * @param PropertyAccessor $accessor      the property accessor to get the object values
     *
     * @return string[] the mapped object
     */
    protected function mapValues($objectOrArray, array $columns, PropertyAccessor $accessor): array
    {
        $callback = static function (array $result, Column $column) use ($objectOrArray, $accessor) {
            $result[$column->getAlias()] = $column->mapValue($objectOrArray, $accessor);

            return $result;
        };

        return \array_reduce($columns, $callback, []);
    }

    /**
     * Update the results before sending back.
     *
     * @param DataQuery   $query   the data query
     * @param DataResults $results the results to update
     */
    protected function updateResults(DataQuery $query, DataResults &$results): void
    {
        // callback?
        if ($query->callback) {
            return;
        }

        // page list and limit
        if (empty($results->pageList)) {
            $results->pageList = $this->getAllowedPageList($results->totalNotFiltered);
        }
        $limit = \min($query->limit, \max($results->pageList));

        // results
        $results->columns = $this->getColumns();
        $results->limit = $limit;

        // parameters
        $results->params = \array_merge($results->params, [
            TableInterface::PARAM_ID => $query->id,
            TableInterface::PARAM_SEARCH => $query->search,
            TableInterface::PARAM_SORT => $query->sort,
            TableInterface::PARAM_ORDER => $query->order,
            TableInterface::PARAM_OFFSET => $query->offset,
            TableInterface::PARAM_CARD => $query->card,
            TableInterface::PARAM_LIMIT => $limit,
        ]);

        // attributes
        $results->attributes = \array_merge($results->attributes, [
            'total-not-filtered' => $results->totalNotFiltered,
            'total-rows' => $results->filtered,

            'search' => \json_encode(true),
            'search-text' => $query->search,

            'page-list' => $this->implodePageList($results->pageList),
            'page-number' => $query->page,
            'page-size' => $limit,

            'card-view' => \json_encode($query->card),

            'sort-name' => $query->sort,
            'sort-order' => $query->order,
        ]);
    }

    /**
     * Gets the display card parameter.
     */
    private function getParamCard(Request $request): bool
    {
        $value = $this->getRequestValue($request, TableInterface::PARAM_CARD, false);

        return (bool) \filter_var($value, \FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Gets the selected identifier parameter.
     */
    private function getParamId(Request $request): int
    {
        return (int) $request->get(TableInterface::PARAM_ID, 0);
    }
}
