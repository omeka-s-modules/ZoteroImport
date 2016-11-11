<?php
namespace ZoteroImport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ZoteroImportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o:ZoteroImport';
    }

    public function getJsonLd()
    {
        return [
            'version' => $this->resource->getVersion(),
            'o:job' => $this->getReference(
                null,
                $this->resource->getJob(),
                $this->getAdapter('jobs')
            ),
        ];
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }

    public function version()
    {
        return $this->resource->getVersion();
    }

    public function name()
    {
        return $this->resource->getName();
    }

    public function libraryUrl()
    {
        return $this->resource->getUrl();
    }

    public function itemIds()
    {
        $itemIds = [];
        foreach ($this->resource->getItems() as $item) {
            $itemIds[] = $item->getItem()->getId();
        }
        return $itemIds;
    }
}
