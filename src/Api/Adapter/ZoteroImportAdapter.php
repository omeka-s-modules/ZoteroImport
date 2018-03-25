<?php
namespace ZoteroImport\Api\Adapter;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ZoteroImportAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'zotero_imports';
    }

    public function getRepresentationClass()
    {
        return \ZoteroImport\Api\Representation\ZoteroImportRepresentation::class;
    }

    public function getEntityClass()
    {
        return \ZoteroImport\Entity\ZoteroImport::class;
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();

        if (isset($data['o:job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
            $entity->setJob($job);
        }
        if (isset($data['o-module-zotero_import:undo_job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o-module-zotero_import:undo_job']['o:id']);
            $entity->setUndoJob($job);
        }

        if (isset($data['o-module-zotero_import:version'])) {
            $entity->setVersion($data['o-module-zotero_import:version']);
        }
        if (isset($data['o-module-zotero_import:name'])) {
            $entity->setName($data['o-module-zotero_import:name']);
        }
        if (isset($data['o-module-zotero_import:url'])) {
            $entity->setUrl($data['o-module-zotero_import:url']);
        }
    }
}
