<?php declare(strict_types=1);
namespace CmapImport\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Item;

/**
 * @Entity
 */
class CmapImportItem extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(
     *     targetEntity="CmapImport",
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $import;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Item",
     *     cascade={"detach"}
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $item;

    /**
     * @Column
     */
    protected $action;

    public function getId()
    {
        return $this->id;
    }

    public function setImport(CmapImport $import): void
    {
        $this->import = $import;
    }

    public function getImport()
    {
        return $this->import;
    }

    public function setItem(Item $item): void
    {
        $this->item = $item;
    }

    public function getItem()
    {
        return $this->item;
    }

    public function setAction($action): void
    {
        $this->action = $action;
    }

    public function getAction()
    {
        return $this->action;
    }
}
