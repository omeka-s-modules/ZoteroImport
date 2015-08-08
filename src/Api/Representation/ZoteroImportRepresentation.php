<?php
namespace ZoteroImport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class ZoteroImportRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLd()
    {
        return array(
            'version' => $this->getData()->getVersion(),
            'o:job' => $this->getReference(
                null,
                $this->getData()->getJob(),
                $this->getAdapter('jobs')
            ),
        );
    }

    public function job()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation(null, $this->getData()->getJob());
    }

    public function version()
    {
        return $this->getData()->getVersion();
    }

    public function name()
    {
        return $this->getData()->getName();
    }

    public function libraryUrl()
    {
        return $this->getData()->getUrl();
    }
}
