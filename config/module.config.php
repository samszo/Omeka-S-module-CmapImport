<?php
namespace CmapImport;

return [
    'api_adapters' => [
        'invokables' => [
            'cmap_imports' => Api\Adapter\CmapImportAdapter::class,
            'cmap_import_items' => Api\Adapter\CmapImportItemAdapter::class,
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
            'CmapImport\Controller\Index' => Service\IndexControllerFactory::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack'      => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            [
                'label'      => 'Cmap Import', // @translate
                'route'      => 'admin/Cmap-import',
                'resource'   => 'CmapImport\Controller\Index',
                'pages'      => [
                    [
                        'label' => 'Import', // @translate
                        'route'    => 'admin/Cmap-import',
                        'action' => 'import',
                        'resource' => 'CmapImport\Controller\Index',
                    ],
                    [
                        'label' => 'Past Imports', // @translate
                        'route'    => 'admin/Cmap-import/default',
                        'action' => 'browse',
                        'resource' => 'CmapImport\Controller\Index',
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'Cmap-import' => [
                        'type' => 'Literal',
                        'options' => [
                            'route' => '/Cmap-import',
                            'defaults' => [
                                '__NAMESPACE__' => 'CmapImport\Controller',
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
