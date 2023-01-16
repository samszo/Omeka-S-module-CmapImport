<?php declare(strict_types=1);
namespace CmapImport\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class CmapImportItemAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'cmap_import_items';
    }

    public function getRepresentationClass()
    {
        return \CmapImport\Api\Representation\CmapImportItemRepresentation::class;
    }

    public function getEntityClass()
    {
        return \CmapImport\Entity\CmapImportItem::class;
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        $data = $request->getContent();
        if ($data['o:item']['o:id']) {
            $item = $this->getAdapter('items')->findEntity($data['o:item']['o:id']);
            $entity->setItem($item);
        }
        if (isset($data['o-module-cmap_import:import']['o:id'])) {
            $import = $this->getAdapter('cmap_imports')->findEntity($data['o-module-cmap_import:import']['o:id']);
            $entity->setImport($import);
        }
        if ($data['o-module-cmap_import:action']) {
            $entity->setAction($data['o-module-cmap_import:action']);
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        if (isset($query['import_id'])) {
            $qb->andWhere($qb->expr()->eq(
                'omeka_root.import',
                $this->createNamedParameter($qb, $query['import_id']))
            );
        }
    }
}
