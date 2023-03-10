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

namespace App\Service;

use App\Controller\AbstractController;
use App\Controller\IndexController;
use App\Interfaces\TableInterface;
use App\Table\AbstractCategoryItemTable;
use App\Table\CalculationTable;
use App\Table\CategoryTable;
use App\Table\LogTable;
use App\Table\SearchTable;
use App\Traits\RequestTrait;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service to generate URL and parameters.
 */
class UrlGeneratorService
{
    use RequestTrait;
    /**
     * The caller parameter name.
     */
    final public const PARAM_CALLER = 'caller';

    /**
     * The parameter names.
     */
    private const PARAMETER_NAMES = [
        // global
        self::PARAM_CALLER,

        // index page
        IndexController::PARAM_RESTRICT,

        // bootstrap-table
        TableInterface::PARAM_ID,
        TableInterface::PARAM_SEARCH,
        TableInterface::PARAM_SORT,
        TableInterface::PARAM_ORDER,
        TableInterface::PARAM_OFFSET,
        TableInterface::PARAM_VIEW,
        TableInterface::PARAM_LIMIT,

        // tables
        LogTable::PARAM_LEVEL,
        LogTable::PARAM_CHANNEL,

        CategoryTable::PARAM_GROUP,
        CalculationTable::PARAM_STATE,
        AbstractCategoryItemTable::PARAM_CATEGORY,

        SearchTable::PARAM_TYPE,
        SearchTable::PARAM_ENTITY,
    ];

    /**
     * Constructor.
     */
    public function __construct(private readonly UrlGeneratorInterface $generator)
    {
    }

    /**
     * Generate the cancel URL.
     */
    public function cancelUrl(Request $request, ?int $id = 0, string $defaultRoute = AbstractController::HOME_PAGE, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        // get parameters
        $params = $this->routeParams($request, $id);

        // caller?
        if (null !== $caller = $this->getCaller($params)) {
            unset($params[self::PARAM_CALLER]);
            if (!empty($params)) {
                $caller .= \str_contains($caller, '?') ? '&' : '?';
                $caller .= \http_build_query($params);
            }

            return $caller;
        }

        // default route
        return $this->generator->generate($defaultRoute, $params, $referenceType);
    }

    /**
     * Generate the cancel URL and returns a redirect response.
     */
    public function redirect(Request $request, ?int $id = 0, string $defaultRoute = AbstractController::HOME_PAGE, int $status = Response::HTTP_FOUND, int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): RedirectResponse
    {
        $url = $this->cancelUrl($request, $id, $defaultRoute, $referenceType);

        return new RedirectResponse($url, $status);
    }

    /**
     * Gets the request parameters.
     *
     * @psalm-return array<string, string|int|float|bool>
     */
    public function routeParams(Request $request, ?int $id = 0): array
    {
        $params = [];

        // parameters
        foreach (self::PARAMETER_NAMES as $name) {
            if (null !== ($value = $this->getRequestValue($request, $name))) {
                $params[$name] = $value;
            }
        }

        // identifier
        if (!empty($id)) {
            $params['id'] = $id;
        }

        return $params;
    }

    /**
     * Gets the caller parameter.
     *
     * @param array<string, string|int|float|bool> $params
     */
    private function getCaller(array $params): ?string
    {
        /** @var string|null $caller */
        $caller = $params[self::PARAM_CALLER] ?? null;
        if (!empty($caller)) {
            return ('/' === $caller) ? $caller : \rtrim($caller, '/');
        }

        return null;
    }
}
