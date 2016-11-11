<?php
namespace ZoteroImport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ZoteroImportItemRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-zotero_import:ZoteroImportItem';
    }

    public function getJsonLd()
    {
        return [
            'o:job' => $this->job()->getReference(),
            'o:item' => $this->job()->getReference(),
        ];
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation($this->resource->getJob());
    }

    public function item()
    {
        return $this->getAdapter('items')
            ->getRepresentation($this->resource->getItem());
    }
}
