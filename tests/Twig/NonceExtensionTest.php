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

namespace App\Tests\Twig;

use App\Twig\NonceExtension;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit test for Twig NonceExtension.
 */
class NonceExtensionTest extends KernelTestCase
{
    /**
     * @var NonceExtension
     */
    private $extension;

    /**
     * {@inheritDoc}
     */
    protected function setUp(): void
    {
        $extension = self::getContainer()->get(NonceExtension::class);
        if ($extension instanceof NonceExtension) {
            $this->extension = $extension;
        }
    }

    public function testExtensionNotNull(): void
    {
        self::assertNotNull($this->extension);
    }

    public function testLength32(): void
    {
        $nonce = $this->extension->getNonce(32);
        self::assertEquals(64, \strlen($nonce));
    }

    public function testLengthDefault(): void
    {
        $nonce = $this->extension->getNonce();
        self::assertEquals(32, \strlen($nonce));
    }
}
