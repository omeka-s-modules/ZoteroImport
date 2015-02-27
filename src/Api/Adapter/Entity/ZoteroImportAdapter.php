<?php
namespace ZoteroImport\Api\Adapter\Entity;

use Omeka\Api\Adapter\Entity\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Model\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ZoteroImportAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'zotero_imports';
    }

    public function getRepresentationClass()
    {
        return 'ZoteroImport\Api\Representation\Entity\ZoteroImportRepresentation';
    }

    public function getEntityClass()
    {
        return 'ZoteroImport\Model\Entity\ZoteroImport';
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();

        if (isset($data['o:job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
            $entity->setJob($job);
        }

        if (isset($data['version'])) {
            $entity->setVersion($data['version']);
        }
    }
}
