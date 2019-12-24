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

namespace App\Pivot;

use App\Pivot\Aggregator\Aggregator;
use App\Utils\Utils;

/**
 * Represents a pivot node.
 *
 * @author Laurent Muller
 */
class PivotNode extends PivotAggregator implements \JsonSerializable, \Countable
{
    /**
     * The path separator.
     */
    public const PATH_SEPARATOR = '/';

    /**
     * The ascending sort mode.
     */
    public const SORT_ASC = 1;

    /**
     * The descending sort mode.
     */
    public const SORT_DESC = 2;

    /**
     * The none sort mode.
     */
    public const SORT_NONE = 0;

    /**
     * The children.
     *
     * @var PivotNode[]
     */
    private $children = [];

    /**
     * The key.
     *
     * @var mixed
     */
    private $key;

    /**
     * The parent node.
     *
     * @var PivotNode
     */
    private $parent;

    /**
     * The sort direction.
     *
     * @var int
     */
    private $sortMode = self::SORT_ASC;

    /**
     * The title.
     *
     * @var string
     */
    private $title;

    /**
     * Constructor.
     *
     * @param Aggregator $aggregator the aggregator function
     * @param mixed      $key        the key
     * @param mixed      $value      the initial value
     */
    public function __construct(Aggregator $aggregator, $key = null, $value = null)
    {
        parent::__construct($aggregator, $value);
        $this->key = $key;
    }

    public function __toString(): string
    {
        $className = Utils::getShortName($this);
        //$value = $this->getFormattedResult();

        return \sprintf('%s(%s)', $className, 0);
    }

    /**
     * Creates a new node and add it to this list of children.
     *
     * @param Aggregator $aggregator the aggregator function
     * @param mixed      $key        the key
     * @param mixed      $value      the initial value
     *
     * @return self the newly created node
     */
    public function add(Aggregator $aggregator, $key = null, $value = null): self
    {
        $node = new self($aggregator, $key, $value);
        $this->addNode($node);

        return $node;
    }

    /**
     * Adds a child to the list of this children.
     *
     * <b>NB:</b> The children are sorted after insertion.
     *
     * @param PivotNode the child to add
     */
    public function addNode(self $child): self
    {
        $this->children[] = $child;
        $child->setParent($this);

        // sort
        return $this->sort();
    }

    /**
     * {@inheritdoc}
     */
    public function addValue($value)
    {
        parent::addValue($value);

        return $this->update();
    }

    /**
     * {@inheritdoc}
     */
    public function count(): int
    {
        return \count($this->children);
    }

    /**
     * Returns if the given key is the same as this key.
     *
     * @param mixed $key the key to compare to
     *
     * @return bool true if equal
     */
    public function equalsKey($key): bool
    {
        return $key === $this->key;
    }

    /**
     * Returns if the given keys are the same as this keys.
     *
     * @param mixed $keys the keys to compare to
     *
     * @return bool true if equal
     *
     * @see PivotNode::getKeys()
     */
    public function equalsKeys(array $keys): bool
    {
        return $keys === $this->getKeys();
    }

    /**
     * Finds a child node for the given key.
     *
     * @param mixed $key the node key to search for
     *
     * @return self|null the child node, if found; null otherwise
     */
    public function find($key): ?self
    {
        foreach ($this->children as $child) {
            if ($child->equalsKey($key)) {
                return $child;
            }
        }

        return null;
    }

    /**
     * Finds a child node for the given array of keys.
     *
     * @param array $keys the node keys to search for
     *
     * @return self|null the child node, if found; null otherwise
     */
    public function findByKeys(array $keys): ?self
    {
        $current = $this;
        foreach ($keys as $key) {
            if (!$found = $current->find($key)) {
                return null;
            }
            $current = $found;
        }

        return $current;
    }

    /**
     * Recusively finds a child node for the given key.
     *
     * @param mixed $key the node key to search for
     *
     * @return self|null the node, if found; null otherwise
     */
    public function findRecursive($key): ?self
    {
        foreach ($this->children as $child) {
            if ($child->equalsKey($key)) {
                return $child;
            }
            if ($found = $child->findRecursive($key)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * Gets the children.
     *
     * @return PivotNode[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    /**
     * Gets the maximum deep level.
     *
     * @return int the deep level
     */
    public function getDeepLevel(): int
    {
        $level = 0;
        $node = $this;
        while (!$node->isEmpty()) {
            ++$level;
            $node = $node->children[0];
        }

        return $level;
    }

    /**
     * Gets the key.
     *
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Gets the keys.
     *
     * @return array the keys or an empty array if this node is the root node
     */
    public function getKeys(): array
    {
        if ($this->isRoot()) {
            return [];
        }

        $result = [$this->key];
        $parent = $this->parent;
        while (null !== $parent && !$parent->isRoot()) {
            // put first
            \array_unshift($result, $parent->key);
            $parent = $parent->parent;
        }

        return $result;
    }

    /**
     * Gets all children from the last level.
     *
     * @return PivotNode[]
     */
    public function getLastChildren(): array
    {
        if ($this->isEmpty()) {
            return [$this];
        }

        $result = [];
        foreach ($this->children as $child) {
            if ($child->isEmpty()) {
                $result[] = $child;
            } else {
                $result = \array_merge($result, $child->getLastChildren());
            }
        }

        return $result;
    }

    /**
     * Gets the level (0 for root, 1 for first level, etc...).
     *
     * @return int the level
     */
    public function getLevel(): int
    {
        return $this->isRoot() ? 0 : 1 + $this->parent->getLevel();
    }

    /**
     * Gets all children for the given level.
     *
     * @param int $level the level
     *
     * @return PivotNode[]
     */
    public function getLevelChildren(int $level): array
    {
        if ($this->getLevel() === $level) {
            return [$this];
        }

        $result = [];
        foreach ($this->children as $child) {
            if ($child->getLevel() === $level) {
                $result[] = $child;
            } else {
                $result = \array_merge($result, $child->getLevelChildren($level));
            }
        }

        return $result;
    }

    /**
     * Gets the parent's node.
     *
     * @return self|null the parent's node or null if root
     */
    public function getParent(): ?self
    {
        return $this->parent;
    }

    /**
     * Gets the path.
     */
    public function getPath(): string
    {
        $sep = self::PATH_SEPARATOR;
        if (!$this->isRoot()) {
            return $sep.\implode($sep, $this->getKeys());
        }

        return $sep;
    }

    /**
     * Gets the sort mode.
     *
     * @return int one of the SORT_XX constant
     */
    public function getSortMode()
    {
        return $this->sortMode;
    }

    /**
     * Gets the title.
     *
     * @return string|null the title, if any; the key otherwise
     */
    public function getTitle(): ?string
    {
        return $this->title ?: (string) $this->key;
    }

    /**
     * Gets the titles.
     *
     * @return string[] the titles or an empty array if this node is the root node
     */
    public function getTitles(): array
    {
        if ($this->isRoot()) {
            return [];
        }

        $result = [$this->getTitle()];
        $parent = $this->parent;
        while (null !== $parent && !$parent->isRoot()) {
            // put first
            \array_unshift($result, $parent->getTitle());
            $parent = $parent->parent;
        }

        return $result;
    }

    /**
     * Returns if this node is empty.
     *
     * @return bool true if this node does not contains children
     */
    public function isEmpty(): bool
    {
        return 0 === $this->count();
    }

    /**
     * Returns if this node is a leaf node.
     *
     * A leaf node is a node without children.
     *
     * @return bool <code>true</code> if leaf
     */
    public function isLeaf(): bool
    {
        return $this->isEmpty();
    }

    /**
     * Returns if this node is a root node.
     *
     * A root node is a node without a parent.
     *
     * @return bool <code>true</code> if root
     */
    public function isRoot(): bool
    {
        return null === $this->parent;
    }

    /**
     * Returns if this title if defined.
     *
     * @return bool true if defined
     */
    public function isTitle(): bool
    {
        return null !== $this->title;
    }

    /**
     * {@inheritdoc}
     */
    public function jsonSerialize()
    {
        $result = [];
        if ($this->key) {
            $result['key'] = $this->key;
        }
        if ($this->title) {
            $result['title'] = $this->title;
        }

        if (!empty($this->getValue())) {
            $result['value'] = $this->aggregator->getFormattedResult();
        }

        if (!$this->isEmpty()) {
            $result['children'] = $this->children;
        }

        return empty($result) ? null : $result;
    }

    /**
     * {@inheritdoc}
     */
    public function setAggregator(Aggregator $aggregator)
    {
        parent::setAggregator($aggregator);
        foreach ($this->children as $child) {
            $child->setAggregator($aggregator);
        }

        return $this;
    }

    /**
     * Sets the parent's node.
     *
     * @param PivotNode $parent the parent to set
     */
    public function setParent(?self $parent): self
    {
        $this->parent = $parent;

        return $this;
    }

    /**
     * Sets the sort mode.
     *
     * @param int $sortMode one of the SORT_XX constant
     */
    public function setSortMode(int $sortMode): self
    {
        switch ($sortMode) {
            case self::SORT_ASC:
            case self::SORT_DESC:
            case self::SORT_NONE:
                if ($this->sortMode !== $sortMode) {
                    $this->sortMode = $sortMode;

                    return $this->sort();
                }
                break;
        }

        return $this;
    }

    /**
     * Sets the title.
     *
     * @param string $title the title to set
     */
    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Sort children.
     */
    private function sort(): self
    {
        switch ($this->sortMode) {
            case self::SORT_ASC:
                return $this->sortAscending();
            case self::SORT_DESC:
                return $this->sortDescending();
            case self::SORT_NONE:
                // nothing to do
                return $this;
        }
    }

    /**
     * Sort children ascending.
     */
    private function sortAscending(): self
    {
        if (!$this->isEmpty()) {
            \usort($this->children, function (self $left, self $right): int {
                return $left->getKey() <=> $right->getKey();
            });
        }

        return $this;
    }

    /**
     * Sort children descending.
     */
    private function sortDescending(): self
    {
        if (!$this->isEmpty()) {
            \usort($this->children, function (self $left, self $right): int {
                return $right->getKey() <=> $left->getKey();
            });
        }

        return $this;
    }

    /**
     * Update this value with the sum of all children (if any).
     *
     * <b>NB:</b> This method is called recursively for the parents (if any).
     */
    private function update(): self
    {
        if (!$this->isEmpty()) {
            $this->aggregator->init();
            foreach ($this->children as $child) {
                $this->aggregator->add($child->getAggregator());
            }
        }

        if ($this->parent) {
            $this->parent->update();
        }

        return $this;
    }
}
