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

namespace App\Generator;

use App\Entity\Calculation;
use App\Entity\CalculationItem;
use App\Faker\Generator;
use App\Service\CalculationService;
use App\Service\FakerService;
use App\Util\FormatUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class to generate calculations.
 *
 * @author Laurent Muller
 */
class CalculationGenerator extends AbstractEntityGenerator
{
    private CalculationService $service;

    /**
     * Constructor.
     */
    public function __construct(EntityManagerInterface $manager, CalculationService $service, FakerService $fakerService, TranslatorInterface $translator)
    {
        parent::__construct($manager, $fakerService, $translator);
        $this->service = $service;
    }

    /**
     * {@inheritDoc}
     */
    protected function generateEntities(int $count, bool $simulate, EntityManagerInterface $manager, Generator $generator): JsonResponse
    {
        $calculations = [];
        $id = $simulate ? (int) $manager->getRepository(Calculation::class)->getNextId() : 0;

        // products range
        $productsCount = $generator->productsCount();
        $min = \min(5, $productsCount);
        $max = \min(15, $productsCount);

        for ($i = 0; $i < $count; ++$i) {
            $date = $generator->dateTimeBetween('first day of previous month', 'last day of next month');

            $calculation = new Calculation();
            $calculation->setDate($date)
                ->setDescription($generator->catchPhrase())
                ->setUserMargin($generator->randomFloat(2, 0, 0.1))
                ->setState($generator->state())
                ->setCustomer($generator->name())
                ->setCreatedBy($generator->userName());

            // add products
            $products = $generator->products($generator->numberBetween($min, $max));
            foreach ($products as $product) {
                // copy
                $item = CalculationItem::create($product)->setQuantity($generator->numberBetween(1, 10));
                if ($item->isEmptyPrice()) {
                    $item->setPrice($generator->randomFloat(2, 1, 10));
                }

                // find category
                $category = $calculation->findCategory($product->getCategory());

                // add
                $category->addItem($item);
            }

            // update
            $this->service->updateTotal($calculation);

            // save
            if (!$simulate) {
                $manager->persist($calculation);
            } else {
                $calculation->setId($id++);
            }

            // add
            $calculations[] = $calculation;
        }

        // save
        if (!$simulate) {
            $manager->flush();
        }

        // map
        $items = \array_map(static function (Calculation $c): array {
            return [
                    'id' => FormatUtils::formatId((int) $c->getId()),
                    'date' => FormatUtils::formatDate($c->getDate()),
                    'state' => $c->getStateCode(),
                    'description' => $c->getDescription(),
                    'customer' => $c->getCustomer(),
                    'margin' => FormatUtils::formatPercent($c->getOverallMargin()),
                    'total' => FormatUtils::formatAmount($c->getOverallTotal()),
                    'color' => $c->getStateColor(),
                ];
        }, $calculations);

        return new JsonResponse([
                'result' => true,
                'items' => $items,
                'count' => \count($items),
                'simulate' => $simulate,
                'message' => $this->trans('counters.calculations_generate', ['count' => $count]),
            ]);
    }
}
