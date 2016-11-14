<?php
namespace ZoteroImport\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Job\AbstractJob;

class UndoImport extends AbstractJob
{
    public function perform()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $importItems = $api->search('zotero_import_items', [
            'import_id' => $this->getArg('import'),
        ])->getContent();

        $i = 0;
        foreach ($importItems as $importItem) {
            if ($i++ % 50 == 0) {
                if ($this->shouldStop()) {
                    return;
                }
            }
            // Must delete the import item first because, otherwise, Doctrine
            // detects an unmanaged item entity at ZoteroImportItem#item on
            // flush and doesn't know what to do with it.
            try {
                $api->delete('zotero_import_items', $importItem->id());
                $api->delete('items', $importItem->item()->id());
            } catch (NotFoundException $e) {
                // Ignore a "not found" exception if an item is deleted during
                // this iteration.
                continue;
            }
        }
    }
}
