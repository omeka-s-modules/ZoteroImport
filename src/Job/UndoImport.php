<?php
namespace ZoteroImport\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class UndoImport extends AbstractJob
{
    public function perform()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $em = $this->getServiceLocator()->get('Omeka\EntityManager');

        // Use DQL instead of API search to get the item IDs so Doctrine doesn't
        // have to manage the import items, which speeds up item delete.
        $query = $em->createQuery('
        SELECT i.id
        FROM ZoteroImport\Entity\ZoteroImportItem ii
        JOIN ii.item i
        WHERE ii.import = ?1');
        $items = $query->setParameter(1, $this->getArg('import'))->getResult();

        $i = 0;
        foreach ($items as $item) {
            if ($i++ % 50 == 0) {
                if ($this->shouldStop()) {
                    return;
                }
            }
            try {
                $api->delete('items', $item['id']);
            } catch (NotFoundException $e) {
                // Ignore a "not found" exception if an item is deleted during
                // this iteration.
                continue;
            }
        }
    }
}
