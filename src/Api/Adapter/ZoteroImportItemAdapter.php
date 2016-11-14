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
        return 'ZoteroImport\Api\Representation\ZoteroImportItemRepresentation';
    }

    public function getEntityClass()
    {
        return 'ZoteroImport\Entity\ZoteroImportItem';
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ) {
        $data = $request->getContent();

        $import = $this->getAdapter('zotero_imports')->findEntity($data['o-module-zotero_import:import']['o:id']);
        $item = $this->getAdapter('items')->findEntity($data['o:item']['o:id']);

        $entity->setImport($import);
        $entity->setItem($item);
    }

    public function buildQuery(QueryBuilder $qb, array $query)
    {
        if (isset($query['import_id'])) {
            $qb->andWhere($qb->expr()->eq(
                sprintf('%s.import', $this->getEntityClass()),
                $this->createNamedParameter($qb, $query['import_id']))
            );
        }
    }
}
