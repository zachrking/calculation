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

namespace App\Tests\Service;

use App\Entity\Calculation;
use App\Service\ApplicationService;
use App\Tests\DatabaseTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Unit test for the {@link App\Service\ApplicationService} class.
 *
 * @author Laurent Muller
 */
class ApplicationServiceTest extends KernelTestCase
{
    use DatabaseTrait;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testActions(): void
    {
        $service = $this->getService();
        $this->assertEquals('edit', $service->getEditAction());
        $this->assertTrue($service->isActionEdit());
        $this->assertFalse($service->isActionShow());
        $this->assertFalse($service->isActionNone());
        $this->assertEquals('table', $service->getDisplayMode());
    }

    public function testAdminRole(): void
    {
        $service = $this->getService();
        $role = $service->getAdminRole();
        $rights = $service->getAdminRights();
        $this->assertSame('ROLE_ADMIN', $role->getName());
        $this->assertEquals($role->getRights(), $rights);
    }

    public function testCustomer(): void
    {
        $service = $this->getService();
        $service->setProperties([
            'customer_name' => 'customer_name',
            'customer_url' => 'customer_url',
        ]);

        $this->assertEquals('customer_name', $service->getCustomerName());
        $this->assertEquals('customer_url', $service->getCustomerUrl());

        $customer = $service->getCustomer();
        $this->assertEquals('customer_name', $customer->getName());
        $this->assertEquals('customer_url', $customer->getUrl());
        $this->assertNull($customer->getAddress());
        $this->assertNull($customer->getEmail());
        $this->assertNull($customer->getFax());
        $this->assertNull($customer->getPhone());
        $this->assertFalse($customer->isPrintAddress());
        $this->assertNull($customer->getZipCity());
    }

    public function testDates(): void
    {
        $service = $this->getService();
        $this->assertNull($service->getLastImport());
        $this->assertNull($service->getUpdateProducts());
    }

    public function testDefaultCategory(): void
    {
        $service = $this->getService();
        $this->assertNull($service->getDefaultCategory());
        $this->assertEquals(0, $service->getDefaultCategoryId());
    }

    public function testDefaultProduct(): void
    {
        $service = $this->getService();
        $this->assertNull($service->getDefaultProduct());
        $this->assertEquals(0, $service->getDefaultProductId());
        $this->assertEquals(0, $service->getDefaultQuantity());
        $this->assertTrue($service->isDefaultEdit());
    }

    public function testDefaultState(): void
    {
        $service = $this->getService();
        $this->assertNull($service->getDefaultState());
        $this->assertEquals(0, $service->getDefaultStateId());
    }

    public function testMessage(): void
    {
        $service = $this->getService();
        $this->assertEquals('bottom-right', $service->getMessagePosition());
        $this->assertEquals(4000, $service->getMessageTimeout());
        $this->assertFalse($service->isMessageSubTitle());
    }

    public function testMinMargin(): void
    {
        $service = $this->getService();
        $this->assertEquals(1.1, $service->getMinMargin());
        $this->assertTrue($service->isMarginBelow(1.0));
        $this->assertFalse($service->isMarginBelow(1.2));

        $calculation = new Calculation();
        $this->assertFalse($service->isMarginBelow($calculation));
    }

    public function testOptions(): void
    {
        $service = $this->getService();
        $this->assertFalse($service->isQrCode());
        $this->assertFalse($service->isPrintAddress());
    }

    public function testPanels(): void
    {
        $service = $this->getService();
        $this->assertTrue($service->isPanelCatalog());
        $this->assertTrue($service->isPanelMonth());
        $this->assertTrue($service->isPanelState());
        $this->assertEquals(10, $service->getPanelCalculation());
    }

    public function testSecurity(): void
    {
        $service = $this->getService();
        $this->assertEquals(-1, $service->getMinStrength());
    }

    public function testUserRole(): void
    {
        $service = $this->getService();
        $role = $service->getUserRole();
        $rights = $service->getUserRights();
        $this->assertSame('ROLE_USER', $role->getName());
        $this->assertEquals($role->getRights(), $rights);
    }

    private function getService(): ApplicationService
    {
        /** @var ApplicationService $service */
        $service = static::getContainer()->get(ApplicationService::class);

        return $service;
    }
}
