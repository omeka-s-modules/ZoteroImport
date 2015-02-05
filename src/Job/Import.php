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
     * The Zotero API base URL
     */
    const BASE_URL = 'https://api.zotero.org';

    /**
     * Perform the import.
     */
    public function perform()
    {
        $client = $this->getClient();
        $uri = $this->getFirstUri();

        // @todo create omeka item set

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

            $items = json_decode($response->getBody(), true);
            if (!is_array($items)) {
                return;
            }
            foreach ($items as $item) {
                // @todo map zotero item to omeka item, assign to item set
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

        return sprintf('%s%s%s', self::BASE_URL, $prefix, $path);
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
