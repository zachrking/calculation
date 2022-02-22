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

use App\BootstrapTable\CalculationEmptyTable;
use App\Report\CalculationEmptyReport;
use App\Repository\CalculationRepository;
use App\Spreadsheet\CalculationsEmptyDocument;
use App\Traits\TableTrait;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use SlopeIt\BreadcrumbBundle\Annotation\Breadcrumb;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for calculations where items has the price or the quantity is equal to 0.
 *
 * @author Laurent Muller
 *
 * @Route("/empty")
 * @IsGranted("ROLE_ADMIN")
 * @Breadcrumb({
 *     {"label" = "index.title", "route" = "homepage" }
 * })
 */
class CalculationEmptyController extends AbstractController
{
    use TableTrait;

    /**
     * Export the empty items to a Spreadsheet document.
     *
     * @Route("/excel", name="empty_excel")
     */
    public function excel(CalculationRepository $repository): Response
    {
        if ($this->isEmptyItems($repository)) {
            $this->warningTrans('empty.empty');

            return $this->redirectToHomePage();
        }

        $items = $this->getItems($repository);
        $doc = new CalculationsEmptyDocument($this, $items);

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export the calculations where items has the price or the quantity is equal to 0.
     *
     * @Route("/pdf", name="empty_pdf")
     */
    public function pdf(CalculationRepository $repository): Response
    {
        if ($this->isEmptyItems($repository)) {
            $this->warningTrans('empty.empty');

            return $this->redirectToHomePage();
        }

        $items = $this->getItems($repository);
        $doc = new CalculationEmptyReport($this, $items);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Render the table view.
     *
     * @Route("", name="empty_table")
     * @Breadcrumb({
     *     {"label" = "empty.title" }
     * })
     */
    public function table(Request $request, CalculationEmptyTable $table): Response
    {
        return $this->handleTableRequest($request, $table, 'calculation/calculation_table_empty.html.twig');
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
        return $repository->getEmptyItems();
    }

    /**
     * Returns a value indicating if no item is empty.
     */
    private function isEmptyItems(CalculationRepository $repository): bool
    {
        return 0 === $repository->countEmptyItems();
    }
}
