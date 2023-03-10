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

namespace App\Spreadsheet;

use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * Spreadsheet document for the list of categories.
 *
 * @extends AbstractArrayDocument<\App\Entity\Category>
 */
class CategoriesDocument extends AbstractArrayDocument
{
    /**
     * {@inheritdoc}
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function doRender(array $entities): bool
    {
        // initialize
        $this->start('category.list.title');

        // headers
        $row = $this->setHeaderValues([
            'category.fields.code' => Alignment::HORIZONTAL_GENERAL,
            'category.fields.description' => Alignment::HORIZONTAL_GENERAL,
            'category.fields.group' => Alignment::HORIZONTAL_GENERAL,
            'category.fields.products' => Alignment::HORIZONTAL_RIGHT,
            'category.fields.tasks' => Alignment::HORIZONTAL_RIGHT,
        ]);

        // formats
        $this->setFormatInt(4);

        // rows
        $default = $this->trans('report.other');
        foreach ($entities as $entity) {
            $this->setRowValues($row++, [
                $entity->getCode(),
                $entity->getDescription(),
                $entity->getGroupCode() ?? $default,
                $entity->countProducts(),
                $entity->countTasks(),
            ]);
        }

        $this->finish();

        return true;
    }
}
