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

use App\Entity\Task;
use App\Pdf\PdfColumn;
use App\Pdf\PdfGroup;
use App\Pdf\PdfGroupListenerInterface;
use App\Pdf\PdfGroupTableBuilder;
use App\Pdf\PdfStyle;
use App\Pdf\PdfTextColor;
use App\Util\FormatUtils;

/**
 * Report for the list of tasks.
 *
 * @extends AbstractArrayReport<Task>
 */
class TasksReport extends AbstractArrayReport implements PdfGroupListenerInterface
{
    /**
     * {@inheritdoc}
     */
    public function outputGroup(PdfGroupTableBuilder $parent, PdfGroup $group): bool
    {
        /** @var Task $task */
        $task = $group->getKey();
        $category = \sprintf('%s / %s', (string) $task->getGroupCode(), (string) $task->getCategoryCode());
        $parent->startRow()
            ->add(text: $task->getName(), style: $group->getStyle())
            ->add($category)
            ->add($task->getUnit());
        if ($task->isEmpty()) {
            $parent->add($this->trans('task.edit.empty_items'), 3);
        }
        $parent->completeRow();

        return true;
    }

    /**
     * {@inheritdoc}
     */
    protected function doRender(array $entities): bool
    {
        // title
        $this->setTitleTrans('task.list.title', [], true);

        // new page
        $this->AddPage();

        // table
        $table = $this->createTable();

        // styles
        $table->getGroupStyle()?->setFontBold();
        $itemStyle = PdfStyle::getCellStyle()
            ->setIndent(4);
        $emptyStyle = PdfStyle::getCellStyle()
            ->setTextColor(PdfTextColor::red());

        foreach ($entities as $entity) {
            // empty?
            if ($entity->isEmpty()) {
                $table->setGroupKey($entity);
                continue;
            }

            foreach ($entity->getItems() as $item) {
                // check for new page
                $count = 1 + \max($item->count(), 1);
                $height = $count * self::LINE_HEIGHT;
                if ($this->isPrintable($height)) {
                    $table->setGroupKey($entity);
                } else {
                    $table->setGroupKey($entity, false);
                    $table->checkNewPage($height);
                }

                if ($item->isEmpty()) {
                    $table->startRow()
                        ->add(text: $item->getName(), style: $itemStyle)
                        ->add('')
                        ->add('')
                        ->add($this->trans('taskitem.edit.empty_items'), 3)
                        ->endRow();
                } else {
                    $index = 0;
                    foreach ($item->getMargins() as $margin) {
                        $table->startRow();
                        if (0 === $index++) {
                            $table->add(text: $item->getName(), style: $itemStyle);
                        } else {
                            $table->add();
                        }
                        $style = empty($margin->getValue()) ? $emptyStyle : null;
                        $table->add()
                            ->add()
                            ->add(FormatUtils::formatAmount($margin->getMinimum()))
                            ->add(FormatUtils::formatAmount($margin->getMaximum()))
                            ->add(text: FormatUtils::formatAmount($margin->getValue()), style: $style)
                            ->endRow();
                    }
                }
            }
        }

        // count
        return $this->renderCount($entities, 'counters.tasks');
    }

    /**
     * Creates the table builder.
     */
    private function createTable(): PdfGroupTableBuilder
    {
        $table = new PdfGroupTableBuilder($this);

        return $table->addColumns(
            PdfColumn::left($this->trans('task.fields.name'), 40),
            PdfColumn::left($this->trans('task.fields.category'), 50, true),
            PdfColumn::left($this->trans('task.fields.unit'), 15, true),
            PdfColumn::right($this->trans('taskitemmargin.fields.minimum'), 20, true),
            PdfColumn::right($this->trans('taskitemmargin.fields.maximum'), 20, true),
            PdfColumn::right($this->trans('taskitemmargin.fields.value'), 20, true)
        )->outputHeaders()
            ->setGroupListener($this);
    }
}
