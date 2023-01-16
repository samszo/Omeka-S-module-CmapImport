<?php declare(strict_types=1);
namespace CmapImport\Api\Adapter;

use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class CmapImportAdapter extends AbstractEntityAdapter
{
    public function getResourceName()
    {
        return 'cmap_imports';
    }

    public function getRepresentationClass()
    {
        return \CmapImport\Api\Representation\CmapImportRepresentation::class;
    }

    public function getEntityClass()
    {
        return \CmapImport\Entity\CmapImport::class;
    }

    public function hydrate(Request $request, EntityInterface $entity,
        ErrorStore $errorStore
    ): void {
        $data = $request->getContent();

        if (isset($data['o:job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o:job']['o:id']);
            $entity->setJob($job);
        }
        if (isset($data['o-module-cmap_import:undo_job']['o:id'])) {
            $job = $this->getAdapter('jobs')->findEntity($data['o-module-cmap_import:undo_job']['o:id']);
            $entity->setUndoJob($job);
        }

        if (isset($data['o-module-cmap_import:version'])) {
            $entity->setVersion($data['o-module-cmap_import:version']);
        }
        if (isset($data['o-module-cmap_import:name'])) {
            $entity->setName($data['o-module-cmap_import:name']);
        }
        if (isset($data['o-module-cmap_import:url'])) {
            $entity->setUrl($data['o-module-cmap_import:url']);
        }
    }
}
