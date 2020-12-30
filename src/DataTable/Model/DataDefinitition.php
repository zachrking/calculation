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

namespace App\DataTable\Model;

use App\Util\Utils;
use DataTables\Column;

/**
 * Represents a column definition mapping.
 *
 * @author Laurent Muller
 */
class DataDefinitition
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var bool
     */
    private $orderable;

    /**
     * @var string
     */
    private $seachValue;

    /**
     * @var bool
     */
    private $searchable;

    /**
     * @var string[]
     */
    private $searchFields;

    /**
     * @var string[]
     */
    private $sortFields;

    /**
     * Constructor.
     *
     * @param Column          $column       the column
     * @param string|string[] $sortFields   the sort fields
     * @param string|string[] $searchFields the search fields
     */
    public function __construct(Column $column, $sortFields, $searchFields)
    {
        $this->name = $column->name;
        $this->orderable = $column->orderable;
        $this->searchable = $column->searchable;
        $this->seachValue = $column->search->value;
        $this->sortFields = (array) $sortFields;
        $this->searchFields = (array) $searchFields;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    /**
     * Gets the column name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Gets the search fields.
     *
     * @return string[]
     */
    public function getSearchFields(): array
    {
        return $this->searchFields;
    }

    /**
     * Gets the search value.
     */
    public function getSearchValue(): ?string
    {
        return $this->seachValue;
    }

    /**
     * Gets the sort fields.
     *
     * @return string[]
     */
    public function getSortFields(): array
    {
        return $this->sortFields;
    }

    /**
     * Gets a value indicating if this column is orderable.
     */
    public function isOrderable(): bool
    {
        return $this->orderable;
    }

    /**
     * Gets a value indicating if this column is searchable and contains a search value.
     *
     * @see DataDefinitition::isSearchable()
     * @see DataDefinitition::isSearchValue()
     */
    public function isSearch(): bool
    {
        return $this->isSearchable() && $this->isSearchValue();
    }

    /**
     * Gets a value indicating if this column is searchable.
     *
     * @see DataDefinitition::isSearch()
     * @see DataDefinitition::isSearchValue()
     */
    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Gets a value indicating if this column search value is set.
     *
     * @see DataDefinitition::isSearch()
     * @see DataDefinitition::isSearchable()
     */
    public function isSearchValue(): bool
    {
        return Utils::isString($this->seachValue);
    }

    /**
     * Sets a value indicating if this column is searchable.
     */
    public function setSearchable(bool $searchable): self
    {
        $this->searchable = $searchable;

        return $this;
    }
}