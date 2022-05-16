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

namespace App\Controller;

use App\Generator\CalculationGenerator;
use App\Generator\CustomerGenerator;
use App\Generator\ProductGenerator;
use App\Interfaces\GeneratorInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller to generate entities.
 */
#[AsController]
#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route(path: '/generate')]
class GeneratorController extends AbstractController
{
    private const KEY_COUNT = 'admin.generate.count';
    private const KEY_ENTITY = 'admin.generate.entity';
    private const KEY_SIMULATE = 'admin.generate.simulate';

    private const ROUTE_CALCULATION = 'generate_calculation';
    private const ROUTE_CUSTOMER = 'generate_customer';
    private const ROUTE_PRODUCT = 'generate_product';

    #[Route(path: '', name: 'generate')]
    public function generate(): Response
    {
        $data = [
            'count' => $this->getSessionInt(self::KEY_COUNT, 1),
            'entity' => $this->getSessionString(self::KEY_ENTITY),
            'simulate' => $this->isSessionBool(self::KEY_SIMULATE, true),
        ];
        $helper = $this->createFormHelper('generate.fields.', $data);
        $helper->field('entity')
            ->updateOption('choice_attr', fn (string $_choice, string $key): array => ['data-key' => $key])->addChoiceType([
                'customer.name' => $this->generateUrl(self::ROUTE_CUSTOMER),
                'calculation.name' => $this->generateUrl(self::ROUTE_CALCULATION),
                'product.name' => $this->generateUrl(self::ROUTE_PRODUCT),
            ]);
        $helper->field('count')
            ->updateAttributes([
                'min' => 1, 'max' => 20,
                'step' => 1,
            ])->addNumberType(0);

        $helper->addCheckboxSimulate()
            ->addCheckboxConfirm($this->translator, $data['simulate']);

        return $this->renderForm('admin/generate.html.twig', [
            'form' => $helper->createForm(),
        ]);
    }

    /**
     * Create one or more calculations with random data.
     */
    #[Route(path: '/calculation', name: 'generate_calculation')]
    public function generateCalculations(Request $request, CalculationGenerator $generator): JsonResponse
    {
        return $this->generateEntities($request, $generator);
    }

    /**
     * Create one or more customers with random data.
     */
    #[Route(path: '/customer', name: 'generate_customer')]
    public function generateCustomers(Request $request, CustomerGenerator $generator): JsonResponse
    {
        return $this->generateEntities($request, $generator);
    }

    /**
     * Create one or more products with random data.
     */
    #[Route(path: '/product', name: 'generate_product')]
    public function generateProducts(Request $request, ProductGenerator $generator): JsonResponse
    {
        return $this->generateEntities($request, $generator);
    }

    /**
     * Generate entities.
     */
    private function generateEntities(Request $request, GeneratorInterface $generator): JsonResponse
    {
        $count = $this->getRequestInt($request, 'count');
        $simulate = $this->getRequestBoolean($request, 'simulate', true);
        $route = (string) $request->attributes->get('_route', self::ROUTE_CUSTOMER);
        $entity = $this->generateUrl($route);

        $this->setSessionValues([
            self::KEY_COUNT => $count,
            self::KEY_SIMULATE => $simulate,
            self::KEY_ENTITY => $entity,
        ]);

        return $generator->generate($count, $simulate);
    }
}
