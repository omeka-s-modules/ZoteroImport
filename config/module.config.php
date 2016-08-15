<?php
return [
    'api_adapters' => [
        'invokables' => [
            'zotero_imports' => 'ZoteroImport\Api\Adapter\ZoteroImportAdapter',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/ZoteroImport/src/Entity',
        ],
    ],
    'controllers' => [
        'factories' => [
            'ZoteroImport\Controller\Index' => 'ZoteroImport\Service\IndexControllerFactory',
        ],
    ],
    'form_elements' => [
        'factories' => [
            'ZoteroImport\Form\ImportForm' => 'ZoteroImport\Service\ImportFormFactory',
        ],
    ],
    'view_manager' => [
        'template_path_stack'      => [
            OMEKA_PATH . '/modules/ZoteroImport/view',
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label'    => 'Zotero Import',
                'route'    => 'admin/zotero-import',
                'resource' => 'ZoteroImport\Controller\Index',
            ],
        ],
        'zoteroimport' => [
            [
                'label' => 'Import',
                'route'    => 'admin/zotero-import',
                'action' => 'import',
                'resource' => 'ZoteroImport\Controller\Index',
            ],
            [
                'label' => 'Browse Imports',
                'route'    => 'admin/zotero-import',
                'action' => 'browse',
                'resource' => 'ZoteroImport\Controller\Index',
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'zotero-import' => [
                        'type' => 'Segment',
                        'options' => [
                            'route' => '/zotero-import[/:action]',
                            'constraints' => [
                                'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                            ],
                            'defaults' => [
                                '__NAMESPACE__' => 'ZoteroImport\Controller',
                                'controller' => 'index',
                                'action' => 'import',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
