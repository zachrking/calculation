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

/**
 * Unit test for {@link App\Controller\HomeController} class.
 *
 * @author Laurent Muller
 */
class HomeControllerTest extends AbstractControllerTest
{
    public function getRoutes(): array
    {
        return [
            ['/', self::ROLE_USER],
            ['/', self::ROLE_ADMIN],
            ['/', self::ROLE_SUPER_ADMIN],

            ['/sitemap', self::ROLE_USER],
            ['/sitemap', self::ROLE_ADMIN],
            ['/sitemap', self::ROLE_SUPER_ADMIN],
        ];
    }
}