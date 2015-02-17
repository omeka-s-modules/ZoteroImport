<?php
namespace ZoteroImport\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZoteroImport\Form\ImportForm;
use ZoteroImport\Job\Uri;

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

                if ($this->isValidRequest($args)) {
                    $dispatcher = $this->getServiceLocator()->get('Omeka\JobDispatcher');
                    $dispatcher->dispatch('ZoteroImport\Job\Import', $args);
                    // Clear the form.
                    $form = new ImportForm($this->getServiceLocator());
                    $this->messenger()->addSuccess('Importing from Zotero');
                } else {
                    $this->messenger()->addError('The requested Zotero library or collection is invalid.');
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
     * Validate a Zotero API request.
     *
     * @param array $args
     * @return bool
     */
    public function isValidRequest(array $args)
    {
        $uri = new Uri($args['type'], $args['id']);
        $uri->setCollectionKey($args['collectionKey']);
        $uri->setLimit(1);

        $headers = array();
        if ($args['apiKey']) {
            $headers['Authorization'] = sprintf('Bearer %s', $args['apiKey']);
        }
        $client = $this->getServiceLocator()->get('Omeka\HttpClient');
        $client->setHeaders($headers);

        $response = $client->setUri($uri->getUri())->send();
        return $response->isSuccess();
    }
}
