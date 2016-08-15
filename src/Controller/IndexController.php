<?php
namespace ZoteroImport\Controller;

use DateTime;
use DateTimeZone;
use Omeka\Job\Dispatcher;
use Zend\Http\Client;
use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZoteroImport\Form\ImportForm;
use ZoteroImport\Zotero\Url;

class IndexController extends AbstractActionController
{
    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var Client
     */
    protected $client;

    public function __construct(Dispatcher $dispatcher, Client $client)
    {
        $this->dispatcher = $dispatcher;
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
                    'importFiles'   => $data['importFiles'],
                    'version'       => 0,
                    'timestamp'     => $timestamp,
                ];

                if ($args['apiKey'] && !$this->apiKeyIsValid($args)) {
                    $this->messenger()->addError(
                        'Cannot import the Zotero library using the provided API key'
                    );
                } else {
                    $response = $this->sendApiRequest($args);
                    $body = json_decode($response->getBody(), true);
                    if (!$response->isSuccess()) {
                        $this->messenger()->addError(sprintf(
                            'Error when requesting Zotero library: %s', $response->getReasonPhrase()
                        ));
                    } else {
                        $job = $this->dispatcher->dispatch('ZoteroImport\Job\Import', $args);

                        $this->api()->create('zotero_imports', [
                            'o:job' => ['o:id' => $job->getId()],
                            'version' => $response->getHeaders()->get('Last-Modified-Version')->getFieldValue(),
                            'name' => $body[0]['library']['name'],
                            'url' => $body[0]['library']['links']['alternate']['href'],
                        ]);

                        $this->messenger()->addSuccess('Importing from Zotero');
                        return $this->redirect()->refresh();
                    }
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
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

    /**
     * Validate a Zotero API key.
     *
     * @param array $args
     * @return bool
     */
    public function apiKeyIsValid(array $args)
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
     * @retuen Response
     */
    public function sendApiRequest(array $args)
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
