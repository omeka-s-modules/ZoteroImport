<?php
namespace ZoteroImport\Job;

use Omeka\Job\AbstractJob;

class UndoImport extends AbstractJob
{
    public function perform()
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $importItems = $api->search('zotero_import_items', [
            'job_id' => $this->getArg('job'),
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
            $api->delete('zotero_import_items', $importItem->id());
            $api->delete('items', $importItem->item()->id());
        }
    }
}
