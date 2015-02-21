<?php
namespace ZoteroImport\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZoteroImport\Form\ImportForm;
use ZoteroImport\Zotero\Url;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        $form = new ImportForm($this->getServiceLocator());

        if ($this->getRequest()->isPost()) {
            $data = $this->params()->fromPost();
            $form->setData($data);

            if ($form->isValid()) {
                $data = $form->getData();
                $args = array(
                    'itemSet' => $data['itemSet'],
                    'type' => $data['type'],
                    'id' => $data['id'],
                    'collectionKey' => $data['collectionKey'],
                    'apiKey' => $data['apiKey'],
                    'importAttachments' => $data['importAttachments'],
                );

                if ($args['apiKey'] && !$this->apiKeyIsValid($args)) {
                    $this->messenger()->addError(
                        'Cannot import the Zotero library using the provided API key'
                    );
                } else {
                    $response = $this->sendApiRequest($args);
                    if (!$response->isSuccess()) {
                        $this->messenger()->addError(sprintf(
                            'Error when requesting Zotero library: %s', $response->getReasonPhrase()
                        ));
                    } else {
                        $dispatcher = $this->getServiceLocator()->get('Omeka\JobDispatcher');
                        $dispatcher->dispatch('ZoteroImport\Job\Import', $args);
                        $form = new ImportForm($this->getServiceLocator()); // Clear the form.
                        $this->messenger()->addSuccess('Importing from Zotero');
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

    /**
     * Validate a Zotero API key.
     *
     * @param array $args
     * @return bool
     */
    public function apiKeyIsValid(array $args)
    {
        $url = Url::key($args['apiKey']);
        $client = $this->getServiceLocator()->get('Omeka\HttpClient');
        $response = $client->setUri($url)->send();
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
        $params = array('limit' => 1);
        $url = new Url($args['type'], $args['id']);
        if ($collectionKey = $args['collectionKey']) {
            $url = $url->collectionItemsTop($collectionKey, $params);
        } else {
            $url = $url->itemsTop($params);
        }
        $headers = array();
        if ($args['apiKey']) {
            $headers['Authorization'] = sprintf('Bearer %s', $args['apiKey']);
        }
        $client = $this->getServiceLocator()->get('Omeka\HttpClient');
        $client->setHeaders($headers);
        return $client->setUri($url)->send();
    }
}
