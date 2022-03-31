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

namespace App\Report;

use App\Controller\AbstractController;

/**
 * Abstract report rendering an array of objects.
 *
 * @author Laurent Muller
 *
 * @template T
 */
abstract class AbstractArrayReport extends AbstractReport
{
    /**
     * The entities to output.
     *
     * @pslam-var T[]
     */
    protected array $entities;

    /**
     * Constructor.
     *
     * @param AbstractController $controller  the parent controller
     * @param array              $entities    the entities to render
     * @param string             $orientation the page orientation. One of the ORIENTATION_XX contents.
     * @psalm-param T[] $entities
     */
    public function __construct(AbstractController $controller, array $entities, string $orientation = self::ORIENTATION_PORTRAIT)
    {
        parent::__construct($controller, $orientation);
        $this->entities = $entities;
    }

    /**
     * {@inheritdoc}
     */
    public function render(): bool
    {
        if (!empty($this->entities)) {
            return $this->doRender($this->entities);
        }

        return false;
    }

    /**
     * Render the given entities.
     *
     * @param array $entities the entities to render
     *
     * @return bool true if rendered successfully; false otherwise
     *
     * @psalm-param T[] $entities
     */
    abstract protected function doRender(array $entities): bool;
}
