<?php
namespace ZoteroImport\Job;

use DateTime;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception;
use Laminas\Http\Client;
use Laminas\Http\Response;
use ZoteroImport\Zotero\Url;

class Import extends AbstractJob
{
    /**
     * Zotero API client
     *
     * @var Client
     */
    protected $client;

    /**
     * Zotero API URL
     *
     * @var Url
     */
    protected $url;

    /**
     * Vocabularies to cache.
     *
     * @var array
     */
    protected $vocabularies = [
        'dcterms' => 'http://purl.org/dc/terms/',
        'dctype' => 'http://purl.org/dc/dcmitype/',
        'bibo' => 'http://purl.org/ontology/bibo/',
    ];

    /**
     * Cache of selected Omeka resource classes
     *
     * @var array
     */
    protected $resourceClasses = [];

    /**
     * Cache of selected Omeka properties
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Priority map between Zotero item types and Omeka resource classes
     *
     * @var array
     */
    protected $itemTypeMap = [];

    /**
     * Priority map between Zotero item fields and Omeka properties
     *
     * @var array
     */
    protected $itemFieldMap = [];

    /**
     * Priority map between Zotero creator types and Omeka properties
     *
     * @var array
     */
    protected $creatorTypeMap = [];

    /**
     * Perform the import.
     *
     * Accepts the following arguments:
     *
     * - itemSet:       The Omeka item set ID (int)
     * - import:        The Omeka Zotero import ID (int)
     * - type:          The Zotero library type (user, group)
     * - id:            The Zotero library ID (int)
     * - collectionKey: The Zotero collection key (string)
     * - apiKey:        The Zotero API key (string)
     * - importFiles:   Whether to import file attachments (bool)
     * - version:       The Zotero Last-Modified-Version of the last import (int)
     * - timestamp:     The Zotero dateAdded timestamp (UTC) to begin importing (int)
     *
     * Roughly follows Zotero's recommended steps for synchronizing a Zotero Web
     * API client with the Zotero server. But for the purposes of this job, a
     * "sync" only imports parent items (and their children) that have been
     * added to Zotero since the passed timestamp.
     *
     * @see https://www.zotero.org/support/dev/web_api/v3/syncing#full-library_syncing
     */
    public function perform()
    {
        // Raise the memory limit to accommodate very large imports.
        ini_set('memory_limit', '500M');

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $itemSet = $api->read('item_sets', $this->getArg('itemSet'))->getContent();

        $this->cacheResourceClasses();
        $this->cacheProperties();

        $this->itemTypeMap = $this->prepareMapping('item_type_map');
        $this->itemFieldMap = $this->prepareMapping('item_field_map');
        $this->creatorTypeMap = $this->prepareMapping('creator_type_map');

        $this->setImportClient();
        $this->setImportUrl();

        $apiVersion = $this->getArg('version', 0);
        $apiKey = $this->getArg('apiKey');
        $collectionKey = $this->getArg('collectionKey');

        $params = [
            'since' => $apiVersion,
            'format' => 'versions',
            // Sort by ascending date added so items are imported roughly in the
            // same order. This way, if there is an error during an import,
            // users can estimate when to set the "Added after" field.
            'sort' => 'dateAdded',
            'direction' => 'asc',
            // Do not import notes.
            'itemType' => '-note',
        ];
        if ($collectionKey) {
            $url = $this->url->collectionItems($collectionKey, $params);
        } else {
            $url = $this->url->items($params);
        }
        $zItemKeys = array_keys(json_decode($this->getResponse($url)->getBody(), true));

        if (empty($zItemKeys)) {
            return;
        }

        // Cache all Zotero parent and child items.
        $zParentItems = [];
        $zChildItems = [];
        foreach (array_chunk($zItemKeys, 50, true) as $zItemKeysChunk) {
            if ($this->shouldStop()) {
                return;
            }
            $url = $this->url->items([
                'itemKey' => implode(',', $zItemKeysChunk),
                // Include the Zotero key so Zotero adds enclosure links to the
                // response. An attachment can only be downloaded if an
                // enclosure link is included.
                'key' => $apiKey,
            ]);
            $zItems = json_decode($this->getResponse($url)->getBody(), true);

            foreach ($zItems as $zItem) {
                $dateAdded = new DateTime($zItem['data']['dateAdded']);
                if ($dateAdded->getTimestamp() < $this->getArg('timestamp', 0)) {
                    // Only import items added since the passed timestamp. Note
                    // that the timezone must be UTC.
                    continue;
                }

                // Unset unneeded data to save memory.
                unset($zItem['library']);
                unset($zItem['version']);
                unset($zItem['meta']);
                unset($zItem['links']['self']);
                unset($zItem['links']['alternate']);

                if (isset($zItem['data']['parentItem'])) {
                    $zChildItems[$zItem['data']['parentItem']][] = $zItem;
                } else {
                    $zParentItems[$zItem['key']] = $zItem;
                }
            }
        }

        // Map Zotero items to Omeka items. Pass by reference so PHP doesn't
        // create a copy of the array, saving memory.
        $oItems = [];
        foreach ($zParentItems as $zParentItemKey => &$zParentItem) {
            $oItem = [];
            $oItem['o:item_set'] = [['o:id' => $itemSet->id()]];
            $oItem = $this->mapResourceClass($zParentItem, $oItem);
            $oItem = $this->mapNameValues($zParentItem, $oItem);
            $oItem = $this->mapSubjectValues($zParentItem, $oItem);
            $oItem = $this->mapValues($zParentItem, $oItem);
            $oItem = $this->mapAttachment($zParentItem, $oItem);
            if (isset($zChildItems[$zParentItemKey])) {
                foreach ($zChildItems[$zParentItemKey] as $zChildItem) {
                    $oItem = $this->mapAttachment($zChildItem, $oItem);
                }
            }
            $oItems[$zParentItemKey] = $oItem;
            // Unset unneeded data to save memory.
            unset($zParentItems[$zParentItemKey]);
        }

        // Batch create Omeka items.
        $importId = $this->getArg('import');
        foreach (array_chunk($oItems, 50, true) as $oItemsChunk) {
            if ($this->shouldStop()) {
                return;
            }
            $response = $api->batchCreate('items', $oItemsChunk, [], ['continueOnError' => true]);

            // Batch create Zotero import items.
            $importItems = [];
            foreach ($response->getContent() as $zKey => $item) {
                $importItems[] = [
                    'o:item' => ['o:id' => $item->id()],
                    'o-module-zotero_import:import' => ['o:id' => $importId],
                    'o-module-zotero_import:zotero_key' => $zKey,
                ];
            }
            // The ZoteroImportItem entity cascade detaches items, which saves
            // memory during batch create.
            $api->batchCreate('zotero_import_items', $importItems, [], ['continueOnError' => true]);
        }
    }

    /**
     * Set the HTTP client to use during this import.
     */
    public function setImportClient()
    {
        $headers = ['Zotero-API-Version' => '3'];
        if ($apiKey = $this->getArg('apiKey')) {
            $headers['Authorization'] = sprintf('Bearer %s', $apiKey);
        }
        $this->client = $this->getServiceLocator()->get('Omeka\HttpClient')
            ->setHeaders($headers)
            // Decrease the chance of timeout by increasing to 20 seconds,
            // which splits the time between Omeka's default (10) and Zotero's
            // upper limit (30).
            ->setOptions(['timeout' => 20]);
    }

    /**
     * Set the Zotero URL object to use during this import.
     */
    public function setImportUrl()
    {
        $this->url = new Url($this->getArg('type'), $this->getArg('id'));
    }

    /**
     * Get a response from the Zotero API.
     *
     * @param string $url
     * @return Response
     */
    public function getResponse($url)
    {
        $response = $this->client->setUri($url)->send();
        if (!$response->isSuccess()) {
            throw new Exception\RuntimeException(sprintf(
                'Requested "%s" got "%s".', $url, $response->renderStatusLine()
            ));
        }
        return $response;
    }

    /**
     * Cache selected resource classes.
     */
    public function cacheResourceClasses()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        foreach ($this->vocabularies as $prefix => $namespaceUri) {
            $classes = $api->search('resource_classes', [
                'vocabulary_namespace_uri' => $namespaceUri,
            ])->getContent();
            foreach ($classes as $class) {
                $this->resourceClasses[$prefix][$class->localName()] = $class;
            }
        }
    }

    /**
     * Cache selected properties.
     */
    public function cacheProperties()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        foreach ($this->vocabularies as $prefix => $namespaceUri) {
            $properties = $api->search('properties', [
                'vocabulary_namespace_uri' => $namespaceUri,
            ])->getContent();
            foreach ($properties as $property) {
                $this->properties[$prefix][$property->localName()] = $property;
            }
        }
    }

    /**
     * Convert a mapping with terms into a mapping with prefix and local name.
     *
     * @param string $mapping
     * @return array
     */
    protected function prepareMapping($mapping)
    {
        $map = require dirname(dirname(__DIR__)) . '/data/mapping/' . $mapping . '.php';
        foreach ($map as &$term) {
            if ($term) {
                $value = explode(':', $term);
                $term = [$value[0] => $value[1]];
            } else {
                $term = [];
            }
        }
        return $map;
    }

    /**
     * Map Zotero item type to Omeka resource class.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    public function mapResourceClass(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data']['itemType'])) {
            return $omekaItem;
        }
        $type = $zoteroItem['data']['itemType'];
        if (!isset($this->itemTypeMap[$type])) {
            return $omekaItem;
        }
        foreach ($this->itemTypeMap[$type] as $prefix => $localName) {
            if (isset($this->resourceClasses[$prefix][$localName])) {
                $class = $this->resourceClasses[$prefix][$localName];
                $omekaItem['o:resource_class'] = ['o:id' => $class->id()];
                return $omekaItem;
            }
        }
        return $omekaItem;
    }

    /**
     * Map Zotero item data to Omeka item values.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    public function mapValues(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data'])) {
            return $omekaItem;
        }
        foreach ($zoteroItem['data'] as $key => $value) {
            if (!$value) {
                continue;
            }
            if (!isset($this->itemFieldMap[$key])) {
                continue;
            }
            foreach ($this->itemFieldMap[$key] as $prefix => $localName) {
                if (isset($this->properties[$prefix][$localName])) {
                    $property = $this->properties[$prefix][$localName];
                    $valueObject = [];
                    $valueObject['property_id'] = $property->id();
                    if ('bibo' == $prefix && 'uri' == $localName) {
                        $valueObject['@id'] = $value;
                        $valueObject['type'] = 'uri';
                    } else {
                        $valueObject['@value'] = $value;
                        $valueObject['type'] = 'literal';
                    }
                    $omekaItem[$property->term()][] = $valueObject;
                    continue 2;
                }
            }
        }
        return $omekaItem;
    }

    /**
     * Map Zotero creator names to the Omeka item values.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    public function mapNameValues(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data']['creators'])) {
            return $omekaItem;
        }
        $creators = $zoteroItem['data']['creators'];
        foreach ($creators as $creator) {
            $creatorType = $creator['creatorType'];
            if (!isset($this->creatorTypeMap[$creatorType])) {
                continue;
            }
            $name = [];
            if (isset($creator['name'])) {
                $name[] = $creator['name'];
            }
            if (isset($creator['firstName'])) {
                $name[] = $creator['firstName'];
            }
            if (isset($creator['lastName'])) {
                $name[] = $creator['lastName'];
            }
            if (!$name) {
                continue;
            }
            $name = implode(' ', $name);
            foreach ($this->creatorTypeMap[$creatorType] as $prefix => $localName) {
                if (isset($this->properties[$prefix][$localName])) {
                    $property = $this->properties[$prefix][$localName];
                    $omekaItem[$property->term()][] = [
                        '@value' => $name,
                        'property_id' => $property->id(),
                        'type' => 'literal',
                    ];
                    continue 2;
                }
            }
        }
        return $omekaItem;
    }

    /**
     * Map Zotero tags to Omeka item values (dcterms:subject).
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    public function mapSubjectValues(array $zoteroItem, array $omekaItem)
    {
        if (!isset($zoteroItem['data']['tags'])) {
            return $omekaItem;
        }
        $tags = $zoteroItem['data']['tags'];
        foreach ($tags as $tag) {
            $property = $this->properties['dcterms']['subject'];
            $omekaItem[$property->term()][] = [
                '@value' => $tag['tag'],
                'property_id' => $property->id(),
                'type' => 'literal',
            ];
        }
        return $omekaItem;
    }

    /**
     * Map an attachment.
     *
     * There are four kinds of Zotero attachments: imported_url, imported_file,
     * linked_url, and linked_file. Only imported_url and imported_file have
     * files, and only when the response includes an enclosure link. For
     * linked_url, the @id URL was already mapped in mapValues(). For
     * linked_file, there is nothing to save.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return string
     */
    public function mapAttachment($zoteroItem, $omekaItem)
    {
        if ('attachment' === $zoteroItem['data']['itemType']
            && isset($zoteroItem['links']['enclosure'])
            && $this->getArg('importFiles')
            && $this->getArg('apiKey')
        ) {
            $property = $this->properties['dcterms']['title'];
            $omekaItem['o:media'][] = [
                'o:ingester' => 'url',
                'o:source' => $this->url->itemFile($zoteroItem['key']),
                'ingest_url' => $this->url->itemFile(
                    $zoteroItem['key'],
                    ['key' => $this->getArg('apiKey')]
                ),
                $property->term() => [
                    [
                        '@value' => $zoteroItem['data']['title'],
                        'property_id' => $property->id(),
                        'type' => 'literal',
                    ],
                ],
            ];
        }
        return $omekaItem;
    }

    /**
     * Get a URL from the Link header.
     *
     * @param Response $response
     * @param string $rel The relationship from the current document. Possible
     * values are first, prev, next, last, alternate.
     * @return string|null
     */
    public function getLink(Response $response, $rel)
    {
        $linkHeader = $response->getHeaders()->get('Link');
        if (!$linkHeader) {
            return null;
        }
        preg_match_all(
            '/<([^>]+)>; rel="([^"]+)"/',
            $linkHeader->getFieldValue(),
            $matches
        );
        if (!$matches) {
            return null;
        }
        $key = array_search($rel, $matches[2]);
        if (false === $key) {
            return null;
        }
        return $matches[1][$key];
    }
}
