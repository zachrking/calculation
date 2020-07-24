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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Unit test for users and routes.
 *
 * @author Laurent Muller
 */
class RoutesTest extends AuthenticateWebTestCase
{
    public function getRoutes(): array
    {
        return [
            // index
            ['/', self::ROLE_USER],
            ['/', self::ROLE_ADMIN],
            ['/', self::ROLE_SUPER_ADMIN],

            // about controller
            ['/about', self::ROLE_USER],
            ['/about', self::ROLE_ADMIN],
            ['/about', self::ROLE_SUPER_ADMIN],

            // admin controller
            ['/admin/rights/admin', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/admin/rights/admin', self::ROLE_ADMIN, Response::HTTP_FORBIDDEN],
            ['/admin/rights/admin', self::ROLE_SUPER_ADMIN],

            ['/admin/rights/user', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/admin/rights/user', self::ROLE_ADMIN],
            ['/admin/rights/user', self::ROLE_SUPER_ADMIN],

            ['/admin/parameters', self::ROLE_USER, Response::HTTP_FORBIDDEN],
            ['/admin/parameters', self::ROLE_ADMIN],
            ['/admin/parameters', self::ROLE_SUPER_ADMIN],

            // not exist
            ['/not_exist', self::ROLE_USER, Response::HTTP_NOT_FOUND],
        ];
    }

    /**
     * @dataProvider getRoutes
     */
    public function testRoutes(string $url, string $username, int $expected = Response::HTTP_OK): void
    {
        $this->loginUserName($username);
        $this->client->request(Request::METHOD_GET, $url);
        $this->checkResponse($url, $username, $expected);
    }
}
