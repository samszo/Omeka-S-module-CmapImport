<?php declare(strict_types=1);
namespace CmapImport\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Job;

/**
 * @Entity
 */
class CmapImport extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @OneToOne(
     *     targetEntity="Omeka\Entity\Job"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="CASCADE"
     * )
     */

    protected $job;
    /**
     * @OneToOne(
     *     targetEntity="Omeka\Entity\Job"
     * )
     * @JoinColumn(
     *     nullable=true,
     *     onDelete="CASCADE"
     * )
     */
    protected $undoJob;

    /**
     * @Column
     */
    protected $name;

    /**
     * @Column
     */
    protected $url;

    /**
     * @Column(type="integer")
     */
    protected $version;

    /**
     * @OneToMany(
     *     targetEntity="CmapImportItem",
     *     mappedBy="import",
     *     fetch="EXTRA_LAZY"
     * )
     */
    protected $importItems;

    public function __construct()
    {
        $this->importItems = new ArrayCollection;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setJob(Job $job): void
    {
        $this->job = $job;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setUndoJob(Job $undoJob): void
    {
        $this->undoJob = $undoJob;
    }

    public function getUndoJob()
    {
        return $this->undoJob;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setUrl($url): void
    {
        $this->url = $url;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setVersion($version): void
    {
        $this->version = $version;
    }

    public function getVersion()
    {
        return $this->version;
    }

    public function getImportItems()
    {
        return $this->importItems;
    }
}
