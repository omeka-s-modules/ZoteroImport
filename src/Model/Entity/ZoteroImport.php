<?php
namespace ZoteroImport\Model\Entity;

use Omeka\Model\Entity\AbstractEntity;
use Omeka\Model\Entity\Job;

/**
 * @Entity
 */
class ZoteroImport extends AbstractEntity
{
    /**
     * @Id
     * @Column(type="integer")
     * @GeneratedValue
     */
    protected $id;

    /**
     * @OneToOne(targetEntity="Omeka\Model\Entity\Job")
     * @JoinColumn(onDelete="SET NULL")
     */
    protected $job;

    /**
     * @Column(type="integer")
     */
    protected $version;

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

    public function setVersion($version)
    {
        $this->version = $version;
    }

    public function getVersion()
    {
        return $this->version;
    }
}
