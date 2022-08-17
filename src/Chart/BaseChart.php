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

namespace App\Chart;

use App\Service\ApplicationService;
use App\Traits\MathTrait;
use App\Traits\TranslatorAwareTrait;
use App\Util\DateUtils;
use App\Util\FormatUtils;
use Laminas\Json\Expr;
use Ob\HighchartsBundle\Highcharts\Highchart;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Symfony\Contracts\Service\ServiceSubscriberTrait;

/**
 * High chart with method shortcuts.
 *
 * @method BaseChart style(array $styles) set the CSS style.
 * @method BaseChart xAxis(array $xAxis)  set the x-axis.
 * @method BaseChart yAxis(array $yAxis)  set the y-axis.
 *
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $xAxis       the x-axis.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $yAxis       the y-axis.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $chart       the chart.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $credits     the credits.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $legend      the legend.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $tooltip     the tooltip.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $plotOptions the plot options.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $lang        the language.
 * @property \Ob\HighchartsBundle\Highcharts\ChartOption $title       the language.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class BaseChart extends Highchart implements ServiceSubscriberInterface
{
    use MathTrait;
    use ServiceSubscriberTrait;
    use TranslatorAwareTrait;

    /**
     * The identifier (#id) of the div where to render the chart.
     */
    final public const CONTAINER = 'chartContainer';

    /**
     * The column chart type.
     */
    final public const TYPE_COLUMN = 'column';

    /**
     * The line chart type.
     */
    final public const TYPE_LINE = 'line';

    /**
     * The pie chart type.
     */
    final public const TYPE_PIE = 'pie';

    /**
     * The spline chart type.
     */
    final public const TYPE_SP_LINE = 'spline';

    /**
     * Constructor.
     */
    public function __construct(protected ApplicationService $application)
    {
        parent::__construct();

        $this->hideCredits()
            ->initLangOptions()
            ->setRenderTo(self::CONTAINER)
            ->setBackground('transparent')
            ->setFontFamily('var(--font-family-sans-serif)');
    }

    /**
     * Add a chart event handler.
     *
     * @param string $eventName the chart event name
     * @param Expr   $handler   the event handler
     */
    public function addChartEventListener(string $eventName, Expr $handler): static
    {
        /** @psalm-var \stdClass $events */
        $events = $this->chart->events ?? new \stdClass();
        $events->$eventName = $handler;
        $this->chart->events($events); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Hides the credits text.
     *
     * @psalm-suppress MixedMethodCall
     */
    public function hideCredits(): self
    {
        $this->credits->enabled(false); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Hides the series legend.
     */
    public function hideLegend(): self
    {
        $this->legend->enabled(false); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Hides the chart title.
     */
    public function hideTitle(): self
    {
        return $this->setTitle(null);
    }

    /**
     * Sets background color for the outer chart area.
     */
    public function setBackground(string $color): self
    {
        $this->chart->backgroundColor($color); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Sets the font family.
     */
    public function setFontFamily(string $font): self
    {
        $this->style(['fontFamily' => $font]);

        return $this;
    }

    /**
     * Sets the HTML element where the chart will be rendered.
     */
    public function setRenderTo(string $id): self
    {
        $this->chart->renderTo($id); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Sets the chart title.
     *
     * @param ?string $title the title to set or null to hide
     */
    public function setTitle(?string $title): self
    {
        $this->title->text($title); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Sets the chart type.
     *
     * @param string $type the chart type to set
     * @psalm-param 'column'|'line'|'pie'|'spline' $type
     */
    public function setType(string $type): self
    {
        $this->chart->type($type); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Sets the x-axis categories.
     *
     * @param mixed $categories the categories to set
     */
    public function setXAxisCategories(mixed $categories): self
    {
        $this->xAxis->categories($categories); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Sets the x-axis title.
     *
     * @param ?string $title the title to set or null to hide
     */
    public function setXAxisTitle(?string $title): self
    {
        $this->xAxis->title(['text' => $title]); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Sets the y-axis title.
     *
     * @param ?string $title the title to set or null to hide
     */
    public function setYAxisTitle(?string $title): self
    {
        $this->yAxis->title(['text' => $title]); // @phpstan-ignore-line

        return $this;
    }

    /**
     * Initialize the language options.
     */
    private function initLangOptions(): self
    {
        $options = [
            'thousandsSep' => FormatUtils::getGrouping(),
            'decimalPoint' => FormatUtils::getDecimal(),
            'months' => \array_values(DateUtils::getMonths()),
            'weekdays' => \array_values(DateUtils::getWeekdays()),
            'shortMonths' => \array_values(DateUtils::getShortMonths()),
            'shortWeekdays' => \array_values(DateUtils::getShortWeekdays()),
        ];

        foreach ($options as $id => $value) {
            $this->lang->{$id}($value);
        }

        return $this;
    }
}
