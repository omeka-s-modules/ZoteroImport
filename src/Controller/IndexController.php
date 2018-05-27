<?php
namespace ZoteroImport\Controller;

use DateTime;
use DateTimeZone;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;
use Zend\Http\Client;
use Zend\Http\Response;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZoteroImport\Form\ExportForm;
use ZoteroImport\Form\ImportForm;
use ZoteroImport\Job;
use ZoteroImport\Zotero\Url;

class IndexController extends AbstractActionController
{
    /**
     * @var Client
     */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function importAction()
    {
        $form = $this->getForm(ImportForm::class);

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->getData();
                $timestamp = 0;
                if ($data['addedAfter']) {
                    $addedAfter = new DateTime($data['addedAfter'],
                        new DateTimeZone('UTC'));
                    $timestamp = (int) $addedAfter->format('U');
                }
                $args = [
                    'itemSet'       => $data['itemSet'],
                    'type'          => $data['type'],
                    'id'            => $data['id'],
                    'collectionKey' => $data['collectionKey'],
                    'apiKey'        => $data['apiKey'],
                    'syncFiles'     => (bool) $data['syncFiles'],
                    'action'        => $data['action'],
                    'version'       => 0,
                    'timestamp'     => $timestamp,
                ];

                if ($args['apiKey'] && !($username = $this->apiKeyIsValid($args))) {
                    $this->messenger()->addError(
                        'Cannot import the Zotero library using the provided API key' // @translate
                    );
                } else {
                    $response = $this->sendApiRequest($args);
                    $body = json_decode($response->getBody(), true);
                    if ($response->isSuccess()) {
                        // The body is empty if the collection is empty.
                        if (empty($body)) {
                            // TODO Manage private group number (we don't know if the group is private here).
                            $usernameClean = str_replace(' ', '_', preg_replace("/[^A-Za-z0-9 ]/", '', strtolower($username)));
                            $import = $this->api()->create('zotero_imports', [
                                'o-module-zotero_import:version' => 0,
                                'o-module-zotero_import:name' => $username,
                                'o-module-zotero_import:url' => 'https://www.zotero.org/' . $usernameClean,
                            ])->getContent();
                        } else {
                            $import = $this->api()->create('zotero_imports', [
                                'o-module-zotero_import:version' => $response->getHeaders()->get('Last-Modified-Version')->getFieldValue(),
                                'o-module-zotero_import:name' => $body[0]['library']['name'],
                                'o-module-zotero_import:url' => $body[0]['library']['links']['alternate']['href'],
                            ])->getContent();
                        }
                        $args['import'] = $import->id();
                        $job = $this->jobDispatcher()->dispatch(Job\Import::class, $args);
                        $this->api()->update('zotero_imports', $import->id(), [
                            'o:job' => ['o:id' => $job->getId()],
                        ]);
                        $message = new Message(
                            'Importing from Zotero. %s', // @translate
                            sprintf(
                                '<a href="%s">%s</a>',
                                htmlspecialchars($this->url()->fromRoute(null, [], true)),
                                $this->translate('Import another?')
                            ));
                        $message->setEscapeHtml(false);
                        $this->messenger()->addSuccess($message);
                        return $this->redirect()->toRoute('admin/zotero/default', ['action' => 'browse']);
                    } else {
                        $this->messenger()->addError(sprintf(
                            'Error when requesting Zotero library: %s', // @translate
                            $response->getReasonPhrase()
                        ));
                    }
                }
            } else {
                $this->messenger()->addFormErrors($form);
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }

    /**
     * Batch export selected items or all items returned from a query.
     */
    public function exportAction()
    {
        if ($this->getRequest()->isGet()) {
            $params = $this->params()->fromQuery();
        } elseif ($this->getRequest()->isPost()) {
            $params = $this->params()->fromPost();
        } else {
            return $this->redirect()->toRoute('admin');
        }

        // Set default values to simplify checks.
        $params += array_fill_keys(['resource_type', 'resource_ids', 'query', 'batch_action', 'zotero_all'],null);

        $resourceType = $params['resource_type'];
        $resourceTypeMap = [
            'item' => 'items',
            'item-set' => 'item_sets',
            'items' => 'items',
            'item_sets' => 'item_sets',
        ];
        if (!isset($resourceTypeMap[$resourceType])) {
            $this->messenger()->addError('You can export to Zotero only items and item sets.'); // @translate
            return $this->redirect()->toRoute('admin');
        }

        $resource = $resourceTypeMap[$resourceType];
        $resourceIds = $params['resource_ids']
            ? (is_array($params['resource_ids']) ? $params['resource_ids'] : explode(',', $params['resource_ids']))
            : [];
        $params['resource_ids'] = $resourceIds;
        $selectAll = $params['batch_action'] ? $params['batch_action'] === 'zotero-all' : (bool) $params['zotero_all'];
        $params['batch_action'] = $selectAll ? 'zotero-all' : 'zotero-selected';

        $query = null;
        $resources = [];

        if ($selectAll) {
            // Derive the query, removing limiting and sorting params.
            $query = json_decode($params['query'] ?: [], true);
            unset($query['submit'], $query['page'], $query['per_page'], $query['limit'],
                $query['offset'], $query['sort_by'], $query['sort_order']);
        }

        // Export of item sets is managed like a query for all their items.
        $itemSets = [];
        $itemSetCount = 0;
        $itemSetQuery= null;
        $itemQuery = $query;

        if ($selectAll || $resource === 'item_sets') {
            if ($resource === 'item_sets') {
                if ($selectAll) {
                    $itemSetQuery = $query;
                    $itemSetIds = $this->api()->search('item_sets', $itemSetQuery, ['returnScalar' => 'id'])->getContent();
                } else {
                    $itemSetIds = $resourceIds;
                    foreach ($itemSetIds as $resourceId) {
                        $itemSets[] = $this->api()->read('item_sets', $resourceId)->getContent();
                    }
                }
                if (empty($itemSetIds)) {
                    $this->messenger()->addError('You must select at least one item set with items to export to Zotero.'); // @translate
                    return $this->redirect()->toRoute('admin/default', ['controller' => $resourceType, 'action' => 'browse'], true);
                }
                $itemQuery = ['item_set_id' => $itemSetIds];
                $itemSetCount = count($itemSetIds);
            }

            $count = $this->api()->search('items', $itemQuery)->getTotalResults();
            if (!$count) {
                $this->messenger()->addError('You must select at least one resource to export to Zotero.'); // @translate
                return $this->redirect()->toRoute('admin/default', ['controller' => $resourceType, 'action' => 'browse'], true);
            }
        }

        // Export of selected items.
        else {
            if (empty($resourceIds)) {
                $this->messenger()->addError('You must select at least one resource to export to Zotero.'); // @translate
                return $this->redirect()->toRoute('admin/default', ['controller' => $resourceType, 'action' => 'browse'], true);
            }
            foreach ($resourceIds as $resourceId) {
                $resources[] = $this->api()->read($resource, $resourceId)->getContent();
            }
            $count = count($resources);
        }

        $form = $this->getForm(ExportForm::class);
        $form->setAttribute('id', 'zotero-export');
        if ($this->params()->fromPost('batch_process')) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->getData();
                $timestamp = 0;

                // TODO Check write rights for each Zotero collection.
                $collections = array_map('strtoupper', array_filter(explode(' ',
                    preg_replace('~[^A-Za-z0-9 ]~', ' ', $data['collectionKey'])
                )));
                $firstCollectionKey = $collections ? $collections[0] : null;

                // The same read request than import is sent to check Zotero
                // before true process, even if this is not the true args.
                // TODO To be cleaned and factorized.
                if ($data['addedAfter']) {
                    $addedAfter = new DateTime($data['addedAfter'],
                        new DateTimeZone('UTC'));
                    $timestamp = (int) $addedAfter->format('U');
                }
                $args = [
                    'type' => $data['type'],
                    'id' => $data['id'],
                    // TODO Remove "collectionKey", kept for compatibility with Url.
                    'collectionKey' => $firstCollectionKey,
                    'collections' => $collections,
                    'apiKey' => $data['apiKey'],
                    'syncFiles' => $data['syncFiles'],
                    'action' => $data['action'],
                    'version' => 0,
                    'timestamp' => $timestamp,
                ];

                $username = $this->apiKeyIsValid($args);
                if (!$username) {
                    $this->messenger()->addError(
                        'Cannot export the Zotero library using the provided API key' // @translate
                    );
                } else {
                    $response = $this->sendApiRequest($args);
                    $body = json_decode($response->getBody(), true);

                    if ($response->isSuccess()) {
                        // TODO Manage Zotero import and export in the tables.
                        // "import" is used currently to avoid to upgrade the
                        // database tables.
                        // The body is empty if the collection is empty.
                        if (empty($body)) {
                            // TODO Manage private group number (we don't know if the group is private here).
                            $usernameClean = str_replace(' ', '_', preg_replace("/[^A-Za-z0-9 ]/", '', strtolower($username)));
                            $import = $this->api()->create('zotero_imports', [
                                'o-module-zotero_import:version' => 0,
                                'o-module-zotero_import:name' => $username,
                                'o-module-zotero_import:url' => 'https://www.zotero.org/' . $usernameClean,
                            ])->getContent();
                        } else {
                            $import = $this->api()->create('zotero_imports', [
                                'o-module-zotero_import:version' => $response->getHeaders()->get('Last-Modified-Version')->getFieldValue(),
                                'o-module-zotero_import:name' => $body[0]['library']['name'],
                                'o-module-zotero_import:url' => $body[0]['library']['links']['alternate']['href'],
                            ])->getContent();
                        }
                        $args['import'] = $import->id();

                        $byIds = $resource === 'items' && $resourceIds;
                        $args['resourceIds'] = $byIds ? $resourceIds : [];
                        $args['query'] = $itemQuery;

                        $job = $this->jobDispatcher()->dispatch(Job\Export::class, $args);

                        $this->api()->update('zotero_imports', $import->id(), [
                            'o:job' => ['o:id' => $job->getId()],
                        ]);
                        $urlHelper = $this->viewHelpers()->get('url');
                        $message = new Message(
                            'Exporting %1$s items to Zotero. This may take a while.', // @translate
                            $byIds
                                ? $count
                                : sprintf(
                                    '<a href="%1$s">%2$d</a>',
                                    htmlspecialchars($urlHelper('admin/default', ['controller' => 'item', 'action' => 'browse'], ['query' => $itemQuery])),
                                    $count
                                )
                        );
                        $message->setEscapeHtml(false);
                        $this->messenger()->addSuccess($message);
                        return $this->redirect()->toRoute('admin/zotero/default', ['action' => 'browse']);
                    }

                    $this->messenger()->addError(sprintf(
                        'Error when requesting Zotero library: %s', // @translate
                        $response->getReasonPhrase()
                    ));
                }
            }

            $this->messenger()->addFormErrors($form);
        } else {
            // Keep hidden the values from the browse page.
            $params['resource_ids'] = implode(',', $params['resource_ids']);
            $form->setData($params);
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        // Keep current request.
        $view->setVariable('selectAll', $selectAll);
        $view->setVariable('resourceType', $resourceType);
        $view->setVariable('resourceIds', $resourceIds);
        $view->setVariable('query', $query);
        // Complete to display info about the resources to export.
        $view->setVariable('resources', $resources);
        $view->setVariable('count', $count);
        $view->setVariable('itemQuery', $itemQuery);
        $view->setVariable('itemSetQuery', $itemSetQuery);
        $view->setVariable('itemSets', $itemSets);
        $view->setVariable('itemSetCount', $itemSetCount);
        return $view;
    }

    public function browseAction()
    {
        $this->setBrowseDefaults('id');
        $response = $this->api()->search('zotero_imports', $this->params()->fromQuery());
        $this->paginator($response->getTotalResults(), $this->params()->fromQuery('page'));

        $view = new ViewModel;
        $view->setVariable('imports', $response->getContent());
        return $view;
    }

    public function undoConfirmAction()
    {
        $import = $this->api()
            ->read('zotero_imports', $this->params('id'))->getContent();
        $form = $this->getForm(ConfirmForm::class);
        $form->setAttribute('action', $import->url('undo'));

        $view = new ViewModel;
        $view->setTerminal(true);
        $view->setTemplate('zotero-import/index/undo-confirm');
        $view->setVariable('import', $import);
        $view->setVariable('form', $form);
        return $view;
    }

    public function undoAction()
    {
        if ($this->getRequest()->isPost()) {
            $import = $this->api()
                ->read('zotero_imports', $this->params('id'))->getContent();
            if (in_array($import->job()->status(), ['completed', 'stopped', 'error'])) {
                $form = $this->getForm(ConfirmForm::class);
                $form->setData($this->getRequest()->getPost());
                if ($form->isValid()) {
                    $args = ['import' => $import->id()];
                    $job = $this->jobDispatcher()->dispatch(Job\UndoImport::class, $args);
                    $this->api()->update('zotero_imports', $import->id(), [
                        'o-module-zotero_import:undo_job' => ['o:id' => $job->getId()],
                    ]);
                    $this->messenger()->addSuccess('Undoing Zotero import'); // @translate
                } else {
                    $this->messenger()->addFormErrors($form);
                }
            }
        }
        return $this->redirect()->toRoute(null, ['action' => 'browse'], true);
    }

    /**
     * Validate a Zotero API key.
     *
     * @param array $args
     * @return string|bool Return the username of the api key, or false.
     */
    protected function apiKeyIsValid(array $args)
    {
        $url = Url::key($args['apiKey']);
        $response = $this->client->resetParameters()->setUri($url)->send();
        if (!$response->isSuccess()) {
            return false;
        }
        $key = json_decode($response->getBody(), true);
        if ('user' == $args['type']
            && $key['userID'] == $args['id']
            && isset($key['access']['user']['library'])
        ) {
            // The user IDs match and the key has user library access.
            return $key['username'];
        }
        if ('group' == $args['type']
            && (isset($key['access']['groups']['all']['library'])
                || isset($key['access']['groups'][$args['id']]['library']))
        ) {
            // It appears that the key has group library access.
            return $key['username'];
        }
        return false;
    }

    /**
     * Send a Zotero API request.
     *
     * @param array $args
     * @return Response
     */
    protected function sendApiRequest(array $args)
    {
        $params = ['limit' => 1, 'since' => '0'];
        $url = new Url($args['type'], $args['id']);
        if ($collectionKey = $args['collectionKey']) {
            $url = $url->collectionItems($collectionKey, $params);
        } else {
            $url = $url->items($params);
        }
        $headers = ['Zotero-API-Version' => '3'];
        if ($args['apiKey']) {
            $headers['Authorization'] = sprintf('Bearer %s', $args['apiKey']);
        }
        $client = $this->client->resetParameters();
        $client->setHeaders($headers);
        return $client->setUri($url)->send();
    }
}
