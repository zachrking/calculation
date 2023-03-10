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

namespace App\Tests\Traits;

use App\Interfaces\RoleInterface;
use App\Model\Role;
use App\Traits\RoleTranslatorTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Unit test for {@link RoleTranslatorTrait} class.
 */
class RoleTranslatorTraitTest extends TestCase
{
    use RoleTranslatorTrait;

    private ?TranslatorInterface $translator;

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
     * @psalm-suppress InvalidNullableReturnType
     * @psalm-suppress NullableReturnStatement
     */
    public function getTranslator(): TranslatorInterface
    {
        return $this->translator;
    }

    /**
     * @param string|RoleInterface $role the role to translate
     *
     * @dataProvider getTranslateRoles
     */
    public function testTranslateRole(RoleInterface|string $role, string $message): void
    {
        $expected = "user.roles.$message";
        $this->translator = $this->createTranslator($expected);
        $actual = $this->translateRole($role);
        self::assertEquals($actual, $expected);
    }

    private function createTranslator(string $message): TranslatorInterface
    {
        $translator = $this->getMockBuilder(TranslatorInterface::class)
            ->disableOriginalConstructor()
            ->getMock();
        $translator->method('trans')
            ->willReturn($message);

        return $translator;
    }
}
