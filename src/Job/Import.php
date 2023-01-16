<?php declare(strict_types=1);
namespace CmapImport\Job;

use Omeka\Api\Exception\RuntimeException;
use Omeka\Job\AbstractJob;

class Import extends AbstractJob
{
    /**
     * Cmap API client
     *
     * @var Client
     */
    protected $client;

    /**
     * Cmap API URL
     *
     * @var Url
     */
    protected $url;

    /**
     * Vocabularies to cache.
     *
     * @var array
     */
    protected $vocabularies = [
        'dcterms' => 'http://purl.org/dc/terms/',
        'dctype' => 'http://purl.org/dc/dcmitype/',
        'bibo' => 'http://purl.org/ontology/bibo/',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'foaf' => 'http://xmlns.com/foaf/0.1/',
        'schema' => 'http://schema.org/',
        'oa' => 'http://www.w3.org/ns/oa#',
        //'dbpedia-owl'   => 'http://dbpedia.org/ontology/',
        'cito' => 'http://purl.org/spar/cito',
        'ma' => 'http://www.w3.org/ns/ma-ont#',
        'geom' => 'http://data.ign.fr/def/geometrie#',
        'plmk' => 'https://polemika.univ-paris8.fr/onto/polemika#',
    ];

    /**
     * Cache of selected Omeka resource classes
     *
     * @var array
     */
    protected $resourceClasses = [];

    /**
     * Cache of selected Omeka resource template
     *
     * @var array
     */
    protected $resourceTemplate = [];

    /**
     * Cache of selected Omeka properties
     *
     * @var array
     */
    protected $properties = [];

    /**
     * Priority map between Cmap item types and Omeka resource classes
     *
     * @var array
     */
    protected $itemTypeMap = [];

    /**
     * Priority map between Cmap item fields and Omeka properties
     *
     * @var array
     */
    protected $itemFieldMap = [];

    /**
     * Priority map between Cmap creator types and Omeka properties
     *
     * @var array
     */
    protected $creatorTypeMap = [];

    //Ajout samszo
    /**
     * proriété pour gérer les personnes
     *
     * @var array
     */
    protected $persons = [];
    /**
     * proriété pour gérer les tags
     *
     * @var array
     */
    protected $tags = [];
    /**
     * proriété pour gérer les annotations
     *
     * @var array
     */
    protected $annotations = [];
    /**
     * objet pour gérer les logs
     *
     * @var object
     */
    protected $logger;
    /**
     * objet pour gérer l'api
     *
     * @var object
     */
    protected $api;
    /**
     * proriété pour gérer l'identifiant de l'import
     *
     * @var array
     */
    protected $idImport;
    /**
     * proriété pour gérer l'identifiant de la collection
     *
     * @var int
     */
    protected $itemSet;

    /**
     * Perform the import.
     *
     * Accepts the following arguments:
     *
     * - itemSet:       The Cmap item set ID (int)
     *
     *
     * @see https://cmap.ihmc.us/documentation-support/
     */
    public function perform(): void
    {
        // Raise the memory limit to accommodate very large imports.
        ini_set('memory_limit', '500M');

        $this->api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $this->logger = $this->getServiceLocator()->get('Omeka\Logger');

        $this->itemSet = $this->api->read('item_sets', $this->getArg('itemSet'))->getContent();

        $this->cacheResourceClasses();
        $this->cacheResourceTemplate();
        $this->cacheProperties();

        $this->itemTypeMap = $this->prepareMapping('item_type_map');
        $this->itemFieldMap = $this->prepareMapping('item_field_map');
        $this->creatorTypeMap = $this->prepareMapping('creator_type_map');

        $apiVersion = $this->getArg('version', 0);
        $this->idImport = $this->getArg('import');
        $data = $this->getArg('data');

        //boucle tant qu'il y a des items
        $this->logger->info($this->getArg('file'));
        //création de la carte
        $oItem = $this->ajouteCarte($data);
        //création des entités
        $arrEntities = $this->ajouteEntities($data, $oItem);
        //création des liens
        $arrLinks = $this->ajouteLinks($data, $oItem, $arrEntities);
    }

    /**
     * Ajoute les items d'une requête
     *
     * @param array $data
     * @return oItem
     */
    protected function ajouteCarte($data)
    {
        //vérifie la présence de l'item pour ne pas écraser les données
        $param = [];
        $param['property'][0]['property'] = $this->properties['dcterms']['title']->id() . "";
        $param['property'][0]['type'] = 'eq';
        $param['property'][0]['text'] = $this->getArg('name');
        $param['resource_template_id'] = $this->resourceTemplate['Cartographie des expressions']->id() . "";

        $result = $this->api->search('items', $param)->getContent();
        //$this->logger->info("RECHERCHE ITEM = ".json_encode($result));
        //$this->logger->info("RECHERCHE COUNT = ".count($result));

        if (count($result)) {
            $oItem = $result[0]->getContent();
            throw new RuntimeException("La carte existe déjà : '" . $oItem->displayTitle() . "' (" . $oItem->id() . ").");
        } else {
            //creation de la carte générale
            $oItem = [];
            $oItem['o:item_set'] = [['o:id' => $this->itemSet->id()]];
            $oItem['o:resource_class'] = ['o:id' => $this->resourceClasses['plmk']['CarteExpression']->id()];
            $oItem['o:resource_templates'] = ['o:id' => $this->resourceTemplate['Cartographie des expressions']->id()];

            $d = $data['ivml:docinfo'];
            $d['width'] = $data['ivml:appdata']['map']['width'];
            $d['height'] = $data['ivml:appdata']['map']['height'];

            //récupération du style de la carte
            $d['style'] = json_encode($data['ivml:appdata']['map']['style-sheet-list']['style-sheet']);
            $oItem = $this->mapValues($d, $oItem);

            $response = $this->api->create('items', $oItem, [], ['continueOnError' => true]);
        }
        //$this->logger->info("UPDATE ITEM".$result[0]->id()." = ".json_encode($result[0]));
        $oItem = $response->getContent();
        //enregistre la progression du traitement
        $importItem = [
            'o:item' => ['o:id' => $oItem->id()],
            'o-module-cmap_import:import' => ['o:id' => $this->idImport],
            'o-module-cmap_import:action' => "Création carte",
        ];
        $this->api->create('cmap_import_items', $importItem, [], ['continueOnError' => true]);

        return $oItem;
    }

    /**
     * Ajoute les liens d'une carte
     *
     * @param array $data
     * @param oItem $oItemCarte
     * @param array $arrEntities
     * @return array
     */
    protected function ajouteLinks($data, $oItemCarte, $arrEntities)
    {
        $arrLinks = [];
        foreach ($data['ivml:links']['ivml:link'] as $k => $d) {
            if ($this->shouldStop()) {
                return;
            }
            //création du lien
            $oItem = [];
            $oItem['o:resource_class'] = ['o:id' => $this->resourceClasses['geom']['Line']->id()];
            $oItem['o:resource_templates'] = ['o:id' => $this->resourceTemplate['Relation sémantique']->id()];

            //création du titre
            $d['titre'] = $d['label'] ? $d['label'] . ' : ' . $d['id'] : $d['from'] . ' -> ' . $d['to'];

            //construction du style
            $d['style'] = '{';
            for ($i = 0; $i < count($d['ivml:appdata']['connection']); $i++) {
                $d['style'] .= $d['ivml:appdata']['connection-appearance'][0]['from-pos'] ? '"from-pos":"' . $d['ivml:appdata']['connection-appearance'][0]['from-pos'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['connection-appearance'][0]['to-pos'] ? '"to-pos":"' . $d['ivml:appdata']['connection-appearance'][0]['to-pos'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['connection-appearance'][0]['arrowhead'] ? '"arrowhead":"' . $d['ivml:appdata']['connection-appearance'][0]['arrowhead'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['concept-appearance'][0]['border-color'] ? '"border-color":"' . $d['ivml:appdata']['concept-appearance'][0]['border-color'] . '",' : "";
            }
            if ($i == 0) {
                $d['style'] .= $d['ivml:appdata']['connection-appearance']['from-pos'] ? '"from-pos":"' . $d['ivml:appdata']['connection-appearance']['from-pos'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['connection-appearance']['to-pos'] ? '"to-pos":"' . $d['ivml:appdata']['connection-appearance']['to-pos'] . '",' : '",';
                $d['style'] .= $d['ivml:appdata']['connection-appearance']['arrowhead'] ? '"arrowhead":"' . $d['ivml:appdata']['connection-appearance']['arrowhead'] . '",' : '';
                $d['style'] .= $d['ivml:appdata']['concept-appearance']['border-color'] ? '"border-color":"' . $d['ivml:appdata']['concept-appearance']['border-color'] . '",' : "";
            }
            $d['style'] .= '"color":"' . $d['color'] . '",';
            $d['style'] .= '"lineStyle":"' . $d['lineStyle'] . '"';
            $d['style'] .= '}';

            //ajoute les références au from et au to
            $d['from'] = $arrEntities[$d['from']];
            $d['to'] = $arrEntities[$d['to']];

            $oItem = $this->mapValues($d, $oItem);
            $response = $this->api->create('items', $oItem, [], ['continueOnError' => true]);
            $oItem = $response->getContent();

            //création le concept
            if ($d['label']) {
                $this->ajouteTag($d['label'], $oItem);
            }

            //ajoute la relation à la carte
            $param = [];
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["geom"]["geometry"]->id();
            $valueObject['value_resource_id'] = $oItem->id();
            $valueObject['type'] = 'resource';
            $param[$this->properties["geom"]["geometry"]->term()][] = $valueObject;
            $this->api->update('items', $oItemCarte->id(), $param, [], ['isPartial' => true, 'continueOnError' => true, 'collectionAction' => 'append']);

            //enregistre la progression du traitement
            $importItem = [
                'o:item' => ['o:id' => $oItem->id()],
                'o-module-cmap_import:import' => ['o:id' => $this->idImport],
                'o-module-cmap_import:action' => 'ajout link',
            ];
            $this->api->create('cmap_import_items', $importItem, [], ['continueOnError' => true]);

            $arrLinks[] = $oItem;
        }
    }

    /**
     * Ajoute les entities d'une carte
     *
     * @param array $data
     * @param oItem $oItemCarte
     * @return array
     */
    protected function ajouteEntities($data, $oItemCarte)
    {
        $arrEntities = [];
        foreach ($data['ivml:entities']['ivml:entity'] as $k => $d) {
            if ($this->shouldStop()) {
                return;
            }
            //création de l'entity
            $oItem = [];
            $oItem['o:resource_class'] = ['o:id' => $this->resourceClasses['geom']['Envelope']->id()];
            $oItem['o:resource_templates'] = ['o:id' => $this->resourceTemplate['Espace sémantique']->id()];
            $d['titre'] = $d['label'] . ' : ' . $d['id'];
            //construction du style
            if ($d['ivml:appdata']['concept-appearance']) {
                $d['width'] = $d['ivml:appdata']['concept-appearance']['width'];
                $d['height'] = $d['ivml:appdata']['concept-appearance']['height'];
                $d['type'] = 'concept';
            }
            if ($d['ivml:appdata']['linking-phrase-appearance']) {
                $d['width'] = $d['ivml:appdata']['linking-phrase-appearance']['width'];
                $d['height'] = $d['ivml:appdata']['linking-phrase-appearance']['height'];
                $d['type'] = 'linking-phrase';
            }

            $d['style'] = '{';
            $d['style'] .= $d['ivml:appdata']['concept-appearance']['border-color'] ? '"border-color":"' . $d['ivml:appdata']['concept-appearance']['border-color'] . '",' : "";
            $d['style'] .= '"color":"' . $d['color'] . '","fgTextColor":"' . $d['fgTextColor'] . '"';
            $d['style'] .= '}';

            $oItem = $this->mapValues($d, $oItem);
            $response = $this->api->create('items', $oItem, [], ['continueOnError' => true]);
            $oItem = $response->getContent();
            //ajoute le concept
            $this->ajouteTag($d['label'], $oItem);
            //ajoute la relation à la carte
            $param = [];
            $valueObject = [];
            $valueObject['property_id'] = $this->properties["geom"]["geometry"]->id();
            $valueObject['value_resource_id'] = $oItem->id();
            $valueObject['type'] = 'resource';
            $param[$this->properties["geom"]["geometry"]->term()][] = $valueObject;
            $this->api->update('items', $oItemCarte->id(), $param, [], ['isPartial' => true, 'continueOnError' => true, 'collectionAction' => 'append']);

            //enregistre la progression du traitement
            $importItem = [
                'o:item' => ['o:id' => $oItem->id()],
                'o-module-cmap_import:import' => ['o:id' => $this->idImport],
                'o-module-cmap_import:action' => 'ajout entity',
            ];
            $this->api->create('cmap_import_items', $importItem, [], ['continueOnError' => true]);

            $arrEntities[$d['id']] = $oItem->id();
        }

        return $arrEntities;
    }

    /**
     * Ajoute un tag au format skos
     *
     * @param array $tag
     * @param array $oItem
     * @return array
     */
    protected function ajouteTag($tag, $oItem)
    {
        if (isset($this->tags[$tag])) {
            $oTag = $this->tags[$tag];
        } else {
            //vérifie la présence de l'item pour gérer la création
            $param = [];
            $param['property'][0]['property'] = $this->properties["skos"]["prefLabel"]->id() . "";
            $param['property'][0]['type'] = 'eq';
            $param['property'][0]['text'] = $tag;
            //$this->logger->info("RECHERCHE PARAM = ".json_encode($param));
            $result = $this->api->search('items', $param)->getContent();
            //$this->logger->info("RECHERCHE ITEM = ".json_encode($result));
            //$this->logger->info("RECHERCHE COUNT = ".count($result));
            if (count($result)) {
                $oTag = $result[0];
            //$this->logger->info("ID TAG EXISTE".$result[0]->id()." = ".json_encode($result[0]));
            } else {
                $param = [];
                $param['o:resource_class'] = ['o:id' => $this->resourceClasses['skos']['Concept']->id()];
                $valueObject = [];
                $valueObject['property_id'] = $this->properties["dcterms"]["title"]->id();
                $valueObject['@value'] = $tag;
                $valueObject['type'] = 'literal';
                $param[$this->properties["dcterms"]["title"]->term()][] = $valueObject;
                $valueObject = [];
                $valueObject['property_id'] = $this->properties["skos"]["prefLabel"]->id();
                $valueObject['@value'] = $tag;
                $valueObject['type'] = 'literal';
                $param[$this->properties["skos"]["prefLabel"]->term()][] = $valueObject;
                //création du tag
                $result = $this->api->create('items', $param, [], ['continueOnError' => true])->getContent();
                $oTag = $result;
                $importItem = [
                    'o:item' => ['o:id' => $oTag->id()],
                    'o-module-cmap_import:import' => ['o:id' => $this->idImport],
                    'o-module-cmap_import:diigo_key' => $tag,
                    'o-module-cmap_import:action' => 'createTag',
                ];
                $this->api->create('cmap_import_items', $importItem, [], ['continueOnError' => true]);
                //$this->logger->info("ID TAG CREATE ".$oIdTag." = ".json_encode($result));
            }
            $this->tags[$tag] = $oTag;
        }
        //ajoute la relation à l'item
        $param = [];
        $valueObject = [];
        $valueObject['property_id'] = $this->properties["skos"]["semanticRelation"]->id();
        $valueObject['value_resource_id'] = $oTag->id();
        $valueObject['type'] = 'resource';
        $param[$this->properties["skos"]["semanticRelation"]->term()][] = $valueObject;
        $this->api->update('items', $oItem->id(), $param, [], ['isPartial' => true, 'continueOnError' => true, 'collectionAction' => 'append']);

        return $oTag;
    }

    /**
     * Cache selected resource classes.
     */
    public function cacheResourceClasses(): void
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        foreach ($this->vocabularies as $prefix => $namespaceUri) {
            $classes = $api->search('resource_classes', [
                'vocabulary_namespace_uri' => $namespaceUri,
            ])->getContent();
            foreach ($classes as $class) {
                $this->resourceClasses[$prefix][$class->localName()] = $class;
            }
        }
    }

    /**
     * Cache selected properties.
     */
    public function cacheProperties(): void
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        foreach ($this->vocabularies as $prefix => $namespaceUri) {
            $properties = $api->search('properties', [
                'vocabulary_namespace_uri' => $namespaceUri,
            ])->getContent();
            foreach ($properties as $property) {
                $this->properties[$prefix][$property->localName()] = $property;
            }
        }
    }

    /**
     * Cache selected resource template.
     */
    public function cacheResourceTemplate(): void
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $arrRT = ["Annotation", "Cartographie des expressions","Relation sémantique","Espace sémantique"];
        foreach ($arrRT as $label) {
            $rt = $api->search('resource_templates', [
                'label' => $label,
            ])->getContent();

            $this->resourceTemplate[$label] = $rt[0];
        }
    }

    /**
     * Convert a mapping with terms into a mapping with prefix and local name.
     *
     * @param string $mapping
     * @return array
     */
    protected function prepareMapping($mapping)
    {
        $map = require dirname(__DIR__, 2) . '/data/mapping/' . $mapping . '.php';
        foreach ($map as &$term) {
            if ($term) {
                $value = explode(':', $term);
                $term = [$value[0] => $value[1]];
            } else {
                $term = [];
            }
        }
        return $map;
    }

    /**
     * Map Cmap item data to Omeka item values.
     *
     * @param array $CmapItem The Cmap item data
     * @param array $omekaItem The Omeka item data
     * @return array
     */
    public function mapValues(array $cmapItem, array $omekaItem)
    {
        foreach ($cmapItem as $key => $value) {
            if (!$value) {
                continue;
            }
            if (!isset($this->itemFieldMap[$key])) {
                continue;
            }
            foreach ($this->itemFieldMap[$key] as $prefix => $localName) {
                if (isset($this->properties[$prefix][$localName])) {
                    $property = $this->properties[$prefix][$localName];
                    $valueObject = [];
                    $valueObject['property_id'] = $property->id();
                    if ('bibo' == $prefix && 'uri' == $localName) {
                        $valueObject['@id'] = $value;
                        $valueObject['type'] = 'uri';
                    } elseif ('from' == $key || 'to' == $key) {
                        $valueObject['value_resource_id'] = $value;
                        $valueObject['type'] = 'resource';
                    } else {
                        $valueObject['@value'] = $value;
                        $valueObject['type'] = 'literal';
                    }
                    $omekaItem[$property->term()][] = $valueObject;
                    continue 2;
                }
            }
        }
        return $omekaItem;
    }
}
