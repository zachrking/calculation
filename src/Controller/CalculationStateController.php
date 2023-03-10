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
use App\Entity\CalculationState;
use App\Form\CalculationState\CalculationStateType;
use App\Interfaces\RoleInterface;
use App\Report\CalculationStatesReport;
use App\Repository\CalculationRepository;
use App\Repository\CalculationStateRepository;
use App\Response\PdfResponse;
use App\Response\SpreadsheetResponse;
use App\Spreadsheet\CalculationStatesDocument;
use App\Table\CalculationStateTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller for calculation state entities.
 *
 * @template-extends AbstractEntityController<CalculationState>
 */
#[AsController]
#[Route(path: '/calculationstate')]
#[IsGranted(RoleInterface::ROLE_USER)]
class CalculationStateController extends AbstractEntityController
{
    /**
     * Constructor.
     *
     * @throws \ReflectionException
     */
    public function __construct(CalculationStateRepository $repository)
    {
        parent::__construct($repository);
    }

    /**
     * Add a new calculation state.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/add', name: 'calculationstate_add')]
    public function add(Request $request): Response
    {
        return $this->editEntity($request, new CalculationState());
    }

    /**
     * Clone (copy) a calculation state.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/clone/{id}', name: 'calculationstate_clone', requirements: ['id' => Requirement::DIGITS])]
    public function clone(Request $request, CalculationState $item): Response
    {
        $code = $this->trans('common.clone_description', ['%description%' => $item->getCode()]);
        $clone = $item->clone($code);

        return $this->editEntity($request, $clone);
    }

    /**
     * Delete a calculation state.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \ReflectionException
     * @throws \Doctrine\ORM\Exception\ORMException
     */
    #[Route(path: '/delete/{id}', name: 'calculationstate_delete', requirements: ['id' => Requirement::DIGITS])]
    public function delete(Request $request, CalculationState $item, CalculationRepository $repository, LoggerInterface $logger): Response
    {
        // calculation?
        $calculations = $repository->countStateReferences($item);
        if (0 !== $calculations) {
            $display = $item->getDisplay();
            $calculationsText = $this->trans('counters.calculations_lower', ['count' => $calculations]);
            $message = $this->trans('calculationstate.delete.failure', [
                '%name%' => $display,
                '%calculations%' => $calculationsText,
                ]);
            $parameters = [
                'item' => $item,
                'id' => $item->getId(),
                'title' => 'calculationstate.delete.title',
                'message' => $message,
                'back_page' => $this->getDefaultRoute(),
                'back_text' => 'common.button_back_list',
            ];

            return $this->render('cards/card_warning.html.twig', $parameters);
        }

        return $this->deleteEntity($request, $item, $logger);
    }

    /**
     * Edit a calculation state.
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/edit/{id}', name: 'calculationstate_edit', requirements: ['id' => Requirement::DIGITS])]
    public function edit(Request $request, CalculationState $item): Response
    {
        return $this->editEntity($request, $item);
    }

    /**
     * Export the calculation states to a Spreadsheet document.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no calculation state is found
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/excel', name: 'calculationstate_excel')]
    public function excel(): SpreadsheetResponse
    {
        $entities = $this->getEntities('code');
        if (empty($entities)) {
            $message = $this->trans('calculationstate.list.empty');
            throw $this->createNotFoundException($message);
        }
        $doc = new CalculationStatesDocument($this, $entities);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export the calculation states to a PDF document.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException if no calculation state is found
     * @throws \Doctrine\ORM\Exception\ORMException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    #[Route(path: '/pdf', name: 'calculationstate_pdf')]
    public function pdf(): PdfResponse
    {
        $entities = $this->getEntities('code');
        if (empty($entities)) {
            $message = $this->trans('calculationstate.list.empty');
            throw $this->createNotFoundException($message);
        }
        $doc = new CalculationStatesReport($this, $entities);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Show properties of a calculation state.
     */
    #[Route(path: '/show/{id}', name: 'calculationstate_show', requirements: ['id' => Requirement::DIGITS])]
    public function show(CalculationState $item): Response
    {
        return $this->showEntity($item);
    }

    /**
     * Render the table view.
     *
     * @throws \ReflectionException
     */
    #[Route(path: '', name: 'calculationstate_table')]
    public function table(Request $request, CalculationStateTable $table, LoggerInterface $logger): Response
    {
        return $this->handleTableRequest($request, $table, 'calculationstate/calculationstate_table.html.twig', $logger);
    }

    /**
     * {@inheritdoc}
     *
     * @psalm-param CalculationState $item
     *
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function deleteFromDatabase(AbstractEntity $item): void
    {
        $this->getApplication()->updateDeletedState($item);
        parent::deleteFromDatabase($item);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEditFormType(): string
    {
        return CalculationStateType::class;
    }
}
