<?php
namespace ZoteroImport\Api\Representation\Entity;

use Omeka\Api\Representation\Entity\AbstractEntityRepresentation;

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

    public function version()
    {
        return $this->getData()->getVersion();
    }

    public function owner()
    {
        return $this->getAdapter('jobs')
            ->getRepresentation(null, $this->getData()->getJob());
    }
}
