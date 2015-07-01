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
    ),
    'router' => array(
        'routes' => array(
            'admin' => array(
                'child_routes' => array(
                    'zotero-import' => array(
                        'type' => 'Literal',
                        'options' => array(
                            'route' => '/zotero-import',
                            'defaults' => array(
                                '__NAMESPACE__' => 'ZoteroImport\Controller',
                                'controller'    => 'Index',
                                'action'        => 'index',
                            ),
                        ),
                    ),
                ),
            ),
        ),
    ),
);
