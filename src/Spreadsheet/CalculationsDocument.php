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
 * Spreadsheet document for the list of calculations.
 *
 * @extends AbstractArrayDocument<\App\Entity\Calculation>
 */
class CalculationsDocument extends AbstractArrayDocument
{
    /**
     * {@inheritdoc}
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    protected function doRender(array $entities): bool
    {
        // initialize
        $title = $this->title ?? 'calculation.list.title';
        $this->start($title, true);

        // headers
        $row = $this->setHeaderValues([
            'calculation.fields.id' => Alignment::HORIZONTAL_CENTER,
            'calculation.fields.date' => Alignment::HORIZONTAL_CENTER,
            'calculation.fields.state' => Alignment::HORIZONTAL_GENERAL,
            'calculation.fields.customer' => Alignment::HORIZONTAL_GENERAL,
            'calculation.fields.description' => Alignment::HORIZONTAL_GENERAL,
            'calculationgroup.fields.amount' => Alignment::HORIZONTAL_RIGHT,
            'calculation.fields.margin' => Alignment::HORIZONTAL_RIGHT,
            'calculation.fields.total' => Alignment::HORIZONTAL_RIGHT,
        ]);

        // formats
        $this->setFormatId(1)
            ->setFormatDate(2)
            ->setFormatAmount(6)
            ->setFormat(7, $this->getMarginFormat())
            ->setFormatAmount(8);

        // rows
        foreach ($entities as $entity) {
            $this->setRowValues($row++, [
                $entity->getId(),
                $entity->getDate(),
                $entity->getStateCode(),
                $entity->getCustomer(),
                $entity->getDescription(),
                $entity->getItemsTotal(),
                $entity->getOverallMargin(),
                $entity->getOverallTotal(),
            ]);
        }

        $this->finish();

        return true;
    }

    /**
     * Gets the overall margin format.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function getMarginFormat(): string
    {
        $minMargin = $this->controller->getApplication()->getMinMargin();
        $format = $this->getPercentFormat();

        return "[Red][<$minMargin]$format;$format";
    }
}
