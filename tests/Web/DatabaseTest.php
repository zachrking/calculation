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

namespace App\Tests\Web;

use App\Entity\User;
use App\Repository\CalculationGroupRepository;
use App\Repository\CalculationItemRepository;
use App\Repository\CalculationRepository;
use App\Repository\CalculationStateRepository;
use App\Repository\CategoryMarginRepository;
use App\Repository\CategoryRepository;
use App\Repository\GlobalMarginRepository;
use App\Repository\ProductRepository;
use App\Repository\PropertyRepository;
use App\Repository\UserRepository;
use App\Tests\DatabaseTrait;
use Doctrine\ORM\EntityRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit test for database.
 *
 * @author Laurent Muller
 */
class DatabaseTest extends KernelTestCase
{
    use DatabaseTrait;

    /**
     * {@inheritdoc}
     */
    public function setUp(): void
    {
        self::bootKernel();
    }

    public function getRepositories(): array
    {
        return [
            [CategoryRepository::class, 0],
            [CategoryMarginRepository::class, 0],
            [ProductRepository::class, 0],
            [CalculationStateRepository::class, 0],
            [CalculationRepository::class, 0],
            [CalculationGroupRepository::class, 0],
            [CalculationItemRepository::class, 0],
            [GlobalMarginRepository::class, 0],
            [PropertyRepository::class, 0],
            [UserRepository::class, 4],
        ];
    }

    public function getTables(): array
    {
        return [
            ['sy_Category', 0],
            ['sy_CategoryMargin', 0],
            ['sy_Product', 0],
            ['sy_CalculationState', 0],
            ['sy_Calculation', 0],
            ['sy_CalculationGroup', 0],
            ['sy_CalculationItem', 0],
            ['sy_GlobalMargin', 0],
            ['sy_Property', 0],
            ['sy_User', 4],
        ];
    }

    public function getUsers(): array
    {
        return [
            [AuthenticateWebTestCase::ROLE_USER, User::ROLE_USER],
            [AuthenticateWebTestCase::ROLE_ADMIN, User::ROLE_ADMIN],
            [AuthenticateWebTestCase::ROLE_SUPER_ADMIN, User::ROLE_SUPER_ADMIN],
            [AuthenticateWebTestCase::ROLE_DISABLED, User::ROLE_USER],
        ];
    }

    /**
     * @dataProvider getRepositories
     */
    public function testRepository(string $class, int $expected): void
    {
        /** @var EntityRepository $repository */
        $repository = self::$container->get($class);
        $this->assertNotNull($repository);

        $result = $repository->findAll();
        $this->assertEquals($expected, \count($result));
    }

    /**
     * @dataProvider getTables
     */
    public function testTable(string $tablename, int $expected): void
    {
        $query = "SELECT COUNT(id) FROM $tablename";
        $result = self::$database->querySingle($query);
        $this->assertEquals($expected, $result);
    }

    /**
     * @dataProvider getUsers
     */
    public function testUser(string $username, string $role): void
    {
        $username = \strtolower($username);

        /** @var UserRepository $repository */
        $repository = self::$container->get(UserRepository::class);
        $this->assertNotNull($repository);

        /** @var User $user */
        $user = $repository->findOneBy(['username' => $username]);
        $this->assertInstanceOf(User::class, $user);

        $this->assertEquals($username, $user->getUsername());
        $this->assertTrue($user->hasRole($role));
    }
}
