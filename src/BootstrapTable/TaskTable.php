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

use App\Repository\CategoryRepository;
use App\Repository\TaskRepository;

/**
 * The tasks table.
 *
 * @author Laurent Muller
 * @template-extends AbstractCategoryItemTable<\App\Entity\Task>
 */
class TaskTable extends AbstractCategoryItemTable
{
    /**
     * Constructor.
     */
    public function __construct(TaskRepository $repository, CategoryRepository $categoryRepository)
    {
        parent::__construct($repository, $categoryRepository);
    }

    /**
     * {@inheritDoc}
     */
    protected function getCategories(CategoryRepository $repository): array
    {
        return $repository->getListCountTasks();
    }

    /**
     * {@inheritdoc}
     */
    protected function getColumnDefinitions(): string
    {
        return __DIR__ . '/Definition/task.json';
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOrder(): array
    {
        return ['name' => self::SORT_ASC];
    }
}
