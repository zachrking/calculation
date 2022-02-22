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

namespace App\Controller;

use App\BootstrapTable\CalculationDuplicateTable;
use App\Report\CalculationDuplicateReport;
use App\Repository\CalculationRepository;
use App\Spreadsheet\CalculationsDuplicateDocument;
use App\Traits\TableTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use SlopeIt\BreadcrumbBundle\Annotation\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller to display and export duplicate items in the calculations.
 *
 * @author Laurent Muller
 *
 * @Route("/duplicate")
 * @IsGranted("ROLE_ADMIN")
 * @Breadcrumb({
 *     {"label" = "index.title", "route" = "homepage"}
 * })
 */
class CalculationDuplicateController extends AbstractController
{
    use TableTrait;

    /**
     * Export the duplicate items to a Spreadsheet document.
     *
     * @Route("/excel", name="duplicate_excel")
     */
    public function excel(CalculationRepository $repository): Response
    {
        if ($this->isEmptyItems($repository)) {
            $this->warningTrans('duplicate.empty');

            return $this->redirectToHomePage();
        }

        $items = $this->getItems($repository);
        $doc = new CalculationsDuplicateDocument($this, $items);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Exports the duplicate items in the calculations.
     *
     * @Route("/pdf", name="duplicate_pdf")
     */
    public function pdf(CalculationRepository $repository): Response
    {
        if ($this->isEmptyItems($repository)) {
            $this->warningTrans('duplicate.empty');

            return $this->redirectToHomePage();
        }

        $items = $this->getItems($repository);
        $doc = new CalculationDuplicateReport($this, $items);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Render the table view.
     *
     * @Route("", name="duplicate_table")
     * @Breadcrumb({
     *     {"label" = "duplicate.title"}
     * })
     */
    public function table(Request $request, CalculationDuplicateTable $table): Response
    {
        return $this->handleTableRequest($request, $table, 'calculation/calculation_table_duplicate.html.twig');
    }

    /**
     * Gets items to display.
     *
     * @psalm-return array<int, array{
     *      id: int,
     *      date: \DateTimeInterface,
     *      stateCode: string,
     *      customer: string,
     *      description: string,
     *      items: array{
     *          description: string,
     *          quantity: float,
     *          price: float,
     *          count: int}
     *      }>
     */
    private function getItems(CalculationRepository $repository): array
    {
        return $repository->getDuplicateItems();
    }

    /**
     * Returns a value indicating if no item is duplicated.
     */
    private function isEmptyItems(CalculationRepository $repository): bool
    {
        return 0 === $repository->countDuplicateItems();
    }
}
