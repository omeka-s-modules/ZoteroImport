<?php
namespace ZoteroImport\Http;

use Zend\Http\Client;
use Zend\Http\Response;
use Zend\ServiceManager\ServiceLocatorInterface;

class ZoteroClient
{
    /**
     * Zotero API base URL.
     */
    const BASE_URL = 'https://api.zotero.org';

    /**
     * @var Client The HTTP client.
     */
    protected $client;

    /**
     * Compose the HTTP client.
     *
     * @param array $args
     */
    public function __construct(ServiceLocatorInterface $serviceLocator)
    {
        $client = $serviceLocator->get('Omeka\HttpClient');
        $client->setHeaders(array('Zotero-API-Version' => '3'));
        $this->client = $client;
    }

    /**
     * Set the request URI.
     *
     * @param string $uri
     */
    public function setUri($uri)
    {
        $this->client->setUri($uri);
        return $this;
    }

    /**
     * Send the request.
     *
     * @param string $uri
     * @return Response
     */
    public function send()
    {
        return $this->client->send();
    }

    /**
     * Get the URI for the first request.
     *
     * @param string $type The Zotero library type, "user" or "group"
     * @param int $id The Zotero library identifier
     * @param string $collectionKey The Zotero collection key
     * @param int $limit The Zotero API result limit
     * @return string
     */
    public function getFirstUri($type, $id, $collectionKey = null, $limit = 100)
    {
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid Zotero library ID');
        }

        if (!is_numeric($limit)) {
            throw new \InvalidArgumentException('Invalid Zotero API result limit');
        }

        if ('user' == $type) {
            $prefix = sprintf('/users/%s', $id);
        } elseif ('group' == $type) {
            $prefix = sprintf('/groups/%s', $id);
        } else {
            throw new \InvalidArgumentException('Invalid Zotero library type');
        }

        if ($collectionKey) {
            $path = sprintf('/collections/%s/items/top', $collectionKey);
        } else {
            $path = '/items/top';
        }

        return sprintf('%s%s%s?limit=%s', self::BASE_URL, $prefix, $path, $limit);
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
}
