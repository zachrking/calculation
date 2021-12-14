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

namespace App\Tests\Traits;

use App\Interfaces\RoleInterface;
use App\Model\Role;
use App\Traits\RoleTranslatorTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit test for {@link App\Traits\RoleTranslatorTrait} class.
 *
 * @author Laurent Muller
 */
class RoleTranslatorTraitTest extends TestCase
{
    use RoleTranslatorTrait;

    public function getTranslateRoles(): array
    {
        return [
            [RoleInterface::ROLE_USER, 'user'],
            [RoleInterface::ROLE_ADMIN, 'admin'],
            [RoleInterface::ROLE_SUPER_ADMIN, 'super_admin'],

            [new Role(RoleInterface::ROLE_USER), 'user'],
            [new Role(RoleInterface::ROLE_ADMIN), 'admin'],
            [new Role(RoleInterface::ROLE_SUPER_ADMIN), 'super_admin'],
        ];
    }

    /**
     * @param string|RoleInterface $role the role to translate
     *
     * @dataProvider getTranslateRoles
     */
    public function testTranslateRole($role, string $message): void
    {
        $this->translator = $this->getTranslator();
        $actual = $this->translateRole($role);
        $expected = "user.roles.$message";
        $this->assertEquals($actual, $expected);
    }

    private function getTranslator(): TranslatorInterface
    {
        $translator = $this->getMockBuilder(TranslatorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $translator->method('trans')
            ->willReturn($this->returnArgument(0));

        return $translator;
    }
}