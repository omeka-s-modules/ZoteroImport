<?php
namespace ZoteroImport\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class ZoteroImportItemAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'zotero_import_items';
    }

    public function getRepresentationClass()
    {
        return \ZoteroImport\Api\Representation\ZoteroImportItemRepresentation::class;
    }

    public function getEntityClass()
    {
        return \ZoteroImport\Entity\ZoteroImportItem::class;
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();
        if ($data['o:item']['o:id']) {
            $item = $this->getAdapter('items')->findEntity($data['o:item']['o:id']);
            $entity->setItem($item);
        }
        if (isset($data['o-module-zotero_import:import']['o:id'])) {
            $import = $this->getAdapter('zotero_imports')->findEntity($data['o-module-zotero_import:import']['o:id']);
            $entity->setImport($import);
        }
        if ($data['o-module-zotero_import:zotero_key']) {
            $entity->setZoteroKey($data['o-module-zotero_import:zotero_key']);
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['import_id'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.import',
                $this->createNamedParameter($qb, $query['import_id']))
            );
        }
    }
}
