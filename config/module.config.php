<?php
namespace ZoteroImport;

return [
    'api_adapters' => [
        'invokables' => [
            'zotero_imports' => Api\Adapter\ZoteroImportAdapter::class,
            'zotero_import_items' => Api\Adapter\ZoteroImportItemAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'controllers' => [
        'factories' => [
            'ZoteroImport\Controller\Index' => Service\IndexControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label' => 'Zotero Import', // @translate
                'route' => 'admin/zotero-import',
                'resource' => 'ZoteroImport\Controller\Index',
                'pages' => [
                    [
                        'label' => 'Import', // @translate
                        'route' => 'admin/zotero-import',
                        'action' => 'import',
                        'resource' => 'ZoteroImport\Controller\Index',
                    ],
                    [
                        'label' => 'Past Imports', // @translate
                        'route' => 'admin/zotero-import/default',
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
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
];
