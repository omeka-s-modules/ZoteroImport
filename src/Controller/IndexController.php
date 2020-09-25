<?php
namespace ZoteroImport\Controller;

use DateTime;
use DateTimeZone;
use Omeka\Form\ConfirmForm;
use Omeka\Stdlib\Message;
use Laminas\Http\Client;
use Laminas\Http\Response;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\View\Model\ViewModel;
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
                    'itemSet' => $data['itemSet'],
                    'type' => $data['type'],
                    'id' => $data['id'],
                    'collectionKey' => $data['collectionKey'],
                    'apiKey' => $data['apiKey'],
                    'importFiles' => $data['importFiles'],
                    'version' => 0,
                    'timestamp' => $timestamp,
                ];

                if ($args['apiKey'] && !$this->apiKeyIsValid($args)) {
                    $this->messenger()->addError(
                        'Cannot import the Zotero library using the provided API key' // @translate
                    );
                } else {
                    $response = $this->sendApiRequest($args);
                    $body = json_decode($response->getBody(), true);
                    if ($response->isSuccess()) {
                        $import = $this->api()->create('zotero_imports', [
                            'o-module-zotero_import:version' => $response->getHeaders()->get('Last-Modified-Version')->getFieldValue(),
                            'o-module-zotero_import:name' => $body[0]['library']['name'],
                            'o-module-zotero_import:url' => $body[0]['library']['links']['alternate']['href'],
                        ])->getContent();
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
                        return $this->redirect()->toRoute('admin/zotero-import/default', ['action' => 'browse']);
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
            ->read('zotero_imports', $this->params('import-id'))->getContent();
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
                ->read('zotero_imports', $this->params('import-id'))->getContent();
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
     * @return bool
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
            return true;
        }
        if ('group' == $args['type']
            && (isset($key['access']['groups']['all']['library'])
                || isset($key['access']['groups'][$args['id']]['library']))
        ) {
            // It appears that the key has group library access.
            return true;
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
