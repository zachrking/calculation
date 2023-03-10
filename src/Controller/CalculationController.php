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

use App\Entity\AbstractEntity;
use App\Entity\Calculation;
use App\Form\Calculation\CalculationEditStateType;
use App\Form\Calculation\CalculationType;
use App\Form\Dialog\EditItemDialogType;
use App\Form\Dialog\EditTaskDialogType;
use App\Interfaces\RoleInterface;
use App\Report\CalculationReport;
use App\Report\CalculationsReport;
use App\Repository\CalculationRepository;
use App\Repository\TaskRepository;
use App\Response\PdfResponse;
use App\Response\SpreadsheetResponse;
use App\Service\CalculationService;
use App\Spreadsheet\CalculationDocument;
use App\Spreadsheet\CalculationsDocument;
use App\Table\CalculationTable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for calculation entities.
 *
 * @template-extends AbstractEntityController<Calculation>
 */
#[AsController]
#[Route(path: '/calculation')]
#[IsGranted(RoleInterface::ROLE_USER)]
class CalculationController extends AbstractEntityController
{
    /**
     * Constructor.
     *
     * @throws \ReflectionException
     */
    public function __construct(CalculationRepository $repository, private readonly CalculationService $service, private readonly TaskRepository $taskRepository)
    {
        parent::__construct($repository);
    }

    /**
     * Add a new calculation.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    #[Route(path: '/add', name: 'calculation_add')]
    public function add(Request $request): Response
    {
        $application = $this->getApplication();
        $state = $application->getDefaultState();
        $product = $application->getDefaultProduct();
        $quantity = $application->getDefaultQuantity();
        // create
        $item = new Calculation();
        // update
        if (null !== $state) {
            $item->setState($state);
        }
        if (null !== $product) {
            $item->addProduct($product, $quantity);
            $this->service->updateTotal($item);
        }
        $parameters = ['overall_below' => $this->isMarginBelow($item)];

        return $this->editEntity($request, $item, $parameters);
    }

    /**
     * Edit a copy (cloned) calculation.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    #[Route(path: '/clone/{id}', name: 'calculation_clone', requirements: ['id' => Requirement::DIGITS])]
    public function clone(Request $request, Calculation $item): Response
    {
        // clone
        $description = $this->trans('common.clone_description', ['%description%' => $item->getDescription()]);
        $state = $this->getApplication()->getDefaultState();
        $clone = $item->clone($state, $description);
        $parameters = [
            'params' => ['id' => $item->getId()],
            'overall_below' => $this->isMarginBelow($clone),
        ];

        return $this->editEntity($request, $clone, $parameters);
    }

    /**
     * Delete a calculation.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     */
    #[Route(path: '/delete/{id}', name: 'calculation_delete', requirements: ['id' => Requirement::DIGITS])]
    public function delete(Request $request, Calculation $item, LoggerInterface $logger): Response
    {
        return $this->deleteEntity($request, $item, $logger);
    }

    /**
     * Edit a calculation.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    #[Route(path: '/edit/{id}', name: 'calculation_edit', requirements: ['id' => Requirement::DIGITS])]
    public function edit(Request $request, Calculation $item): Response
    {
        $parameters = ['overall_below' => $this->isMarginBelow($item)];

        return $this->editEntity($request, $item, $parameters);
    }

    /**
     * Export the calculations to a Spreadsheet document.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no calculation is found
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/excel', name: 'calculation_excel')]
    public function excel(): SpreadsheetResponse
    {
        $entities = $this->getEntities('id');
        if (empty($entities)) {
            $message = $this->trans('calculation.list.empty');
            throw $this->createNotFoundException($message);
        }
        $doc = new CalculationsDocument($this, $entities);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export a single calculation to a Spreadsheet document.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/excel/{id}', name: 'calculation_excel_id', requirements: ['id' => Requirement::DIGITS])]
    public function excelById(Calculation $calculation): SpreadsheetResponse
    {
        $doc = new CalculationDocument($this, $calculation);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export calculations to a PDF document.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no calculation is found
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/pdf', name: 'calculation_pdf')]
    public function pdf(Request $request): PdfResponse
    {
        $entities = $this->getEntities('id');
        if (empty($entities)) {
            $message = $this->trans('calculation.list.empty');
            throw $this->createNotFoundException($message);
        }
        $grouped = $this->getRequestBoolean($request, 'grouped', true);
        $doc = new CalculationsReport($this, $entities, $grouped);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Export a single calculation to a PDF document.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/pdf/{id}', name: 'calculation_pdf_id', requirements: ['id' => Requirement::DIGITS])]
    public function pdfById(Calculation $calculation, UrlGeneratorInterface $generator, LoggerInterface $logger): PdfResponse
    {
        $qrcode = $this->getQrCode($generator, $calculation);
        $doc = new CalculationReport($this, $logger, $calculation, $qrcode);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Show properties of a calculation.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    #[Route(path: '/show/{id}', name: 'calculation_show', requirements: ['id' => Requirement::DIGITS])]
    public function show(Calculation $item): Response
    {
        $parameters = [
            'min_margin' => $this->getApplication()->getMinMargin(),
            'duplicate_items' => $item->hasDuplicateItems(),
            'empty_items' => $item->hasEmptyItems(),
        ];

        return $this->showEntity($item, $parameters);
    }

    /**
     * Edit the state of a calculation.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/state/{id}', name: 'calculation_state', requirements: ['id' => Requirement::DIGITS])]
    public function state(Request $request, Calculation $item, EntityManagerInterface $manager): Response
    {
        $oldState = $item->getState();
        $form = $this->createForm(CalculationEditStateType::class, $item);
        if ($this->handleRequestForm($request, $form)) {
            // change?
            if ($oldState !== $item->getState()) {
                // update
                $manager->flush();
            }

            // message
            $this->successTrans('calculation.state.success', ['%name%' => $item->getDisplay()]);

            return $this->getUrlGenerator()->redirect($request, $item->getId(), $this->getDefaultRoute());
        }
        // parameters
        $parameters = [
            'form' => $form,
            'item' => $item,
        ];
        $this->updateQueryParameters($request, $parameters, (int) $item->getId());
        // display
        return $this->render('calculation/calculation_state.html.twig', $parameters);
    }

    /**
     * Render the table view.
     *
     * @throws \ReflectionException
     */
    #[Route(path: '', name: 'calculation_table')]
    public function table(Request $request, CalculationTable $table, LoggerInterface $logger): Response
    {
        return $this->handleTableRequest($request, $table, 'calculation/calculation_table.html.twig', $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @param Calculation $item
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    protected function editEntity(Request $request, AbstractEntity $item, array $parameters = []): Response
    {
        /* @var Calculation $item */
        $parameters['groups'] = $this->service->createGroupsFromCalculation($item);
        $parameters['min_margin'] = $this->getApplication()->getMinMargin();
        $parameters['duplicate_items'] = $item->hasDuplicateItems();
        $parameters['empty_items'] = $item->hasEmptyItems();

        // editable?
        if ($parameters['editable'] = $item->isEditable()) {
            $parameters['group_index'] = $item->getGroupsCount();
            $parameters['category_index'] = $item->getCategoriesCount();
            $parameters['item_index'] = $item->getLinesCount();
            $parameters['tasks'] = $this->getTasks();
            $parameters['item_dialog'] = $this->createForm(EditItemDialogType::class);
            $parameters['task_dialog'] = $this->createForm(EditTaskDialogType::class);
        }

        return parent::editEntity($request, $item, $parameters);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return CalculationType::class;
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-param Calculation $item
     *
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    protected function saveToDatabase(AbstractEntity $item): void
    {
        $this->service->updateTotal($item);
        parent::saveToDatabase($item);
    }

    /**
     * Gets the QR-code for the given calculation.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    private function getQrCode(UrlGeneratorInterface $generator, Calculation $calculation): ?string
    {
        if ($this->getUserService()->isQrCode()) {
            $name = 'calculation_show';
            $parameters = ['id' => (int) $calculation->getId()];

            return $generator->generate($name, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        }

        return null;
    }

    private function getTasks(): array
    {
        return $this->taskRepository->getSortedBuilder(false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns if the given calculation has an overall margin below the minimum.
     *
     * @param Calculation $calculation the calculation to verify
     *
     * @return bool true if margin is below
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    private function isMarginBelow(Calculation $calculation): bool
    {
        $margin = $this->getApplication()->getMinMargin();

        return $calculation->isMarginBelow($margin);
    }
}
