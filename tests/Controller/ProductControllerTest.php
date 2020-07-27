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

namespace App\Tests\Controller;

use App\Entity\Category;
use App\Entity\Product;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit test for product controller.
 *
 * @author Laurent Muller
 */
class ProductControllerTest extends AbstractControllerTest
{
    private static ?Category $category = null;
    private static ?Product $product = null;

    public function getRoutes(): array
    {
        return [
            ['/product', self::ROLE_USER],
            ['/product', self::ROLE_ADMIN],
            ['/product', self::ROLE_SUPER_ADMIN],

            ['/product/table', self::ROLE_USER],
            ['/product/table', self::ROLE_ADMIN],
            ['/product/table', self::ROLE_SUPER_ADMIN],

            ['/product/add', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/product/add', self::ROLE_ADMIN],
            ['/product/add', self::ROLE_SUPER_ADMIN],

            ['/product/edit/1', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/product/edit/1', self::ROLE_ADMIN],
            ['/product/edit/1', self::ROLE_SUPER_ADMIN],

            ['/product/delete/1', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/product/delete/1', self::ROLE_ADMIN],
            ['/product/delete/1', self::ROLE_SUPER_ADMIN],

            ['/product/show/1', self::ROLE_USER],
            ['/product/show/1', self::ROLE_ADMIN],
            ['/product/show/1', self::ROLE_SUPER_ADMIN],

            ['/product/clone/1', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/product/clone/1', self::ROLE_ADMIN],
            ['/product/clone/1', self::ROLE_SUPER_ADMIN],

            ['/product/pdf', self::ROLE_USER],
            ['/product/pdf', self::ROLE_ADMIN],
            ['/product/pdf', self::ROLE_SUPER_ADMIN],
        ];
    }

    /**
     * @dataProvider getRoutes
     */
    public function testRoutes(string $url, string $username, int $expected = Response::HTTP_OK): void
    {
        self::addEntities();
        $this->checkRoute($url, $username, $expected);
    }

    private static function addEntities(): void
    {
        if (null === self::$category) {
            self::$category = new Category();
            self::$category->setCode('Test Category');
            self::addEntity(self::$category);
        }

        if (null === self::$product) {
            self::$product = new Product();
            self::$product->setDescription('Test Product')
                ->setCategory(self::$category);
            self::addEntity(self::$product);
        }
    }

    private static function deleteEntities(): void
    {
        self::$product = self::deleteEntity(self::$product);
        self::$category = self::deleteEntity(self::$category);
    }
}
