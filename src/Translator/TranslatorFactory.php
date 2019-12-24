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

namespace App\Translator;

use App\Traits\SessionTrait;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Factory to provide translator services.
 *
 * @author Laurent Muller
 */
class TranslatorFactory
{
    use SessionTrait;

    /**
     * The Bing translator service name.
     */
    public const BING_SERVICE = BingTranslatorService::class;

    /**
     * The Google translator service name.
     */
    public const GOOGLE_SERVICE = GoogleTranslatorService::class;

    /**
     * The Yandex translator service name.
     */
    public const YANDEX_SERVICE = YandexTranslatorService::class;

    /**
     * The name of the key to save/retrieve the last translation service used.
     */
    private const KEY_LAST_SERVICE = 'translator_service';

    /**
     * @var AdapterInterface
     */
    private $cache;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * Constructor.
     *
     * @param ContainerInterface $container the container to get the API key
     * @param KernelInterface    $kernel    the kernel to get the debug mode
     * @param AdapterInterface   $cache     the cache used to save or retrieve languages
     * @param SessionInterface   $session   the session to save or retrieve the last used service
     */
    public function __construct(ContainerInterface $container, KernelInterface $kernel, AdapterInterface $cache, SessionInterface $session)
    {
        $this->container = $container;
        $this->kernel = $kernel;
        $this->cache = $cache;
        $this->session = $session;
    }

    /**
     * Returns if the given translator service exists.
     *
     * @param string $class the service class to be tested
     *
     * @return bool true if exist
     */
    public function exists(string $class): bool
    {
        $services = $this->getServices();
        foreach ($services as $service) {
            if ($class === $service['class']) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets a translator service.
     *
     * @param string $class the service class. Can be one of this defined constants.
     *
     * @return ITranslatorService the translator service
     *
     * @throws \InvalidArgumentException if the service can not be found or if the API key parameter is not defined
     */
    public function getService(string $class): ITranslatorService
    {
        if (!$this->exists($class)) {
            throw new \InvalidArgumentException("The translator service '{$class}' can not be found.");
        }

        // create and save service
        $service = new $class($this->container, $this->kernel, $this->cache);
        $this->setSessionValue(self::KEY_LAST_SERVICE, $class);

        return $service;
    }

    /**
     * Gets the defined services.
     *
     * Each entry contains the service name, the class name and the API url.
     */
    public function getServices(): array
    {
        return [
            [
                'name' => BingTranslatorService::getName(),
                'api' => BingTranslatorService::getApiUrl(),
                'class' => BingTranslatorService::class,
            ],
            [
                'name' => GoogleTranslatorService::getName(),
                'api' => GoogleTranslatorService::getApiUrl(),
                'class' => GoogleTranslatorService::class,
            ],
            [
                'name' => YandexTranslatorService::getName(),
                'api' => YandexTranslatorService::getApiUrl(),
                'class' => YandexTranslatorService::class,
            ],
        ];
    }

    /**
     * Gets the last used translator service from the session.
     *
     * @return ITranslatorService the translator service or the default (Bing) if not found
     */
    public function getSessionService(): ITranslatorService
    {
        $class = $this->getSessionValue(self::KEY_LAST_SERVICE, self::BING_SERVICE);
        if (!$this->exists($class)) {
            $class = self::BING_SERVICE;
        }

        return $this->getService($class);
    }
}
