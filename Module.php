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

    public function install(ServiceLocatorInterface $services)
    {
        $conn = $services->get('Omeka\Connection');
        $conn->exec('
SET FOREIGN_KEY_CHECKS=0;
CREATE TABLE zotero_import (id INT AUTO_INCREMENT NOT NULL, job_id INT DEFAULT NULL, name VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, version INT NOT NULL, UNIQUE INDEX UNIQ_82A3EEB8BE04EA9 (job_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
CREATE TABLE zotero_import_item (id INT AUTO_INCREMENT NOT NULL, import_id INT NOT NULL, item_id INT NOT NULL, INDEX IDX_86A2392BB6A263D9 (import_id), INDEX IDX_86A2392B126F525E (item_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB;
ALTER TABLE zotero_import ADD CONSTRAINT FK_82A3EEB8BE04EA9 FOREIGN KEY (job_id) REFERENCES job (id) ON DELETE SET NULL;
ALTER TABLE zotero_import_item ADD CONSTRAINT FK_86A2392BB6A263D9 FOREIGN KEY (import_id) REFERENCES zotero_import (id) ON DELETE CASCADE;
ALTER TABLE zotero_import_item ADD CONSTRAINT FK_86A2392B126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE;
SET FOREIGN_KEY_CHECKS=1;
');
    }

    public function uninstall(ServiceLocatorInterface $services)
    {
        $conn = $services->get('Omeka\Connection');
        $conn->exec('
SET FOREIGN_KEY_CHECKS=0;
DROP TABLE zotero_import;
DROP TABLE zotero_import_item;
SET FOREIGN_KEY_CHECKS=1;
');
    }
}
