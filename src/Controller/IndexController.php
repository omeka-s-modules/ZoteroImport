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
                );

                // Validate a Zotero API request.
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
                $response = $client->setUri($url)->send();

                if ($response->isSuccess()) {
                    $dispatcher = $this->getServiceLocator()->get('Omeka\JobDispatcher');
                    $dispatcher->dispatch('ZoteroImport\Job\Import', $args);
                    $form = new ImportForm($this->getServiceLocator()); // Clear the form.
                    $this->messenger()->addSuccess('Importing from Zotero');
                } else {
                    $this->messenger()->addError(sprintf(
                        'Error when requesting Zotero library: %s', $response->getReasonPhrase()
                    ));
                }
            } else {
                $this->messenger()->addError('There was an error during validation');
            }
        }

        $view = new ViewModel;
        $view->setVariable('form', $form);
        return $view;
    }
}
