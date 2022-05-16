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

use App\Form\Admin\ParametersType;
use App\Form\User\RoleRightsType;
use App\Interfaces\ApplicationServiceInterface;
use App\Interfaces\RoleInterface;
use App\Model\Role;
use App\Security\EntityVoter;
use App\Service\ArchiveService;
use App\Service\ProductUpdater;
use App\Service\SuspendEventListenerService;
use App\Service\SwissPostUpdater;
use App\Traits\RoleTranslatorTrait;
use App\Util\SymfonyInfo;
use App\Util\Utils;
use Psr\Log\LoggerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Controller for administration tasks.
 */
#[AsController]
#[Route(path: '/admin')]
class AdminController extends AbstractController
{
    use RoleTranslatorTrait;

    /**
     * Archive calculations.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/archive', name: 'admin_archive')]
    public function archive(Request $request, ArchiveService $service, SuspendEventListenerService $listener): Response
    {
        $query = $service->createQuery();
        $form = $service->createForm($query);
        if ($this->handleRequestForm($request, $form)) {
            try {
                // save
                $service->saveQuery($query);

                // update
                $listener->disableListeners();
                $result = $service->processQuery($query);

                return $this->renderForm('admin/archive_result.html.twig', [
                    'result' => $result,
                ]);
            } finally {
                $listener->enableListeners();
            }
        }

        return $this->renderForm('admin/archive_query.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Clear the application cache.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/clear', name: 'admin_clear')]
    public function clearCache(Request $request, KernelInterface $kernel, LoggerInterface $logger, SymfonyInfo $info): Response
    {
        // handle request
        $form = $this->createForm();
        if ($this->handleRequestForm($request, $form)) {
            // first clear application service cache
            $this->getApplication()->clearCache();

            try {
                // new command
                $options = [
                    'command' => 'cache:pool:clear',
                    'pools' => ['cache.global_clearer'],
                    '--env' => $kernel->getEnvironment(),
                ];

                $input = new ArrayInput($options);
                $output = new BufferedOutput();

                $application = new Application($kernel);
                $application->setCatchExceptions(false);
                $application->setAutoExit(false);

                $result = $application->run($input, $output);
                $content = $output->fetch();

                $context = [
                    'result' => $result,
                    'options' => $options,
                    'content' => $content,
                ];
                $message = $this->successTrans('clear_cache.success');
                $logger->info($message, $context);

                return $this->redirectToHomePage();
            } catch (\Exception $e) {
                $message = $this->trans('clear_cache.failure');
                $context = Utils::getExceptionContext($e);
                $logger->error($message, $context);

                return $this->renderForm('@Twig/Exception/exception.html.twig', [
                    'message' => $message,
                    'exception' => $e,
                ]);
            }
        }
        // display
        return $this->renderForm('admin/clear_cache.html.twig', [
            'size' => $info->getCacheSize(),
            'form' => $form,
        ]);
    }

    /**
     * Import zip codes, cities and streets from Switzerland.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/import', name: 'admin_import')]
    public function import(Request $request, SwissPostUpdater $updater): Response
    {
        // clear cache
        $application = $this->getApplication();
        $application->clearCache();
        // create form
        $form = $updater->createForm();
        // handle request
        if ($this->handleRequestForm($request, $form)) {
            // import
            /** @psalm-var array $data */
            $data = $form->getData();
            /** @psalm-var \Symfony\Component\HttpFoundation\File\UploadedFile|string|null $file */
            $file = $data['file'];
            $results = $updater->import($file);

            // display result
            return $this->renderForm('admin/import_result.html.twig', [
                'results' => $results,
            ]);
        }
        // display
        return $this->renderForm('admin/import_file.html.twig', [
            'last_import' => $application->getLastImport(),
            'form' => $form,
        ]);
    }

    /**
     * Display the application parameters.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/parameters', name: 'admin_parameters')]
    public function parameters(Request $request): Response
    {
        // properties
        $application = $this->getApplication();
        $data = $application->getProperties([
            ApplicationServiceInterface::P_UPDATE_PRODUCTS,
            ApplicationServiceInterface::P_LAST_IMPORT,
        ]);
        // password options
        foreach (ParametersType::PASSWORD_OPTIONS as $option) {
            $data[$option] = $application->isPropertyBoolean($option);
        }
        // form
        $form = $this->createForm(ParametersType::class, $data);
        if ($this->handleRequestForm($request, $form)) {
            /** @psalm-var array<string, mixed> $data */
            $data = $form->getData();
            $defaultProperties = $application->getDefaultValues();
            foreach (ParametersType::PASSWORD_OPTIONS as $option) {
                $defaultProperties[$option] = false;
            }
            $application->setProperties($data, $defaultProperties);
            $this->successTrans('parameters.success');

            return $this->redirectToHomePage();
        }
        // display
        return $this->renderForm('admin/parameters.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Edit rights for the administrator role ('ROLE_ADMIN').
     */
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route(path: '/rights/admin', name: 'admin_rights_admin')]
    public function rightsAdmin(Request $request): Response
    {
        // get values
        $roleName = RoleInterface::ROLE_ADMIN;
        $rights = $this->getApplication()->getAdminRights();
        $default = EntityVoter::getRoleAdmin();
        $property = ApplicationServiceInterface::P_ADMIN_RIGHTS;

        return $this->editRights($request, $roleName, $rights, $default, $property);
    }

    /**
     * Edit rights for the user role ('ROLE_USER').
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/rights/user', name: 'admin_rights_user')]
    public function rightsUser(Request $request): Response
    {
        // get values
        $roleName = RoleInterface::ROLE_USER;
        $rights = $this->getApplication()->getUserRights();
        $default = EntityVoter::getRoleUser();
        $property = ApplicationServiceInterface::P_USER_RIGHTS;

        return $this->editRights($request, $roleName, $rights, $default, $property);
    }

    /**
     * Update product prices.
     */
    #[IsGranted('ROLE_ADMIN')]
    #[Route(path: '/product', name: 'admin_product')]
    public function updateProduct(Request $request, ProductUpdater $updater): Response
    {
        // create form
        $application = $this->getApplication();
        $query = $updater->createUpdateQuery();
        $form = $updater->createForm($query);
        // handle request
        if ($this->handleRequestForm($request, $form)) {
            // save query
            $updater->saveUpdateQuery($query);

            // update
            $result = $updater->update($query);

            // update last date
            if (!$query->isSimulate() && $result->isValid()) {
                $application->setProperty(ApplicationServiceInterface::P_UPDATE_PRODUCTS, new \DateTime());
            }

            return $this->renderForm('product/product_result.html.twig', [
                'result' => $result,
                'query' => $query,
            ]);
        }

        return $this->renderForm('product/product_update.html.twig', [
            'last_update' => $application->getUpdateProducts(),
            'form' => $form,
        ]);
    }

    /**
     * Edit rights.
     *
     * @param Request $request  the request
     * @param string  $roleName the role name
     * @param int[]   $rights   the role rights
     * @param Role    $default  the role with default rights
     * @param string  $property the property name to update
     */
    private function editRights(Request $request, string $roleName, ?array $rights, Role $default, string $property): Response
    {
        // role
        $role = new Role($roleName);
        $role->setName($this->translateRole($roleName))
            ->setRights($rights);

        // form
        $form = $this->createForm(RoleRightsType::class, $role);
        if ($this->handleRequestForm($request, $form)) {
            $this->getApplication()->setProperty($property, $role->getRights());
            $this->successTrans('admin.rights.success', ['%name%' => $role->getName()]);

            return $this->redirectToHomePage();
        }

        // show form
        return $this->renderForm('admin/role_rights.html.twig', [
            'form' => $form,
            'default' => $default,
        ]);
    }
}
