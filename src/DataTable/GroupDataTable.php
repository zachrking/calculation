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

namespace App\DataTable;

use App\DataTable\Model\AbstractEntityDataTable;
use App\DataTable\Model\DataColumn;
use App\DataTable\Model\DataColumnFactory;
use App\Entity\Group;
use App\Repository\GroupRepository;
use App\Util\FormatUtils;
use DataTables\DataTablesInterface;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Environment;

/**
 * Parent category (group) data table handler.
 *
 * @author Laurent Muller
 */
class GroupDataTable extends AbstractEntityDataTable
{
    /**
     * The datatable identifier.
     */
    public const ID = Group::class;

    /**
     * Constructor.
     *
     * @param SessionInterface    $session     the session to save/retrieve user parameters
     * @param DataTablesInterface $datatables  the datatables to handle request
     * @param GroupRepository     $repository  the repository to get entities
     * @param Environment         $environment the Twig environment to render cells
     */
    public function __construct(SessionInterface $session, DataTablesInterface $datatables, GroupRepository $repository, Environment $environment)
    {
        parent::__construct($session, $datatables, $repository, $environment);
    }

    /**
     * The categories formatter.
     *
     * @param Collection $categories the list of categories that fall into the given group
     *
     * @return string the link, if applicable, the value otherwise
     */
    public function categoriesFormatter(Collection $categories): string
    {
        return FormatUtils::formatInt(\count($categories));
    }

    /**
     * The margins formatter.
     *
     * @param Collection $margins the margins to format
     *
     * @return string the formatted margins
     */
    public function maginsFormatter(Collection $margins): string
    {
        return FormatUtils::formatInt(\count($margins));
    }

    /**
     * {@inheritdoc}
     */
    protected function createColumns(): array
    {
        $path = __DIR__ . '/Definition/group.json';

        return DataColumnFactory::fromJson($this, $path);
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOrder(): array
    {
        return ['code' => DataColumn::SORT_ASC];
    }
}
