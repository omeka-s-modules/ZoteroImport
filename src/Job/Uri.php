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
    protected $type;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $collectionKey;

    /**
     * @var int
     */
    protected $limit = 100;

    /**
     * Construct a Zotero URI.
     *
     * @param string $type The Zotero library type, "user" or "group"
     * @param int $id The Zotero library ID
     */
    public function __construct($type, $id)
    {
        if (!in_array($type, array('user', 'group'))) {
            throw new \InvalidArgumentException('Invalid Zotero library type');
        }
        if (!is_numeric($id)) {
            throw new \InvalidArgumentException('Invalid Zotero library ID');
        }
        $this->type = $type;
        $this->id = $id;
    }

    /**
     * Set a collection key.
     *
     * @param string $collectionKey
     */
    public function setCollectionKey($collectionKey)
    {
        $this->collectionKey = $collectionKey;
    }

    /**
     * Set a result limit.
     *
     * @param int $limit
     */
    public function setLimit($limit)
    {
        if (!is_numeric($limit)) {
            throw new \InvalidArgumentException('Invalid Zotero API result limit');
        }
        $this->limit = $limit;
    }

    /**
     * Get the Zotero URI.
     *
     * @param string $itemKey Query an item by key
     * @param bool $children Query item children
     * @param bool $file Query item file
     * @return string
     */
    public function getUri($itemKey = null, $children = false, $file = false)
    {
        if ('user' == $this->type) {
            $path = sprintf('/users/%s', $this->id);
        } else {
            $path = sprintf('/groups/%s', $this->id);
        }
        if ($this->collectionKey && !$itemKey) {
            $path .= sprintf('/collections/%s', $this->collectionKey);
        }
        $path .= '/items';
        if ($itemKey) {
            $path .= sprintf('/%s', $itemKey);
            if ($children) {
                $path .= '/children';
            } elseif ($file) {
                $path .= '/file';
            }
        } else {
            $path .= '/top';
        }
        return sprintf('%s%s?limit=%s', self::BASE, $path, $this->limit);
    }
}
