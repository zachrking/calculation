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

namespace App\Tests\Controller;

use App\Entity\Calculation;
use App\Entity\CalculationState;
use App\Entity\Category;
use App\Entity\Group;
use App\Entity\Product;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit test for {@link App\Controller\CalculationDuplicateController} class.
 *
 * @author Laurent Muller
 */
class CalculationDuplicateControllerTest extends AbstractControllerTest
{
    private static ?Calculation $calculation = null;
    private static ?Category $category = null;
    private static ?Group $group = null;
    private static ?Product $product = null;
    private static ?CalculationState $state = null;

    public function getRoutes(): array
    {
        return [
            ['/duplicate', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/duplicate', self::ROLE_ADMIN],
            ['/duplicate', self::ROLE_SUPER_ADMIN],

            ['/duplicate/card', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/duplicate/card', self::ROLE_ADMIN],
            ['/duplicate/card', self::ROLE_SUPER_ADMIN],

            ['/duplicate/pdf', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/duplicate/pdf', self::ROLE_ADMIN],
            ['/duplicate/pdf', self::ROLE_SUPER_ADMIN],

            ['/duplicate/excel', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/duplicate/excel', self::ROLE_ADMIN],
            ['/duplicate/excel', self::ROLE_SUPER_ADMIN],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function addEntities(): void
    {
        if (null === self::$state) {
            self::$state = new CalculationState();
            self::$state->setCode('Test State');
            $this->addEntity(self::$state);
        }

        if (null === self::$group) {
            self::$group = new Group();
            self::$group->setCode('Test Group');
            $this->addEntity(self::$group);
        }

        if (null === self::$category) {
            self::$category = new Category();
            self::$category->setCode('Test Category')
                ->setGroup(self::$group);
            $this->addEntity(self::$category);
        }

        if (null === self::$product) {
            self::$product = new Product();
            self::$product->setDescription('Test Product')
                ->setCategory(self::$category)
                ->setPrice(10);
            $this->addEntity(self::$product);
        }

        if (null === self::$calculation) {
            self::$calculation = new Calculation();
            self::$calculation->setCustomer('Test Customer')
                ->setDescription('Test Description')
                ->setState(self::$state)
                ->addProduct(self::$product)
                ->addProduct(self::$product);
            $this->addEntity(self::$calculation);
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function deleteEntities(): void
    {
        self::$calculation = $this->deleteEntity(self::$calculation);
        self::$category = $this->deleteEntity(self::$category);
        self::$group = $this->deleteEntity(self::$group);
        self::$product = $this->deleteEntity(self::$product);
        self::$state = $this->deleteEntity(self::$state);
    }
}