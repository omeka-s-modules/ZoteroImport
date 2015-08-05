<?php
namespace ZoteroImport\Job;

use DateTime;
use Omeka\Job\AbstractJob;
use Omeka\Job\Exception;
use Zend\Http\Client;
use Zend\Http\Response;
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
    protected $vocabularies = array(
        'dcterms' => 'http://purl.org/dc/terms/',
        'dctype'  => 'http://purl.org/dc/dcmitype/',
        'bibo'    => 'http://purl.org/ontology/bibo/',
    );

    /**
     * Cache of selected Omeka resource classes
     *
     * @var array
     */
    protected $resourceClasses = array();

    /**
     * Cache of selected Omeka properties
     *
     * @var array
     */
    protected $properties = array();

    /**
     * Priority map between Zotero item types and Omeka resource classes
     *
     * @var array
     */
    protected $itemTypeMap = array();

    /**
     * Priority map between Zotero item fields and Omeka properties
     *
     * @var array
     */
    protected $itemFieldMap = array();

    /**
     * Priority map between Zotero creator types and Omeka properties
     *
     * @var array
     */
    protected $creatorTypeMap = array();

    /**
     * Perform the import.
     *
     * Accepts the following arguments:
     *
     * - itemSet:       The Omeka item set ID (int)
     * - type:          The Zotero library type (user, group)
     * - id:            The Zotero library ID (int)
     * - collectionKey: The Zotero collection key (string)
     * - apiKey:        The Zotero API key (string)
     * - importFiles:   Whether to import file attachments (bool)
     * - version:       The Zotero Last-Modified-Version of the last import (int)
     * - timestamp:     The Omeka Job::$started timestamp of the last import (int)
     *
     * Roughly follows Zotero's recommended steps for synchronizing a Zotero Web
     * API client with the Zotero server. But for the purposes of this job, a
     * "sync" only imports parent items (and their children) that have been
     * added to Zotero since the last import.
     *
     * @see https://www.zotero.org/support/dev/web_api/v3/syncing#full-library_syncing
     */
    public function perform()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $response = $api->read('item_sets', $this->getArg('itemSet'));
        if ($response->isError()) {
            throw new Exception\RuntimeException('There was an error during item set read.');
        }
        $itemSet = $response->getContent();

        $headers = array('Zotero-API-Version' => '3');
        if ($apiKey = $this->getArg('apiKey')) {
            $headers['Authorization'] = sprintf('Bearer %s', $apiKey);
        }
        $this->client = $this->getServiceLocator()->get('Omeka\HttpClient');
        $this->client->setHeaders($headers);

        $params = array('since' => $this->getArg('version', 0), 'format' => 'versions');
        $this->url = new Url($this->getArg('type'), $this->getArg('id'));
        if ($collectionKey = $this->getArg('collectionKey')) {
             $url = $this->url->collectionItems($collectionKey, $params);
        } else {
            $url = $this->url->items($params);
        }

        $response = $this->getResponse($url);
        $zItemKeys = array_keys(json_decode($response->getBody(), true));

        // Cache all Zotero parent and child items.
        $zParentItems = array();
        $zChildItems = array();
        foreach (array_chunk($zItemKeys, 50, true) as $zItemKeysChunk) {
            $params = array('itemKey' => implode(',', $zItemKeysChunk));
            $url = $this->url->items($params);

            $response = $this->getResponse($url);
            $zItems = json_decode($response->getBody(), true);

            foreach ($zItems as $zItem) {
                if ('note' == $zItem['data']['itemType']) {
                    continue; // do not import notes
                }
                $dateAdded = new DateTime($zItem['data']['dateAdded']);
                if ($dateAdded->getTimestamp() < $this->getArg('timestamp', 0)) {
                    continue; // only import items added since last import
                }
                if (isset($zItem['data']['parentItem'])) {
                    $zChildItems[$zItem['data']['parentItem']][] = $zItem;
                } else {
                    $zParentItems[$zItem['key']] = $zItem;
                }
            }
        }

        $this->cacheResourceClasses();
        $this->cacheProperties();

        $this->itemTypeMap = require __DIR__ . '/item_type_map.php';
        $this->itemFieldMap = require __DIR__ . '/item_field_map.php';
        $this->creatorTypeMap = require __DIR__ . '/creator_type_map.php';

        // Map Zotero items to Omeka items.
        $oItems = array();
        foreach ($zParentItems as $zParentItemKey => $zParentItem) {
            $oItem = array();
            $oItem['o:item_set'] = array(array('o:id' => $itemSet->id()));
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
        }

        // Batch create Omeka items.
        foreach (array_chunk($oItems, 50, true) as $oItemsChunk) {
            if ($this->shouldStop()) {
                // @todo cache item IDs and delete them before returning
                return;
            }
            $response = $api->batchCreate('items', $oItemsChunk, array(), true);
            if ($response->isError()) {
                throw new Exception\RuntimeException('There was an error during item batch create.');
            }
        }
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
            $classes = $api->search('resource_classes', array(
                'vocabulary_namespace_uri' => $namespaceUri,
            ))->getContent();
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
            $properties = $api->search('properties', array(
                'vocabulary_namespace_uri' => $namespaceUri,
            ))->getContent();
            foreach ($properties as $property) {
                $this->properties[$prefix][$property->localName()] = $property;
            }
        }
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
                $omekaItem['o:resource_class'] = array('o:id' => $class->id());
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
                    $valueObject = array();
                    $valueObject['property_id'] = $property->id();
                    if ('bibo' == $prefix && 'uri' == $localName) {
                        $valueObject['@id'] = $value;
                    } else {
                        $valueObject['@value'] = $value;
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
            $name = array();
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
                    $omekaItem[$property->term()][] = array(
                        '@value' => $name,
                        'property_id' => $property->id(),
                    );
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
            $omekaItem[$property->term()][] = array(
                '@value' => $tag['tag'],
                'property_id' => $property->id(),
            );
        }
        return $omekaItem;
    }

    /**
     * Map an attachment.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return string
      */
    public function mapAttachment($zoteroItem, $omekaItem)
    {
        if ('attachment' != $zoteroItem['data']['itemType']) {
            return $omekaItem;
        }

        switch ($zoteroItem['data']['linkMode']) {
            case 'imported_url':
            case 'imported_file':
                if (!$this->getArg('importFiles') || !$this->getArg('apiKey')) {
                    break;
                }
                $property = $this->properties['dcterms']['title'];
                $omekaItem['o:media'][] = array(
                    'o:type'     => 'url',
                    'o:source'   => $this->url->itemFile($zoteroItem['key']),
                    'ingest_url' => $this->url->itemFile(
                        $zoteroItem['key'],
                        array('key' => $this->getArg('apiKey'))
                    ),
                    $property->term() => array(
                        array(
                            '@value' => $zoteroItem['data']['title'],
                            'property_id' => $property->id(),
                        ),
                    ),
                );
                break;
            case 'linked_url':
                // @id url already mapped in mapValues()
            case 'linked_file':
                // nothing to save for a linked file
            default:
                break;
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
