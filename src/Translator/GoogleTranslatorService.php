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

namespace App\Translator;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Exception\ParameterNotFoundException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Google translator service v2.0.
 *
 * @author Laurent Muller
 *
 * @see https://cloud.google.com/translate/docs/translating-text
 */
class GoogleTranslatorService extends AbstractTranslatorService
{
    /**
     * The host name.
     */
    private const HOST_NAME = 'https://translation.googleapis.com/language/translate/v2/';

    /**
     * The parameter name for the API key.
     */
    private const PARAM_KEY = 'google_translator_key';

    /**
     * The detect URI.
     */
    private const URI_DETECT = 'detect';

    /**
     * The languages URI.
     */
    private const URI_LANGUAGE = 'languages';

    /**
     * The translate URI.
     */
    private const URI_TRANSLATE = '';

    /**
     * Constructor.
     *
     * @throws ParameterNotFoundException if the API key parameter is not defined
     * @throws \InvalidArgumentException  if the API key is null or empty
     */
    public function __construct(ParameterBagInterface $params, CacheItemPoolInterface $adapter, bool $isDebug)
    {
        /** @var string $key */
        $key = $params->get(self::PARAM_KEY);
        parent::__construct($adapter, $isDebug, $key);
    }

    /**
     * {@inheritdoc}
     */
    public function detect(string $text)
    {
        // query
        $query = ['q' => $text];

        /** @var bool|array $response */
        $response = $this->get(self::URI_DETECT, $query);
        if (!\is_array($response)) {
            return false;
        }

        // detections
        $detections = $this->getPropertyArray($response, 'detections');
        if (!\is_array($detections)) {
            return false;
        }

        // entries
        if (!$this->isValidArray($detections[0], 'entries')) {
            return false;
        }
        /** @var array $entries */
        $entries = $detections[0];

        // entry
        if (!$this->isValidArray($entries[0], 'detection')) {
            return false;
        }
        /** @var array $detection */
        $detection = $entries[0];

        // language
        $tag = $this->getProperty($detection, 'language');
        if (!\is_string($tag)) {
            return false;
        }

        return [
            'tag' => $tag,
            'name' => $this->findLanguage($tag),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public static function getApiUrl(): string
    {
        return 'https://cloud.google.com/translate/docs/translating-text';
    }

    /**
     * {@inheritdoc}
     */
    public static function getDefaultIndexName(): string
    {
        return 'Google';
    }

    /**
     * {@inheritdoc}
     */
    public function translate(string $text, string $to, ?string $from = null, bool $html = false)
    {
        // query
        $query = [
            'q' => $text,
            'target' => $to,
            'source' => $from ?: '',
            'format' => $html ? 'html' : 'text',
        ];

        // get
        /** @var bool|array $response */
        $response = $this->get(self::URI_TRANSLATE, $query);
        if (!\is_array($response)) {
            return false;
        }

        // translations
        $translations = $this->getPropertyArray($response, 'translations');
        if (!\is_array($translations)) {
            return false;
        }

        /** @var array $translation */
        $translation = $translations[0];

        // target
        $target = $this->getProperty($translation, 'translatedText');
        if (!\is_string($target)) {
            return false;
        }

        // from
        /** @var bool|string $from */
        $from = $this->getProperty($translation, 'detectedSourceLanguage', false);
        if (!\is_string($from)) {
            return false;
        }

        return [
            'source' => $text,
            'target' => $target,
            'from' => [
                'tag' => $from,
                'name' => $this->findLanguage($from),
            ],
            'to' => [
                'tag' => $to,
                'name' => $this->findLanguage($to),
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function doGetLanguages()
    {
        // query
        $query = ['target' => self::getAcceptLanguage()];

        $response = $this->get(self::URI_LANGUAGE, $query);
        if (!\is_array($response)) {
            return false;
        }

        // languages
        /** @var bool|array<array{name: string, language: string}>  $languages */
        $languages = $this->getPropertyArray($response, 'languages');
        if (!\is_array($languages)) {
            return false;
        }

        // build
        $result = [];
        foreach ($languages as $language) {
            $result[$language['name']] = $language['language'];
        }
        \ksort($result);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    protected function getDefaultOptions(): array
    {
        return [self::BASE_URI => self::HOST_NAME];
    }

    /**
     * Make a HTTP-GET call.
     *
     * @param string $uri   the uri to append to the host name
     * @param array  $query an associative array of query string values to add to the request
     *
     * @return mixed|bool the data response on success, false otherwise
     */
    private function get(string $uri, array $query = [])
    {
        // add key parameter
        $query['key'] = $this->key;

        // call
        $response = $this->requestGet($uri, [
            self::QUERY => $query,
        ]);

        // decode
        $response = $response->toArray(false);

        // check error
        if (isset($response['error'])) {
            /**
             * @var null|array{
             *      result: bool,
             *      code: string|int,
             *      message: string,
             *      exception?: array|\Exception} $error
             */
            $error = $response['error'];
            $this->lastError = $error;

            return false;
        }

        // get data
        /** @var bool|array $data */
        $data = $this->getProperty($response, 'data');
        if (!\is_array($data)) {
            return false;
        }

        return $data;
    }
}
