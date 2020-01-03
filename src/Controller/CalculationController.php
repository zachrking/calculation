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

namespace App\Controller;

use App\DataTables\CalculationBelowDataTable;
use App\DataTables\CalculationDataTable;
use App\DataTables\CalculationDuplicateDataTable;
use App\DataTables\CalculationEmptyDataTable;
use App\Entity\Calculation;
use App\Entity\Category;
use App\Form\CalculationEditStateType;
use App\Form\CalculationType;
use App\Form\FormHelper;
use App\Interfaces\IApplicationService;
use App\Listener\CalculationListener;
use App\Pdf\PdfResponse;
use App\Pivot\Aggregator\SumAggregator;
use App\Pivot\Field\PivotFieldFactory;
use App\Pivot\PivotFactory;
use App\Pivot\PivotTable;
use App\Report\CalculationDuplicateTableReport;
use App\Report\CalculationEmptyTableReport;
use App\Report\CalculationReport;
use App\Report\CalculationsReport;
use App\Service\CalculationService;
use Doctrine\Common\Collections\Criteria;
use Doctrine\Common\EventManager;
use Gedmo\Blameable\BlameableListener;
use Gedmo\Timestampable\TimestampableListener;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for calculation entities.
 *
 * @Route("/calculation")
 * @IsGranted("ROLE_USER")
 */
class CalculationController extends EntityController
{
    /**
     * The delete route.
     */
    public const ROUTE_DELETE = 'calculation_delete';

    /**
     * The list route.
     */
    public const ROUTE_LIST = 'calculation_list';

    /**
     * The edit template.
     */
    public const TEMPLATE_EDIT = 'calculation/calculation_edit.html.twig';

    /**
     * @var CalculationService
     */
    private $calculationService;

    /**
     * Constructor.
     */
    public function __construct(CalculationService $calculationService)
    {
        parent::__construct(Calculation::class);
        $this->calculationService = $calculationService;
    }

    /**
     * Edit a new calculation.
     *
     * @Route("/add", name="calculation_add", methods={"GET", "POST"})
     */
    public function add(Request $request): Response
    {
        // create
        $item = new Calculation();

        // default values
        $userName = $this->getUserName();
        if ($userName) {
            $item->setCreatedBy($userName)
                ->setUpdatedBy($userName);
        }

        // default state
        $state = $this->getApplication()->getDefaultState();
        if ($state) {
            $item->setState($state);
        }

        // edit
        return $this->editItem($request, [
            'item' => $item,
            'below' => false,
        ]);
    }

    /**
     * Find calculations where margins is below the minimum.
     *
     * @Route("/below", name="calculation_below")
     * @IsGranted("ROLE_ADMIN")
     */
    public function below(Request $request): Response
    {
        // get values
        $minMargin = $this->getApplication()->getMinMargin();
        $calculations = $this->getBelowMargin($minMargin);
        $selection = $request->get('selection', 0);
        $edit = $this->getApplication()->isEditAction();

        // parameters
        $parameters = [
            'items' => $calculations,
            'items_count' => \count($calculations),
            'min_margin' => $minMargin,
            'query' => false,
            'selection' => $selection,
            'sortField' => 'id',
            'sortMode' => Criteria::DESC,
            'sortFields' => [],
            'edit' => $edit,
        ];

        return $this->render('calculation/calculation_below.html.twig', $parameters);
    }

    /**
     * Report calculations where margins is below the minimum.
     *
     * @Route("/below/pdf", name="calculation_below_pdf")
     * @IsGranted("ROLE_ADMIN")
     */
    public function belowPdf(Request $request): Response
    {
        $minMargin = $this->getApplication()->getMinMargin();
        $calculations = $this->getBelowMargin($minMargin);
        if (empty($calculations)) {
            $this->warningTrans('below.empty');

            return  $this->redirectToHomePage();
        }

        $percent = $this->localePercent($minMargin);
        $description = $this->trans('below.description', ['%margin%' => $percent]);

        $report = new CalculationsReport($this);
        $report->SetTitleTrans('below.title')
            ->setDescription($description)
            ->setCalculations($calculations);

        return $this->renderDocument($report);
    }

    /**
     * Find calculations where margins is below the minimum.
     *
     * @Route("/below/table", name="calculation_below_table")
     * @IsGranted("ROLE_ADMIN")
     */
    public function belowTable(Request $request, CalculationBelowDataTable $table): Response
    {
        $attributes = [];

        // callback?
        if (!$request->isXmlHttpRequest()) {
            $margin = $this->getApplication()->getMinMargin();
            $margin_text = $this->trans('calculation.list.margin_below', ['%minimum%' => $this->localePercent($margin)]);
            $attributes = [
                'min_margin' => $margin,
                'min_margin_text' => $margin_text,
            ];
        }

        return $this->showTable($request, $table, 'calculation/calculation_table_below.html.twig', $attributes);
    }

    /**
     * List the calculations.
     *
     * @Route("", name="calculation_list", methods={"GET", "POST"})
     */
    public function card(Request $request): Response
    {
        $sortedFields = [
            ['name' => 'id', 'label' => 'calculation.fields.id', 'numeric' => true],
            ['name' => 'date', 'label' => 'calculation.fields.date'],
            ['name' => 'customer', 'label' => 'calculation.fields.customer'],
            ['name' => 'description', 'label' => 'calculation.fields.description'],
            ['name' => 'overallTotal', 'label' => 'calculation.fields.total', 'numeric' => true],
        ];
        $parameters = [
            'min_margin' => $this->getApplication()->getMinMargin(),
        ];

        return $this->renderCard($request, 'calculation/calculation_card.html.twig', 'id', Criteria::DESC, $sortedFields, $parameters);
    }

    /**
     * Edit a copy (cloned) calculation.
     *
     * @Route("/clone/{id}", name="calculation_clone", requirements={"id": "\d+" }, methods={"GET", "POST"})
     */
    public function clone(Request $request, Calculation $item): Response
    {
        // clone
        $state = $this->getApplication()->getDefaultState();
        $userName = $this->getUserName();
        $clone = $item->clone($state, $userName);

        // edit
        return $this->editItem($request, [
            'item' => $clone,
            'below' => $this->isMarginBelow($clone),
        ]);
    }

    /**
     * Delete a calculation.
     *
     * @Route("/delete/{id}", name="calculation_delete", requirements={"id": "\d+" })
     */
    public function delete(Request $request, Calculation $item): Response
    {
        // parameters
        $parameters = [
            'item' => $item,
            'page_list' => self::ROUTE_LIST,
            'page_delete' => self::ROUTE_DELETE,
            'title' => 'calculation.delete.title',
            'message' => 'calculation.delete.message',
            'success' => 'calculation.delete.success',
            'failure' => 'calculation.delete.failure',
        ];

        // delete
        return $this->deletItem($request, $parameters);
    }

    /**
     * Find duplicate items in the calculations.
     *
     * @Route("/duplicate", name="calculation_duplicate")
     * @IsGranted("ROLE_ADMIN")
     */
    public function duplicate(Request $request): Response
    {
        $calculations = $this->getDuplicateItems();
        $selection = $request->get('selection', 0);
        $edit = $this->getApplication()->isEditAction();

        // number of items
        $items_count = \array_reduce($calculations, function (float $carry, array $calculation) {
            foreach ($calculation['items'] as $item) {
                $carry += $item['count'];
            }

            return $carry;
        }, 0);

        // parameters
        $parameters = [
            'items' => $calculations,
            'items_count' => $items_count,
            'query' => false,
            'selection' => $selection,
            'sortField' => 'id',
            'sortMode' => Criteria::DESC,
            'sortFields' => [],
            'edit' => $edit,
        ];

        return $this->render('calculation/calculation_duplicate.html.twig', $parameters);
    }

    /**
     * Report for duplicate items in the calculations.
     *
     * @Route("/duplicate/pdf", name="calculation_duplicate_pdf")
     * @IsGranted("ROLE_ADMIN")
     */
    public function duplicatePdf(Request $request): Response
    {
        // $request->

        $items = $this->getDuplicateItems();
        if (empty($items)) {
            $this->warningTrans('duplicate.empty');

            return  $this->redirectToHomePage();
        }

        $report = new CalculationDuplicateTableReport($this);
        $report->setItems($items);

        return $this->renderDocument($report);
    }

    /**
     * Display the duplicate items in the calculations.
     *
     * @Route("/duplicate/table", name="calculation_duplicate_table")
     * @IsGranted("ROLE_ADMIN")
     */
    public function duplicateTable(Request $request, CalculationDuplicateDataTable $table): Response
    {
        $results = $table->handleRequest($request);
        if ($table->isCallback()) {
            return $this->json($results);
        }

        // attributes
        $attributes = [
            'edit-action' => \json_encode($this->getApplication()->isEditAction()),
            'itemsCount' => $table->getItemCounts(),
        ];

        // parameters
        $parameters = [
            'results' => $results,
            'attributes' => $attributes,
            'columns' => $table->getColumns(),
        ];

        return $this->render('calculation/calculation_table_duplicate.html.twig', $parameters);
    }

    /**
     * Edit a calculation.
     *
     * @Route("/edit/{id}", name="calculation_edit", requirements={"id": "\d+" }, methods={"GET", "POST"})
     */
    public function edit(Request $request, Calculation $item): Response
    {
        return $this->editItem($request, [
            'item' => $item,
            'below' => $this->isMarginBelow($item),
        ]);
    }

    /**
     * Find empty items in the calculations. Items are empty if the price or the quantity is equal to 0.
     *
     * @Route("/empty", name="calculation_empty")
     * @IsGranted("ROLE_ADMIN")
     */
    public function empty(Request $request): Response
    {
        $calculations = $this->getEmptyItems();
        $selection = $request->get('selection', 0);
        $edit = $this->getApplication()->isEditAction();

        // number of items
        $items_count = \array_reduce($calculations, function (float $carry, array $calculation) {
            return $carry + \count($calculation['items']);
        }, 0);

        // parameters
        $parameters = [
            'items' => $calculations,
            'items_count' => $items_count,
            'query' => false,
            'selection' => $selection,
            'sortField' => 'id',
            'sortMode' => Criteria::DESC,
            'sortFields' => [],
            'edit' => $edit,
        ];

        return $this->render('calculation/calculation_empty.html.twig', $parameters);
    }

    /**
     * Report for empty items in the calculations.
     *
     * @Route("/empty/pdf", name="calculation_empty_pdf")
     * @IsGranted("ROLE_ADMIN")
     */
    public function emptyPdf(Request $request): Response
    {
        $items = $this->getEmptyItems();
        if (empty($items)) {
            $this->warningTrans('empty.empty');

            return  $this->redirectToHomePage();
        }

        //$report = new CalculationEmptyReport($this);
        $report = new CalculationEmptyTableReport($this);
        $report->setItems($items);

        return $this->renderDocument($report);
    }

    /**
     * Display the duplicate items in the calculations.
     *
     * @Route("/empty/table", name="calculation_empty_table")
     * @IsGranted("ROLE_ADMIN")
     */
    public function emptyTable(Request $request, CalculationEmptyDataTable $table): Response
    {
        $results = $table->handleRequest($request);
        if ($table->isCallback()) {
            return $this->json($results);
        }

        // attributes
        $attributes = [
            'edit-action' => \json_encode($this->getApplication()->isEditAction()),
            'itemsCount' => $table->getItemCounts(),
        ];

        // parameters
        $parameters = [
            'results' => $results,
            'attributes' => $attributes,
            'columns' => $table->getColumns(),
        ];

        return $this->render('calculation/calculation_table_empty.html.twig', $parameters);
    }

    /**
     * Export calculations to a PDF document.
     *
     * @Route("/pdf", name="calculation_pdf")
     */
    public function pdf(Request $request): PdfResponse
    {
        // get calculations
        $calculations = $this->getRepository()->findAll();
        if (empty($calculations)) {
            $message = $this->trans('calculation.list.empty');

            throw $this->createNotFoundException($message);
        }

        // create and render report
        $grouped = (bool) $request->get('grouped', true);
        $report = new CalculationsReport($this);
        $report->setCalculations($calculations)
            ->setGrouped($grouped);

        return $this->renderDocument($report);
    }

    /**
     * Export a single calculation to a PDF document.
     *
     * @Route("/pdf/{id}", name="calculation_pdf_id", requirements={"id": "\d+" })
     */
    public function pdfById(Request $request, Calculation $calculation): PdfResponse
    {
        // create and render report
        $report = new CalculationReport($this);
        $report->setCalculation($calculation);

        return $this->renderDocument($report);
    }

    /**
     * Export pivot data to JSON.
     *
     * @Route("/pivot", name="calculation_pivot", methods={"GET", "POST"})
     */
    public function pivot(Request $request): Response
    {
        // table
        $table = $this->getPivotTable();

        return $this->render('calculation/calculation_pivot.html.twig', ['table' => $table]);
    }

    /**
     * Export pivot data to CSV.
     *
     * @Route("/pivot/export", name="calculation_pivot_export", methods={"GET", "POST"})
     */
    public function pivotExport(Request $request): Response
    {
        try {
            // load data
            $dataset = $this->getPivotData();

            // callback
            $callback = function () use ($dataset): void {
                // data?
                if (\count($dataset)) {
                    // open
                    $handle = \fopen('php://output', 'w+');

                    // headers
                    \fputcsv($handle, \array_keys($dataset[0]), ';');

                    // rows
                    foreach ($dataset as $row) {
                        // convert
                        $row['calculation_date'] = $this->localeDate($row['calculation_date']);
                        $row['calculation_overall_margin'] = \round($row['calculation_overall_margin'], 3);
                        $row['item_total'] = \round($row['item_total'], 2);

                        \fputcsv($handle, $row, ';');
                    }

                    // close
                    \fclose($handle);
                }
            };

            // create response
            $response = StreamedResponse::create($callback);

            // headers
            $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'data.csv');
            $response->headers->set('Content-Type', 'text/csv');
            $response->headers->set('Content-Disposition', $disposition);

            return $response;
        } catch (\Exception $e) {
            return $this->jsonException($e);
        }
    }

    /**
     * Export pivot data to JSON.
     *
     * @return Response
     * @Route("/pivot/json", name="calculation_pivot_json", methods={"GET", "POST"})
     */
    public function pivotJson(Request $request): JsonResponse
    {
        try {
            // create table
            $table = $this->getPivotTable();

            return JsonResponse::create($table);
        } catch (\Exception $e) {
            return $this->jsonException($e);
        }
    }

    /**
     * Show properties of a calculation.
     *
     * @Route("/show/{id}", name="calculation_show", requirements={"id": "\d+" }, methods={"GET", "POST"})
     */
    public function show(Calculation $item): Response
    {
        $parameters = [
            'min_margin' => $this->getApplication()->getMinMargin(),
            'duplicate_items' => $item->hasDuplicateItems(),
            'emty_items' => $item->hasEmptyItems(),
        ];

        return $this->showItem('calculation/calculation_show.html.twig', $item, $parameters);
    }

    /**
     * Edit the state of a calculation.
     *
     * @Route("/state/{id}", name="calculation_state", requirements={"id": "\d+" }, methods={"GET", "POST"})
     */
    public function state(Request $request, Calculation $item): Response
    {
        $form = $this->createForm(CalculationEditStateType::class, $item);
        if ($this->handleFormRequest($form, $request)) {
            // update
            $this->getManager()->flush();

            // message
            $this->succesTrans('calculation.state.success', ['%name%' => $item->getDisplay()]);

            // redirect
            $id = $item->getId();

            return $this->getUrlGenerator()->redirect($request, $id, self::ROUTE_LIST);
        }

        // display
        return $this->render('calculation/calculation_state.html.twig', [
            'form' => $form->createView(),
            'item' => $item,
        ]);
    }

    /**
     * Render the table view.
     *
     * @Route("/table", name="calculation_table", methods={"GET", "POST"})
     */
    public function table(Request $request, CalculationDataTable $table): Response
    {
        $attributes = [];

        // callback?
        if (!$request->isXmlHttpRequest()) {
            // attributes
            $margin = $this->getApplication()->getMinMargin();
            $margin_text = $this->trans('calculation.list.margin_below', ['%minimum%' => $this->localePercent($margin)]);
            $attributes = [
                'min_margin' => $margin,
                'min_margin_text' => $margin_text,
            ];
        }

        return $this->showTable($request, $table, 'calculation/calculation_table.html.twig', $attributes);
    }

    /**
     * Update calculation totals.
     *
     * @Route("/update", name="calculation_update", methods={"GET", "POST"})
     * @IsGranted("ROLE_ADMIN")
     */
    public function update(Request $request, LoggerInterface $logger): Response
    {
        // create form
        $builder = $this->createFormBuilder();

        // fields
        $helper = new FormHelper($builder);
        $helper->field('includeClosed')
            ->label('update.includeClosed')
            ->notRequired()
            ->addCheckboxType();

        // handle request
        $form = $builder->getForm();
        if ($this->handleFormRequest($form, $request)) {
            $data = $form->getData();
            $includeClosed = (bool) $data['includeClosed'];

            $updated = 0;
            $skipped = 0;
            $unmodifiable = 0;

            $suspended = $this->disableListeners();

            /** @var Calculation[] $calculations */
            $calculations = $this->getRepository()->findAll();
            foreach ($calculations as $calculation) {
                if ($includeClosed || $calculation->isEditable()) {
                    if ($this->calculationService->updateTotal($calculation)) {
                        ++$updated;
                    } else {
                        ++$skipped;
                    }
                } else {
                    ++$unmodifiable;
                }
            }

            if ($updated > 0) {
                $this->getManager()->flush();
            }
            $this->enableListeners($suspended);
            $total = \count($calculations);

            // update last update
            $this->getApplication()->setProperties([IApplicationService::LAST_UPDATE => new \DateTime()]);

            // log results
            if (null !== $logger) {
                $context = [
                    $this->trans('update.updated') => $updated,
                    $this->trans('update.skipped') => $skipped,
                    $this->trans('update.unmodifiable') => $unmodifiable,
                    $this->trans('update.total') => $total,
                ];
                $message = $this->trans('update.title');
                $logger->info($message, $context);
            }

            // display results
            $data = [
                'updated' => $updated,
                'skipped' => $skipped,
                'unmodifiable' => $unmodifiable,
                'total' => $total,
            ];

            return $this->render('calculation/calculation_result.html.twig', $data);
        }

        // display
        return $this->render('calculation/calculation_update.html.twig', [
            'lastUpdate' => $this->getApplication()->getLastUpdate(),
            'form' => $form->createView(),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function editItem(Request $request, array $parameters): Response
    {
        /** @var Calculation $item */
        $item = $parameters['item'];

        // $parameters['title'] = $item->isNew() ? 'calculation.add.title' : 'calculation.edit.title_short';
        $parameters['type'] = CalculationType::class;
        $parameters['template'] = self::TEMPLATE_EDIT;
        $parameters['route'] = self::ROUTE_LIST;
        $parameters['success'] = $item->isNew() ? 'calculation.add.success' : 'calculation.edit.success';
        $parameters['groups'] = $this->calculationService->createGroupsFromCalculation($item);
        $parameters['min_margin'] = $this->getApplication()->getMinMargin();
        $parameters['duplicate_items'] = $item->hasDuplicateItems();
        $parameters['emty_items'] = $item->hasEmptyItems();

        if ($parameters['editable'] = $item->isEditable()) {
            $parameters['groupIndex'] = $item->getGroupsCount();
            $parameters['itemIndex'] = $item->getLinesCount();
            $parameters['categories'] = $this->getCategories();
            $parameters['grouping'] = $this->getApplication()->getGrouping();
            $parameters['decimal'] = $this->getApplication()->getDecimal();
        }

        return parent::editItem($request, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @param Calculation $item
     */
    protected function updateItem($item): bool
    {
        // compute total
        $this->calculationService->updateTotal($item);

        return parent::updateItem($item);
    }

    /**
     * Disabled doctrine event listeners.
     *
     * @return array an array containing the event names and listerners
     */
    private function disableListeners(): array
    {
        $suspended = [];
        $manager = $this->getEventManager();
        foreach ($manager->getListeners() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof TimestampableListener
                    || $listener instanceof BlameableListener
                    || $listener instanceof CalculationListener) {
                    $suspended[$event][] = $listener;
                    $manager->removeEventListener($event, $listener);
                }
            }
        }

        return $suspended;
    }

    /**
     *  Enabled doctrine event listeners.
     *
     * @param array $suspended the event names and listeners to activate
     */
    private function enableListeners(array $suspended): void
    {
        $manager = $this->getEventManager();
        foreach ($suspended as $event => $listeners) {
            foreach ($listeners as $listener) {
                $manager->addEventListener($event, $listener);
            }
        }
    }

    /**
     * Gets calculations with the overall margin below the given value.
     *
     * @param float $minMargin the minimum margin
     *
     * @return Calculation[] the below calculations
     */
    private function getBelowMargin(float $minMargin): array
    {
        /** @var \App\Repository\CalculationRepository $repository */
        $repository = $this->getRepository();

        return $repository->getBelowMargin($minMargin);
    }

    /**
     * Gets the categories.
     *
     * @return array
     */
    private function getCategories()
    {
        return $this->getManager()
            ->getRepository(Category::class)
            ->getList();
    }

    /**
     * Gets the duplicate items.
     */
    private function getDuplicateItems(): array
    {
        /** @var \App\Repository\CalculationRepository $repository */
        $repository = $this->getRepository();

        return $repository->getDuplicateItems();
    }

    /**
     * Gets the empty items.
     */
    private function getEmptyItems(): array
    {
        /** @var \App\Repository\CalculationRepository $repository */
        $repository = $this->getRepository();

        return $repository->getEmptyItems();
    }

    /**
     * Gets the doctrine event manager.
     *
     * @return EventManager the event manager
     */
    private function getEventManager(): EventManager
    {
        return $this->getManager()->getEventManager();
    }

    /**
     * Gets the pivot data.
     */
    private function getPivotData(): array
    {
        /** @var \App\Repository\CalculationRepository $repository */
        $repository = $this->getRepository();

        return $repository->getPivot();
    }

    /**
     * Gets the pivot table.
     */
    private function getPivotTable(): PivotTable
    {
        // fields
        $key = PivotFieldFactory::integer('calculation_id');
        // $data = PivotFieldFactory::float('item_total');
        $data = PivotFieldFactory::float('calculation_overall_total');
        $rows = [
            PivotFieldFactory::default('calculation_state'),
            PivotFieldFactory::default('item_group'),
        ];
        $columns = [
            PivotFieldFactory::year('calculation_date'),
            PivotFieldFactory::month('calculation_date'),
        ];

        $dataset = $this->getPivotData();
        $title = $this->trans('calculation.list.title');

        // create pivot table
        $table = PivotFactory::instance($dataset, $title)
            //->setAggregatorClass(AverageAggregator::class)
            //->setAggregatorClass(CountAggregator::class)
            ->setAggregatorClass(SumAggregator::class)

            ->setColumnFields($columns)
            ->setRowFields($rows)
            ->setDataField($data)
            ->setKeyField($key)
            ->create();

        $table->getColumn()->setTitle('Année \ Mois');
        $table->getRow()->setTitle('Statut \ Catégory');
        //$table->setTotalTitle('Moyenne');
        //$table->setTotalTitle('Nombre');

        return $table;
    }

//     /**
//      * Gets the search calculation states.
//      *
//      * @return array
//      */
//     private function getSearchStates()
//     {
//         // get states containing calculations
//         $states = $this->getDoctrine()
//             ->getRepository(CalculationState::class)
//             ->getNotEmptyList();

//         // add "ALL" state at the first place
//         $all = [
//             'id' => 0,
//             'code' => $this->trans('calculation.list.state_all_text'),
//             'description' => $this->trans('calculation.list.state_all_description'),
//         ];
//         \array_unshift($states, $all);

//         return $states;
//     }

    /**
     * Returns if the given calculation has an overall margin below the minimum.
     *
     * @param Calculation $calculation the calculation to verify
     *
     * @return bool true if below
     */
    private function isMarginBelow(Calculation $calculation): bool
    {
        return  $this->getApplication()->isMarginBelow($calculation->getOverallMargin());
    }
}
