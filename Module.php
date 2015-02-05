<?php
namespace ZoteroImport;

use Omeka\Module\AbstractModule;
use Omeka\Event\FilterEvent;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\Controller\AbstractController;
use Zend\View\Model\ViewModel;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }
}

