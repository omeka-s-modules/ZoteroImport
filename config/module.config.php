<?php
return [
    'api_adapters' => [
        'invokables' => [
            'zotero_imports' => 'ZoteroImport\Api\Adapter\ZoteroImportAdapter',
            'zotero_import_items' => 'ZoteroImport\Api\Adapter\ZoteroImportItemAdapter',
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            OMEKA_PATH . '/modules/ZoteroImport/src/Entity',
        ],
        'proxy_paths' => [
            OMEKA_PATH . '/modules/ZoteroImport/data/doctrine-proxies',
        ],
    ],
    'controllers' => [
        'factories' => [
            'ZoteroImport\Controller\Index' => 'ZoteroImport\Service\IndexControllerFactory',
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
                'label'      => 'Zotero Import', // @translate
                'route'      => 'admin/zotero-import',
                'resource'   => 'ZoteroImport\Controller\Index',
                'pages'      => [
                    [
                        'label' => 'Import', // @translate
                        'route'    => 'admin/zotero-import',
                        'action' => 'import',
                        'resource' => 'ZoteroImport\Controller\Index',
                    ],
                    [
                        'label' => 'Past Imports', // @translate
                        'route'    => 'admin/zotero-import/default',
                        'action' => 'browse',
                        'resource' => 'ZoteroImport\Controller\Index',
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'zotero-import' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/zotero-import',
                            'defaults' => [
                                '__NAMESPACE__' => 'ZoteroImport\Controller',
                                'controller' => 'index',
                                'action' => 'import',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'id' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:import-id[/:action]',
                                    'constraints' => [
                                        'import-id' => '\d+',
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                            'default' => [
                                'type' => 'Segment',
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
