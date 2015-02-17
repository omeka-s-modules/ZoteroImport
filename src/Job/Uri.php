<?php
namespace ZoteroImport\Job;

class Uri
{
    /**
     * Zotero API base URI.
     */
    const BASE = 'https://api.zotero.org';

    /**
     * @var string
     */
    protected $uri;

    /**
     * Set a Zotero URI.
     *
     * @param string $type The Zotero library type, "user" or "group"
     * @param int $id The Zotero library identifier
     * @param string $collectionKey The Zotero collection key
     * @param int $limit The Zotero API result limit
     * @return string
     */
    public function __construct($type, $id, $collectionKey = null, $limit = 100)
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

        $this->uri = sprintf('%s%s%s?limit=%s', self::BASE, $prefix, $path, $limit);
    }

    /**
     * Get the Zotero URI.
     *
     * @return string
     */
    public function getUri()
    {
        return $this->uri;
    }
}
