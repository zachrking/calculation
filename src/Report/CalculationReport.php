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

namespace App\Report;

use App\Controller\AbstractController;
use App\Entity\Calculation;
use App\Pdf\Enums\PdfImageType;
use App\Pdf\Enums\PdfMove;
use App\Pdf\Enums\PdfTextAlignment;
use App\Pdf\PdfBorder;
use App\Pdf\PdfColumn;
use App\Pdf\PdfStyle;
use App\Pdf\PdfTableBuilder;
use App\Traits\LoggerTrait;
use App\Util\FileUtils;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeNone;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;

/**
 * Report for a calculation.
 */
class CalculationReport extends AbstractReport
{
    use LoggerTrait;

    private readonly float $minMargin;

    /**
     * Constructor.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws \Psr\Container\ContainerExceptionInterface
     */
    public function __construct(AbstractController $controller, private readonly LoggerInterface $logger, private readonly Calculation $calculation, private readonly ?string $qrcode = null)
    {
        parent::__construct($controller);
        $this->minMargin = $controller->getApplication()->getMinMargin();
    }

    /**
     * Gets the calculation.
     */
    public function getCalculation(): Calculation
    {
        return $this->calculation;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Gets the minimum allowed margin.
     */
    public function getMinMargin(): float
    {
        return $this->minMargin;
    }

    public function Header(): void
    {
        parent::Header();
        $this->renderCalculation();
    }

    /**
     * {@inheritdoc}
     *
     * @throws \ReflectionException
     */
    public function render(): bool
    {
        $calculation = $this->calculation;

        // update title
        if ($calculation->isNew()) {
            $this->setTitleTrans('calculation.add.title');
        } else {
            $id = $calculation->getFormattedId();
            $this->setTitleTrans('calculation.edit.title', ['%id%' => $id], true);
        }

        // new page
        $this->AddPage();

        // empty?
        if ($calculation->isEmpty()) {
            $this->resetStyle()->Ln();
            $message = $this->trans('calculation.edit.empty');
            $this->Cell(0, 0, $message, PdfBorder::none(), PdfMove::NEW_LINE, PdfTextAlignment::CENTER);
            $this->renderQrCode();

            return true;
        }

        // items
        CalculationTableItems::render($this);
        $this->Ln(3);

        // check new page
        $this->checkTablesHeight($calculation);

        // totals by group
        CalculationTableGroups::render($this);

        // overall totals
        CalculationTableOverall::render($this);

        // qr-code
        $this->renderQrCode();

        return true;
    }

    /**
     * Checks if the groups table, the overall table and the QR code (if any) fit within the current page.
     */
    private function checkTablesHeight(Calculation $calculation): void
    {
        // groups header + groups count + groups footer
        $lines = $calculation->getGroupsCount() + 2;

        // net total + user margin
        if (!empty($calculation->getUserMargin())) {
            $lines += 2;
        }

        // overall margin + overall total + timestamp
        $lines += 3;

        // total height
        $total = 2 + self::LINE_HEIGHT * $lines;

        // qr-code
        if (null !== $this->qrcode) {
            $total += $this->getQrCodeSize() - 1;
        }

        // check
        if (!$this->isPrintable($total)) {
            $this->AddPage();
        }
    }

    /**
     * Gets the QR code link or an empty string if none.
     */
    private function getQrCodeLink(): string
    {
        if (null !== $this->qrcode) {
            if (false !== \filter_var($this->qrcode, \FILTER_VALIDATE_EMAIL)) {
                return 'mailto:' . $this->qrcode;
            }

            return $this->qrcode;
        }

        return '';
    }

    /**
     * Gets the QR code size.
     */
    private function getQrCodeSize(): float
    {
        if (null !== $this->qrcode) {
            return $this->pixels2UserUnit(100);
        }

        return 0;
    }

    /**
     * Render the calculation properties (header).
     */
    private function renderCalculation(): void
    {
        $calculation = $this->calculation;

        $leftStyle = PdfStyle::getHeaderStyle()->setBorder(PdfBorder::TOP . PdfBorder::BOTTOM . PdfBorder::LEFT);
        $rightStyle = PdfStyle::getHeaderStyle()->setBorder(PdfBorder::TOP . PdfBorder::BOTTOM . PdfBorder::RIGHT);

        $table = new PdfTableBuilder($this);
        $table->addColumns(
            PdfColumn::left(null, 100),
            PdfColumn::right(null, 40, true)
        )->startHeaderRow()
            ->add(text: $calculation->getCustomer(), style: $leftStyle)
            ->add(text: $calculation->getStateCode(), style: $rightStyle)
            ->endRow()
            ->startHeaderRow()
            ->add(text: $calculation->getDescription(), style: $leftStyle)
            ->add(text: $calculation->getFormattedDate(), style: $rightStyle)
            ->endRow();

        $this->Ln(3);
    }

    /**
     * Render the QR code (if any).
     *
     * @throws \ReflectionException
     */
    private function renderQrCode(): void
    {
        if (null !== $this->qrcode) {
            try {
                // temp file
                if (null === $path = FileUtils::tempfile('qr_code')) {
                    $this->logWarning($this->trans('report.calculation.error_qr_code'), [
                        'calculation' => $this->calculation->getDisplay(),
                    ]);

                    return;
                }

                // build and save
                Builder::create()
                    ->roundBlockSizeMode(new RoundBlockSizeModeNone())
                    ->writer(new PngWriter())
                    ->data($this->qrcode)
                    ->margin(0)
                    ->build()
                    ->saveToFile($path);

                // position
                $size = $this->getQrCodeSize();
                $x = $this->GetPageWidth() - $this->getRightMargin() - $size;
                $y = $this->GetPageHeight() + self::FOOTER_OFFSET - $size - 1;

                // render
                $this->Image($path, $x, $y, $size, $size, PdfImageType::PNG, $this->getQrCodeLink());
            } catch (\Exception $e) {
                $this->logException($e, $this->trans('report.calculation.error_qr_code'));
            }
        }
    }
}
