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

namespace App\Report;

use App\Entity\GlobalMargin;
use App\Pdf\PdfColumn;
use App\Pdf\PdfTableBuilder;
use App\Util\FormatUtils;

/**
 * Report for the list of global margins.
 *
 * @extends AbstractArrayReport<GlobalMargin>
 */
class GlobalMarginsReport extends AbstractArrayReport
{
    /**
     * {@inheritdoc}
     *
     * @param GlobalMargin[] $entities
     */
    protected function doRender(array $entities): bool
    {
        // title
        $this->setTitleTrans('globalmargin.list.title');

        // new page
        $this->AddPage();

        // table
        $table = new PdfTableBuilder($this);
        $table->addColumns(
            PdfColumn::right($this->trans('globalmargin.fields.minimum'), 50),
            PdfColumn::right($this->trans('globalmargin.fields.maximum'), 50),
            PdfColumn::right($this->trans('globalmargin.fields.margin'), 50)
        )->outputHeaders();

        foreach ($entities as $entity) {
            $table->addRow(
                FormatUtils::formatAmount($entity->getMinimum()),
                FormatUtils::formatAmount($entity->getMaximum()),
                FormatUtils::formatPercent($entity->getMargin())
            );
        }

        // count
        return $this->renderCount($entities);
    }
}
