<?php
return array(
    'api_adapters' => array(
        'invokables' => array(
            'zotero_imports' => 'ZoteroImport\Api\Adapter\ZoteroImportAdapter',
        ),
    ),
    'entity_manager' => array(
        'mapping_classes_paths' => array(
            OMEKA_PATH . '/modules/ZoteroImport/src/Entity',
        ),
    ),
    'controllers' => array(
        'invokables' => array(
            'ZoteroImport\Controller\Index' => 'ZoteroImport\Controller\IndexController',
        ),
    ),
    'view_manager' => array(
        'template_path_stack'      => array(
            OMEKA_PATH . '/modules/ZoteroImport/view',
        ),
    ),
    'navigation' => array(
        'admin' => array(
            array(
                'label'    => 'Zotero Import',
                'route'    => 'admin/zotero-import',
                'resource' => 'ZoteroImport\Controller\Index',
            ),
        ),
        'zoteroimport' => array(
            array(
                'label' => 'Import',
                'route'    => 'admin/zotero-import',
                'action' => 'import',
                'resource' => 'ZoteroImport\Controller\Index',
            ),
            array(
                'label' => 'Browse Imports',
                'route'    => 'admin/zotero-import',
                'action' => 'browse',
                'resource' => 'ZoteroImport\Controller\Index',
            ),
        ),
    ),
    'router' => array(
        'routes' => array(
            'admin' => array(
                'child_routes' => array(
                    'zotero-import' => array(
                        'type' => 'Segment',
                        'options' => array(
                            'route' => '/zotero-import[/:action]',
                            'constraints' => array(
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ),
                            'defaults' => array(
                                '__NAMESPACE__' => 'ZoteroImport\Controller',
                                'controller' => 'index',
                                'action' => 'import',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
);
