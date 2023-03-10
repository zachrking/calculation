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
 * Spreadsheet document for the list of customers.
 *
 * @extends AbstractArrayDocument<\App\Entity\Customer>
 */
class CustomersDocument extends AbstractArrayDocument
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
        $this->start('customer.list.title');

        // headers
        $row = $this->setHeaderValues([
            'customer.fields.lastName' => Alignment::HORIZONTAL_GENERAL,
            'customer.fields.firstName' => Alignment::HORIZONTAL_GENERAL,
            'customer.fields.company' => Alignment::HORIZONTAL_GENERAL,
            'customer.fields.address' => Alignment::HORIZONTAL_GENERAL,
            'customer.fields.zipCode' => Alignment::HORIZONTAL_RIGHT,
            'customer.fields.city' => Alignment::HORIZONTAL_GENERAL,
        ]);

        // rows
        foreach ($entities as $entity) {
            $this->setRowValues($row++, [
                $entity->getLastName(),
                $entity->getFirstName(),
                $entity->getCompany(),
                $entity->getAddress(),
                $entity->getZipCode(),
                $entity->getCity(),
            ]);
        }

        $this->finish();

        return true;
    }
}
