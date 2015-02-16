<?php
namespace ZoteroImport\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;
use ZoteroImport\Form\ImportForm;
use ZoteroImport\Http\ZoteroClient;

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
                );
                // Validate the Zotero API request.
                $client = new ZoteroClient($this->getServiceLocator());
                $uri = $client->getFirstUri($args['type'], $args['id'],
                    $args['collectionKey'], 1);
                $response = $client->setUri($uri)->send();
                if ($response->isSuccess()) {
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
}
