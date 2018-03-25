<?php
namespace ZoteroImport\Job;

use Omeka\Api\Exception\NotFoundException;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\MediaRepresentation;
use Omeka\Stdlib\Message;
use Zend\Http\Request;
use Zend\Http\Client;

class Export extends AbstractZoteroSync
{
    /**
     * The base path where files are stored.
     *
     * @var string
     */
    protected $basePath;

    /**
     * Priority map between Omeka resource classes and Zotero item types.
     *
     * @var array
     */
    protected $resourceClassMap = [];

    /**
     * Priority map between Omeka properties and Zotero creator types.
     *
     * @var array
     */
    protected $creatorNameMap = [];

    /**
     * Priority map between Omeka properties and Zotero item fields.
     *
     * @var array
     */
    protected $propertyMap = [];

    /**
     * Zotero has a specific template for each Zotero type of item.
     *
     * @link https://www.zotero.org/support/dev/web_api/v3/types_and_fields#getting_a_template_for_a_new_item
     * @var array
     */
    protected $zoteroTemplates = [];

    /**
     * Existing Zotero items of Omeka items (first one only).
     *
     * @var array
     */
    protected $existingZoteroKeys = [];

    /**
     * Existing Zotero full items of Omeka items (current loop).
     *
     * @var array
     */
    protected $existingZoteroItems = [];

    /**
     * Existing Zotero full child items (files) of Omeka items (current loop).
     *
     * @var array
     */
    protected $existingZoteroChildItems = [];

    /**
     * Items that are currently processed.
     *
     * @var ItemRepresentation[]
     */
    protected $items;

    /**
     * Media that are currently processed.
     *
     * @var MediaRepresentation[]
     */
    protected $media;

    /**
     * Perform the export of a list of items.
     *
     * Accepts the following arguments:
     *
     * - resourceIds: Array of items ids (int)
     * - query: Api query to get items, if no resource ids are provided (array)
     * - type: The Zotero library type (user, group)
     * - id: The Zotero library ID (int)
     * - collectionKey: The Zotero collection key (string) (to be removed)
     * - collectionKeys: The Zotero collection keys (array)
     * - apiKey: The Zotero API key (string)
     * - syncFiles: Whether to export file attachments (bool)
     * - action: What to do with existing items (string)
     * - version: The Zotero Last-Modified-Version of the last import (int)
     * - timestamp: The Omeka dateAdded timestamp (UTC) to begin exporting (int)
     *
     * Roughly follows Zotero's recommended steps for synchronizing a Zotero Web
     * API client with the Zotero server. But for the purposes of this job, a
     * "sync" only exports parent items (and their children) that have been
     * added to Omeka since the passed timestamp. Nevertheless, the user can
     * choose to update previous exported items.
     *
     * @see https://www.zotero.org/support/dev/web_api/v3/syncing#full-library_syncing
     */
    public function perform()
    {
        $services = $this->getServiceLocator();
        $logger = $this->logger = $services->get('Omeka\Logger');
        $api = $this->api = $services->get('Omeka\ApiManager');

        $this->basePath = $services->get('Config')['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        $this->cacheResourceClasses();
        $this->cacheProperties();

        $this->resourceClassMap = $this->loadMapping('resource_class_map');
        $this->itemTypeMap = $this->prepareMapping('item_type_map');
        $this->creatorNameMap =  $this->loadMapping('creator_name_map');
        $this->propertyMap = $this->loadMapping('property_map');

        $this->setImportClient();
        $this->setImportUrl();

        $apiVersion = $this->getArg('version', 0);
        $apiKey = $this->getArg('apiKey');
        $action = $this->getArg('action');

        $resourceIds = $this->getArg('resourceIds', []);
        if (empty($resourceIds)) {
            $query = $this->getArg('query', []);
            $resourceIds = $api->search('items', $query, ['returnScalar' => 'id'])->getContent();
        }

        $args = $this->job->getArgs() ?: [];
        unset($args['resourceIds']);
        unset($args['query']);

        $exportFiles = !empty($args['syncFiles']);

        switch ($action) {
            case self::ACTION_REPLACE:
                $this->existingZoteroKeys = $this->existingZoteroItems($resourceIds);
                break;
        }

        // Batch export the resources in chunks.
        $importId = $this->getArg('import');
        foreach (array_chunk($resourceIds, $this->sizeChunk) as $idsChunk) {
            if ($this->shouldStop() || empty($idsChunk)) {
                return;
            }
            $result = $this->export($idsChunk, $args);
            if ($exportFiles) {
                $attachments = $this->exportAttachments($result, $args);
            }

            // Store the data about the export.
            $exportItems = [];
            foreach ($result as $itemId => $zoteroItem) {
                $exportItems[] = [
                    'o:item' => ['o:id' => $itemId],
                    'o-module-zotero_import:import' => ['o:id' => $importId],
                    'o-module-zotero_import:zotero_key' => $zoteroItem['key'],
                ];
            }
            $api->batchCreate('zotero_import_items', $exportItems, [], ['continueOnError' => true]);

            // TODO Store the data about the files (that have a Zotero key).
            // Currently, files are managed via their filename, that is enough
            // in most of the cases (there are never multiple files attached to
            // an item with the same name).

            // Reset the processed items.
            $this->items = [];
            $exportItems = [];
        }
    }

    /**
     * Export a list of items.
     *
     * @param array $itemIds
     * @param array $args
     * @return array Associative array of item id and Zotero items.
     */
    protected function export(array $itemIds, array $args)
    {
        $zoteroItems = [];

        // Check if some Zotero items exist.
        $existingZoteroKeys = array_intersect_key($this->existingZoteroKeys, array_flip($itemIds));
        if ($existingZoteroKeys) {
            // Use the parents here, and the children if needed for files.
            list($zParentItems, $this->existingZoteroChildItems) = $this->fetchZoteroItems($existingZoteroKeys);
        }

        // Prepare Zotero items.
        foreach ($itemIds as $itemId) {
            try{
                /** @var \Omeka\Api\Representation\ItemRepresentation $item */
                $item = $this->api->read('items', $itemId)->getContent();
            } catch (NotFoundException $e) {
                $this->logger->warn(new Message(
                    'The item #%d was removed before export to Zotero.', // @translate
                    $itemId)
                );
                continue;
            }

            $existing = isset($existingZoteroKeys[$itemId])
                && isset($zParentItems[$existingZoteroKeys[$itemId]])
                ? $zParentItems[$existingZoteroKeys[$itemId]]['data']
                : null;
            $zoteroItem = $this->convertItemToZoteroItem($item, $existing, $args);
            if ($zoteroItem) {
                $zoteroItems[$item->id()] = $zoteroItem;
                $this->items[$item->id()] = $item;
            }

            // Currently, to avoid to duplicate files for update, all existing
            // files are simply removed. Anyway, it is required for files that
            // were removed.
            if ($existing) {
                $attachments = $this->fetchItemAttachments($existing['key']);
                $attachmentKeys = array_map(function ($v) {
                    return $v['key'];
                }, $attachments);
                $this->deleteZoteroItems($attachmentKeys);
            }
        }

        return $this->processExport($zoteroItems);
    }

    /**
     * Export a list of files.
     *
     * @link https://www.zotero.org/support/dev/web_api/v3/file_upload#a_create_a_new_attachment
     *
     * @param array $zoteroItems Associative array by item id.
     * @param array $args
     * @return array Associative array of media ids and Zotero item.
     */
    protected function exportAttachments(array $zoteroItems, array $args)
    {
        $zoteroFiles = [];

        // For update, all previous files were removed, so no check is done.

        foreach ($zoteroItems as $itemId => $zoteroItem) {
            if (empty($zoteroItem['key'])) {
                continue;
            }

            // The item should be cached by doctrine.
            /** @var \Omeka\Api\Representation\ItemRepresentation $item */
            $item = $this->items[$itemId];

            foreach ($item->media() as $media) {
                $existing = isset($existingZoteroKeys[$itemId])
                    && isset($zParentItems[$existingZoteroKeys[$itemId]])
                    ? $zParentItems[$existingZoteroKeys[$itemId]]['data']
                    : null;
                $attachment = $this->convertMediaToZoteroAttachment($media, $zoteroItem['key'], $existing, $args);
                if ($attachment) {
                    $zoteroFiles[$media->id()] = $attachment;
                    $this->media[$media->id()] = $media;
                }
            }
        }

        // Even if it is not the real practice for bibliographic items, there
        // may be more than one attachment by item, so chunk them too.
        // The function array_chunk() works on a copy of the array, so the
        // original can be updated, but it is simpler to copy it before.
        $zoteroFilesResult = [];
        foreach (array_chunk($zoteroFiles, $this->sizeChunk, true) as $zoteroChunk) {
            // Export metadata of attachments.
            $result = $this->processExport($zoteroChunk);
            // Attach each file to attachment, one by one as specified.
            foreach ($result as $mediaId => $zoteroAttachment) {
                $resultUpload = $this->processUpload($this->media[$mediaId], $zoteroAttachment);
                if ($resultUpload) {
                    $zoteroFilesResult[$mediaId] = $resultUpload;
                }
                unset($this->media[$mediaId]);
            }
            // Clear the remaining cached media.
            $this->media = array_diff_key($this->media, $zoteroChunk);
        }

        return $zoteroFilesResult;
    }

    /**
     * Convert metadata of an item into a Zotero item.
     *
     * @param ItemRepresentation $item
     * @param array $existingZoteroItem The existing Zotero item data.
     * @param array $args
     * @return array
     */
    protected function convertItemToZoteroItem(
        ItemRepresentation $item,
        array $existingZoteroItem = null,
        array $args = null
    ) {
        $resourceClass = $item->resourceClass() ? $item->resourceClass()->term() : '';
        $zoteroItemType = $this->mapResourceClassToZoteroItemType($resourceClass);
        $template = $this->fetchZoteroTemplate($zoteroItemType);
        $zoteroItem = $template;

        // Start with zotero values in case of a bad mapping, so creators,
        // collections, etc. will be kept.
        $zoteroValues = $this->mapItemToZoteroValues($item);

        // Furthermore, keep only values that belong to the item type, else
        // Zotero will fail.
        $zoteroItem = array_merge($zoteroItem, array_intersect_key($zoteroValues, $template));

        $zoteroItem['itemType'] = $zoteroItemType;
        $zoteroItem['collections'] = $args['collections'];
        $zoteroItem['creators'] = $this->mapItemToZoteroCreators($item);
        $zoteroItem['tags'] = $this->mapResourceToZoteroTags($item);

        // Check if this is an update.
        if ($existingZoteroItem) {
            $zoteroItem['key'] = $existingZoteroItem['key'];
            $zoteroItem['version'] = $existingZoteroItem['version'];
            $zoteroItem['dateAdded'] = $existingZoteroItem['dateAdded'];
            $zoteroItem['dateModified'] = $existingZoteroItem['dateModified'];
        }

        return $zoteroItem;
    }

    /**
     * Convert metadata of a media (only files) into a Zotero attachment.
     *
     * Note: Currently, files are not managed separately from the items and
     * their keys are not saved. So they are managed via their source filename,
     * generally unique for each item.
     *
     * @param MediaRepresentation $media
     * @param string $parentItemKey
     * @param array $existingZoteroItem The existing Zotero attachment data.
     * @param array $args
     * @return array
     */
    protected function convertMediaToZoteroAttachment(
        MediaRepresentation $media,
        $parentItemKey,
        array $existingZoteroItem = null,
        array $args = null
    ) {
        if (!$media->hasOriginal() || $media->renderer() !== 'file') {
            return;
        }

        $zoteroItemType = 'attachment';
        // The url mode may or may not be kept according to the import process.
        // TODO Add an option for imported_url, that are generally a local place in Omeka.
        // $linkedMode = $media->ingester() === 'url' ? 'imported_url' : 'imported_file';
        $linkedMode = 'imported_file';

        $template = $this->fetchZoteroTemplate($zoteroItemType, $linkedMode);
        $zoteroItem = $template;

        $zoteroItem['parentItem'] = $parentItemKey;
        $value = $media->value('dcterms:title', ['type' => 'literal']);
        $zoteroItem['title'] = $value ? $value->value() : basename($media->source());
        if ($linkedMode === 'imported_url') {
            $zoteroItem['url'] = $media->originalUrl();
        }
        $zoteroItem['tags'] = $this->mapResourceToZoteroTags($media);
        $zoteroItem['contentType'] = $media->mediaType();
        $zoteroItem['filename'] = basename($media->source());
        // Don't add the collections to the attachment: it is possible, but it
        // does not match the Omeka process.

        return $zoteroItem;
    }

    /**
     * Map Omeka resource class to Zotero item type (document by default).
     *
     * @param string The Zotero item data
     * @return string
     */
    protected function mapResourceClassToZoteroItemType($resourceClass)
    {
        return isset($this->resourceClassMap[$resourceClass])
            ? $this->resourceClassMap[$resourceClass]
            : 'document';
    }

    /**
     * Map Omeka item to Zotero creator names.
     *
     * @todo Manage non-literal creators.
     * @param ItemRepresentation $item
     * @return array
     */
    protected function mapItemToZoteroCreators(ItemRepresentation $item)
    {
        $result = [];
        foreach ($this->creatorNameMap as $term => $creatorType) {
            $values = $item->value($term, ['type' => 'literal', 'all' => true, 'default' => []]);
            foreach ($values as $value) {
                $zoteroValue = [];
                $zoteroValue['creatorType'] = $creatorType;
                $zoteroValue['name'] = $value->value();
                $result[] = $zoteroValue;
            }
        }
        return $result;
    }

    /**
     * Map Omeka resource Dublin Core subjects to Zotero tags.
     *
     * @todo Manage non-literal subject.
     * @param AbstractResourceEntityRepresentation $resource
     * @return array
     */
    protected function mapResourceToZoteroTags(AbstractResourceEntityRepresentation $resource)
    {
        $result = [];
        $values = $resource->value('dcterms:subject', ['type' => 'literal', 'all' => true, 'default' => []]);
        foreach ($values as $value) {
            $zoteroValue = [];
            $zoteroValue['tag'] = $value->value();
            $result[] = $zoteroValue;
        }
        return $result;
    }

    /**
     * Map Omeka item properties to Zotero values.
     *
     * Note: Zotero manages only one value by field, except for people-related
     * fields that are managed as creators.
     *
     * @todo Manage property mapping according to the item type.
     * @todo Manage non-literal values.
     * @param ItemRepresentation $item
     * @return array
     */
    protected function mapItemToZoteroValues(ItemRepresentation $item)
    {
        $result = [];
        foreach ($this->propertyMap as $term => $field) {
            $value = $item->value($term, ['type' => 'literal']);
            if ($value) {
                $result[$field] = $value->value();
            }
        }
        return $result;
    }

    /**
     * Process the export via a post request to Zotero.
     *
     * @param array $zoteroItems Associative array with item or media id as key.
     * @return array
     */
    protected function processExport(array $zoteroItems)
    {
        if (empty($zoteroItems)) {
            return [];
        }

        $itemIds = array_keys($zoteroItems);

        // Export Zotero items as a collection.
        $url = $this->url->items();
        // Set a new client to set the default headers.
        $this->setImportClient();
        $client = $this->client;
        $client->setMethod(Request::METHOD_POST);
        $client->setUri($url);

        // Add a unique key to avoid issues because versions are not managed.
        // @link https://www.zotero.org/support/dev/web_api/v3/write_requests#zotero-write-token
        $request = $client->getRequest();
        $headers = $request->getHeaders();
        $headers->addHeaderLine('Zotero-Write-Token', $this->randomString(32));
        $headers->addHeaderLine('Content-type', 'application/json');

        $client->setRawBody(json_encode(array_values($zoteroItems)));

        // The client may throw an error.
        $response = $this->getResponse($url);

        $zoteroResponse = json_decode($response->getBody(), true) ?: [];
        if (!empty($zoteroResponse['success'])) {
            foreach ($zoteroResponse['success'] as $key => $zoteroKey) {
                $zoteroItems[$itemIds[$key]]['key'] = $zoteroKey;
            }
        }
        if (!empty($zoteroResponse['unchanged'])) {
            foreach ($zoteroResponse['unchanged'] as $key => $zoteroKey) {
                $zoteroItems[$itemIds[$key]]['key'] = $zoteroKey;
            }
        }
        if (!empty($zoteroResponse['failed'])) {
            foreach ($zoteroResponse['failed'] as $key => $zoteroError) {
                $zoteroKey = isset($zoteroItems[$itemIds[$key]]['key']) ? $zoteroItems[$itemIds[$key]]['key'] : '';
                unset($zoteroItems[$itemIds[$key]]);
                $this->logger->err(new Message(
                    'Error [%1$s] during export of item #%2$d (key "%3$s"): %4$s', // @translate
                    $zoteroError['code'], $itemIds[$key], $zoteroKey, $zoteroError['message']
                ));
            }
        }

        return $zoteroItems;
    }

    /**
     * Upload a file to the specified attachment.
     *
     * @link https://www.zotero.org/support/dev/web_api/v3/file_upload#a_create_a_new_attachment
     *
     * @param MediaRepresentation $media
     * @param array $zoteroAttachments Associative array with media id.
     * @return array
     */
    protected function processUpload(MediaRepresentation $media, array $zoteroAttachment)
    {
        if (empty($zoteroAttachment)
            || empty($zoteroAttachment['key'])
        ) {
            return;
        }

        // The media is already checked to create attachment, but not the file.
        $file = $this->basePath . '/original/' . $media->filename();
        if (!file_exists($file)) {
            $this->logger->err(new Message(
                'Unable to read the file of the media #%1$s.', // @translate
                $media->id()
            ));
            return;
        }

        // Step 1: Create/get the attachment (done).
        // Step 2: Get upload authorization.
        $url = $this->url->itemFile($zoteroAttachment['key']);
        // Set a new client to set the default headers.
        $this->setImportClient();
        $client = $this->client;
        $client->setMethod(Request::METHOD_POST);
        $client->setUri($url);

        $request = $client->getRequest();
        $headers = $request->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/x-www-form-urlencoded');
        $headers->addHeaderLine('If-None-Match', '*');

        $paramsFile = [
            'md5' => md5_file($file),
            'filename' => basename($media->source()),
            'filesize' => filesize($file),
            'mtime' => $media->created()->format('U') . '000',
        ];
        $client->setParameterPost($paramsFile);

        // The client may throw an error.
        $response = $this->getResponse($url);

        // The file may exist even if it is not uploaded: another user may have
        // uploaded it before.
        $zoteroResponse = json_decode($response->getBody(), true) ?: [];
        if (!empty($zoteroResponse['exists'])) {
            return $zoteroAttachment;
        }
        $upload = $zoteroResponse;

        // Step 3a: Send the file.
        // Step 3a i: Post file.
        $url = $upload['url'];
        // The client is reset: this is not an export to Zotero, but to Amazon.
        $this->setImportClient();
        $this->client = new Client();
        $client = $this->client;
        $client->setMethod(Request::METHOD_POST);
        $client->setUri($url);

        $request = $client->getRequest();
        $headers = $request->getHeaders();
        $headers =

        $headers->addHeaderLine('Content-type', 'multipart/form-data');
        $headers->addHeaderLine('Content-type', $upload['contentType']);

        $client->setRawBody(
            $upload['prefix']
            . file_get_contents($file)
            . $upload['suffix']
        );

        // The client may throw an error, else there is no specific response.
        $response = $this->getResponse($url);

        // Step 3a ii: Register the file.
        $url = $this->url->itemFile($zoteroAttachment['key']);
        $this->setImportClient();
        $client = $this->client;
        $client->setMethod(Request::METHOD_POST);
        $client->setUri($url);

        $request = $client->getRequest();
        $headers = $request->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/x-www-form-urlencoded');
        $headers->addHeaderLine('If-None-Match', '*');

        $client->setParameterPost([
            'upload' => $upload['uploadKey'],
        ]);

        // The client may throw an error, else there is no specific response.
        $response = $this->getResponse($url);

        return $zoteroAttachment;
    }

    /**
     * Get the Zotero template for a specific item type (document by default).
     *
     * @link https://www.zotero.org/support/dev/web_api/v3/types_and_fields#getting_a_template_for_a_new_item
     * @param string $zoteroItemType
     * @param string $linkMode Needed for template "attachment."
     * @return array
     */
    protected function fetchZoteroTemplate($zoteroItemType, $linkMode = null)
    {
        if (!isset($this->itemTypeMap[$zoteroItemType])) {
            $zoteroItemType = 'document';
        }
        if (!isset($this->zoteroTemplates[$zoteroItemType])) {
            // The client is reset to avoid issue with the write token.
            $this->setImportClient();
            $url = $this->url->template($zoteroItemType, $linkMode);
            $template = json_decode($this->getResponse($url)->getBody(), true);
            $this->zoteroTemplates[$zoteroItemType] = $template;
        }
        return $this->zoteroTemplates[$zoteroItemType];
    }

    protected function randomString($length = 32)
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyz';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
}
