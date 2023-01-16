<?php declare(strict_types=1);
namespace CmapImport\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class CmapImportItemRepresentation extends AbstractEntityRepresentation
{
    public function getJsonLdType()
    {
        return 'o-module-cmap_import:CmapImportItem';
    }

    public function getJsonLd()
    {
        return [
            'o-module-cmap_import:import' => $this->import()->getReference(),
            'o:item' => $this->job()->getReference(),
            'o-module-cmap_import:action' => $this->resource->getAction(),
        ];
    }

    public function import()
    {
        return $this->getAdapter('cmap_imports')
            ->getRepresentation($this->resource->getImport());
    }

    public function item()
    {
        return $this->getAdapter('items')
            ->getRepresentation($this->resource->getItem());
    }

    public function action()
    {
        return $this->getAdapter('action')
            ->getRepresentation($this->resource->getAction());
    }
}
