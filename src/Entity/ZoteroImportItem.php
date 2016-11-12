<?php
namespace ZoteroImport\Entity;

use Omeka\Entity\AbstractEntity;
use Omeka\Entity\Item;
use Omeka\Entity\Job;

/**
 * @Entity
 */
class ZoteroImportItem extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Job",
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $job;

    /**
     * @ManyToOne(
     *     targetEntity="Omeka\Entity\Item",
     * )
     * @JoinColumn(
     *     nullable=false,
     *     onDelete="CASCADE"
     * )
     */
    protected $item;

    public function getId()
    {
        return $this->id;
    }

    public function setJob(Job $job)
    {
        $this->job = $job;
    }

    public function getJob()
    {
        return $this->job;
    }

    public function setItem(Item $item)
    {
        $this->item = $item;
    }

    public function getItem()
    {
        return $this->item;
    }
}
