<?php
namespace ZoteroImport\Controller;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class IndexController extends AbstractActionController
{
    public function indexAction()
    {
        //~ $dispatcher = $this->getServiceLocator()->get('Omeka\JobDispatcher');
        //~ $dispatcher->dispatch('ZoteroImport\Job\Import', array(
            //~ 'type' => 'user',
            //~ 'id' => '',
            //~ 'collectionKey' => '',
        //~ ));
    }
}
