<?php
namespace ZoteroImport\Job;

use Omeka\Job\AbstractJob;
use Omeka\Job\Exception;
use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Http\Response;

class Import extends AbstractJob
{
    /**
     * Zotero API base URL.
     */
    const BASE_URL = 'https://api.zotero.org';

    /**
     * Zotero API result limit.
     */
    const LIMIT = 100;

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
    protected $resourceClasses = array(
        'dcterms' => array(),
        'dctype'  => array(),
        'bibo'    => array(),
    );

    /**
     * Cache of selected Omeka properties
     *
     * @var array
     */
    protected $properties = array(
        'dcterms' => array(),
        'dctype'  => array(),
        'bibo'    => array(),
    );

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
     * Perform the import.
     */
    public function perform()
    {
        $client = $this->getClient();
        $uri = $this->getFirstUri();

        $this->cacheResourceClasses();
        $this->cacheProperties();

        $this->itemTypeMap = require __DIR__ . '/item_type_map.php';
        $this->itemFieldMap = require __DIR__ . '/item_field_map.php';

        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        $itemSetData = array();
        $itemSet = $api->create('item_sets')->getContent();

        do {
            $request = new Request;
            $request->setUri($uri);
            $request->getHeaders()->addHeaderLine('Zotero-API-Version', '3');

            $response = $client->send($request);
            if (!$response->isSuccess()) {
                throw new Exception\RuntimeException(sprintf(
                    'Requested "%s" got "%s"', $uri, $response->renderStatusLine()
                ));
            }

            $zoteroItems = json_decode($response->getBody(), true);
            if (!is_array($zoteroItems)) {
                return;
            }

            $omekaItems = array();
            foreach ($zoteroItems as $zoteroItem) {
                $omekaItem = array();
                $omekaItem['o:item_set'] = array(array('o:id' => $itemSet->id()));
                $omekaItem = $this->setResourceClass($zoteroItem, $omekaItem);
                $omekaItem = $this->setValues($zoteroItem, $omekaItem);
                $omekaItems[] = $omekaItem;
            }

            $batchCreate = $api->batchCreate('items', $omekaItems);
            if ($batchCreate->isError()) {
                throw new Exception\RuntimeException('There was an error during batch creation');
            }

            if ($this->shouldStop()) {
                // @todo consider performing cleanup before stopping
                break;
            }

        } while ($uri = $this->getLink($response, 'next'));
    }

    /**
     * Get the HTTP client.
     *
     * Uses the cURL adapter if the extension is loaded. Otherwise uses the
     * default socket adapter, setting the sslcapath.
     * 
     * @see http://framework.zend.com/manual/current/en/modules/zend.http.client.html#connecting-to-ssl-urls
     * @return Client
     */
    public function getClient()
    {
        $clientOptions = array();
        if (extension_loaded('curl')) {
            $clientOptions['adapter'] = 'Zend\Http\Client\Adapter\Curl';
        } else {
            $clientOptions['sslcapath'] = '/etc/ssl/certs';
        }
        return new Client(null, $clientOptions);
    }

    /**
     * Get the URI for the first request.
     *
     * @return string
     */
    public function getFirstUri()
    {
        $id = $this->getArg('id');
        if (!$id) {
            throw new Exception\InvalidArgumentException('Invalid id');
        }

        $type = $this->getArg('type');
        if ('user' == $type) {
            $prefix = sprintf('/users/%s', $id);
        } elseif ('group' == $type) {
            $prefix = sprintf('/groups/%s', $id);
        } else {
            throw new Exception\InvalidArgumentException('Invalid library type');
        }

        $collectionKey = $this->getArg('collectionKey');
        if ($collectionKey) {
            $path = sprintf('/collections/%s/items/top', $collectionKey);
        } else {
            $path = '/items/top';
        }

        return sprintf('%s%s%s?limit=%s', self::BASE_URL, $prefix, $path, self::LIMIT);
    }

    /**
     * Get a URI from the Link header.
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
     * Set the mapped resource class to the Omeka item data.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    public function setResourceClass(array $zoteroItem, array $omekaItem)
    {
        $type = $zoteroItem['data']['itemType'];
        if (!isset($this->itemTypeMap[$type])) {
            // The Zotero item type has no mapping.
            return $omekaItem;
        }
        foreach ($this->itemTypeMap[$type] as $prefix => $localName) {
            if (isset($this->resourceClasses[$prefix][$localName])) {
                // There was a match.
                $class = $this->resourceClasses[$prefix][$localName];
                $omekaItem['o:resource_class'] = array('o:id' => $class->id());
                return $omekaItem;
            }
        }
        // There was no match.
        return $omekaItem;
    }

    /**
     * Set the mapped property values to the Omeka item data.
     *
     * @param array $zoteroItem The Zotero item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    public function setValues(array $zoteroItem, array $omekaItem)
    {
        return $omekaItem;
    }
}
