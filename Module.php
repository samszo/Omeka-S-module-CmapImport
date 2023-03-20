<?php declare(strict_types=1);
namespace CmapImport;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    protected function preInstall(): void
    {
        $modules = [
            'Generic' => '3.4.43',
            'Annotate' => '3.1.2',
        ];
        $services = $this->getServiceLocator();
        $moduleManager = $services->get('Omeka\ModuleManager');
        $translator = $services->get('MvcTranslator');
        foreach ($modules as $moduleName => $minVersion) {
            $module = $moduleManager->getModule($moduleName);
            if ($module && version_compare($module->getIni('version'), $minVersion, '<')) {
                $message = new \Omeka\Stdlib\Message(
                    $translator->translate('This module requires the module "%s", version %s or above.'), // @translate
                    $moduleName, $minVersion
                );
                throw new \Omeka\Module\Exception\ModuleCannotInstallException($message);
            }
        }
    }

    protected function postUninstall(): void
    {
        $services = $this->getServiceLocator();

        if (!class_exists(\Generic\InstallResources::class)) {
            require_once file_exists(dirname(__DIR__) . '/Generic/InstallResources.php')
                ? dirname(__DIR__) . '/Generic/InstallResources.php'
                : __DIR__ . '/src/Generic/InstallResources.php';
        }

        $installResources = new \Generic\InstallResources($services);
        $installResources = $installResources();

        if (!empty($_POST['remove-vocabulary'])) {
            $prefix = 'geom';
            $installResources->removeVocabulary($prefix);
            $prefix = 'plmk';
            $installResources->removeVocabulary($prefix);
            $prefix = 'skos';
            $installResources->removeVocabulary($prefix);
            $prefix = 'schema';
            $installResources->removeVocabulary($prefix);
            $prefix = 'oa';
            $installResources->removeVocabulary($prefix);
            $prefix = 'ma';
            $installResources->removeVocabulary($prefix);
            $prefix = 'cito';
            $installResources->removeVocabulary($prefix);
        }

        if (!empty($_POST['remove-template'])) {
            $resourceTemplate = 'Cartographie des expressions';
            $installResources->removeResourceTemplate($resourceTemplate);
            $resourceTemplate = 'Relation sémantique';
            $installResources->removeResourceTemplate($resourceTemplate);
            $resourceTemplate = 'Concept';
            $installResources->removeResourceTemplate($resourceTemplate);
            $resourceTemplate = 'Espace sémantique';
            $installResources->removeResourceTemplate($resourceTemplate);
        }

        //parent::uninstall($services);
    }

    public function warnUninstall(Event $event): void
    {
        $view = $event->getTarget();
        $module = $view->vars()->module;
        if ($module->getId() != __NAMESPACE__) {
            return;
        }

        $serviceLocator = $this->getServiceLocator();
        $t = $serviceLocator->get('MvcTranslator');

        $vocabularyLabels = 'IGN geometry" / "Polemika" / "SKOS" / "Ontology for Media Resources" / "schema';
        $resourceTemplates = 'Cartographie des expressions" / "Relation sémantique" / "Concept" / "Espace sémantique';

        $html = '<p>';
        $html .= '<strong>';
        $html .= $t->translate('WARNING'); // @translate
        $html .= '</strong>' . ': ';
        $html .= '</p>';

        $html .= '<p>';
        $html .= $t->translate('All the annotations will be removed.'); // @translate
        $html .= '</p>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('If checked, the values of the vocabularies "%s" will be removed too. The class of the resources that use a class of these vocabularies will be reset.'), // @translate
            $vocabularyLabels
        );
        $html .= '</p>';
        $html .= '<label><input name="remove-vocabulary" type="checkbox" form="confirmform">';
        $html .= sprintf($t->translate('Remove the vocabularies "%s"'), $vocabularyLabels); // @translate
        $html .= '</label>';

        $html .= '<p>';
        $html .= sprintf(
            $t->translate('If checked, the resource templates "%s" will be removed too. The resource template of the resources that use it will be reset.'), // @translate
            $resourceTemplates
        );
        $html .= '</p>';
        $html .= '<label><input name="remove-template" type="checkbox" form="confirmform">';
        $html .= sprintf($t->translate('Remove the resource templates "%s"'), $resourceTemplates); // @translate
        $html .= '</label>';

        echo $html;
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\ItemAdapter::class,
            'api.search.query',
            function (Event $event): void {
                $query = $event->getParam('request')->getContent();
                if (isset($query['cmap_import_id'])) {
                    $qb = $event->getParam('queryBuilder');
                    $adapter = $event->getTarget();
                    $importItemAlias = $adapter->createAlias();
                    $qb->innerJoin(
                        \CmapImport\Entity\CmapoImportItem::class, $importItemAlias,
                        'WITH', "$importItemAlias.item = omeka_root.id"
                    )->andWhere($qb->expr()->eq(
                        "$importItemAlias.import",
                        $adapter->createNamedParameter($qb, $query['cmap_import_id'])
                    ));
                }
            }
        );

        // Display a warn before uninstalling.
        $sharedEventManager->attach(
            'Omeka\Controller\Admin\Module',
            'view.details',
            [$this, 'warnUninstall']
        );
    }
}
