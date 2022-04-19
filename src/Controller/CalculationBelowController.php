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

use App\Entity\Calculation;
use App\Report\CalculationsReport;
use App\Repository\CalculationRepository;
use App\Spreadsheet\CalculationsDocument;
use App\Table\CalculationBelowTable;
use App\Traits\TableTrait;
use App\Util\FormatUtils;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for calculations where margins are below the minimum.
 *
 * @author Laurent Muller
 *
 * @Route("/below")
 * @IsGranted("ROLE_ADMIN")
 */
class CalculationBelowController extends AbstractController
{
    use TableTrait;

    /**
     * Export the calculations to a Spreadsheet document.
     *
     * @Route("/excel", name="below_excel")
     */
    public function excel(CalculationRepository $repository): Response
    {
        $minMargin = $this->getApplication()->getMinMargin();
        $items = $this->getItems($repository, $minMargin);
        if (empty($items)) {
            $this->warningTrans('below.empty');

            return $this->redirectToHomePage();
        }

        $doc = new CalculationsDocument($this, $items);
        $doc->setTitle('below.title');

        return $this->renderSpreadsheetDocument($doc);
    }

    /**
     * Export calculations to a PDF document.
     *
     * @Route("/pdf", name="below_pdf")
     */
    public function pdf(CalculationRepository $repository): Response
    {
        $minMargin = $this->getApplication()->getMinMargin();
        $items = $this->getItems($repository, $minMargin);
        if (empty($items)) {
            $this->warningTrans('below.empty');

            return $this->redirectToHomePage();
        }

        $percent = FormatUtils::formatPercent($minMargin);
        $description = $this->trans('below.description', ['%margin%' => $percent]);

        $doc = new CalculationsReport($this, $items);
        $doc->setTitleTrans('below.title');
        $doc->getHeader()->setDescription($description);

        return $this->renderPdfDocument($doc);
    }

    /**
     * Render the table view.
     *
     * @Route("", name="below_table")
     */
    public function table(Request $request, CalculationBelowTable $table): Response
    {
        return $this->handleTableRequest($request, $table, 'calculation/calculation_table_below.html.twig');
    }

    /**
     * Gets items to display.
     *
     * @return Calculation[]
     */
    private function getItems(CalculationRepository $repository, float $minMargin): array
    {
        return $repository->getBelowMargin($minMargin);
    }
}
