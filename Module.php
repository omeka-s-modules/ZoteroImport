<?php
namespace ZoteroImport;

use Omeka\Module\AbstractModule;
use Zend\ServiceManager\ServiceLocatorInterface;

class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec('CREATE TABLE zotero_import (id INT AUTO_INCREMENT NOT NULL, job_id INT DEFAULT NULL, version INT NOT NULL, UNIQUE INDEX UNIQ_82A3EEB8BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB;');
        $connection->exec('ALTER TABLE zotero_import ADD CONSTRAINT FK_82A3EEB8BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE SET NULL;');
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $connection = $serviceLocator->get('Omeka\Connection');
        $connection->exec('ALTER TABLE zotero_import DROP FOREIGN KEY FK_82A3EEB8BE04EA9;');
        $connection->exec('DROP TABLE zotero_import');
    }
}

