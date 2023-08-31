<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;

/**
 * @ORM\Entity(repositoryClass="User\Repository\LtiUserConnectionRepository")
 * @ORM\Table(name="user_lti_user_connections")
 */
class LtiUserConnection{
    
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     */
    private $id; 

    /**
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE",nullable=false)
     */
    private $user;

    /**
     * @ORM\Column(name="connection_date", type="datetime",options={"default": "CURRENT_TIMESTAMP"})
     */
    private $connectionDate;

    /**
     * @ORM\Column(name="connection_duration", type="integer")
     */
    private $connectionDuration;


    /**
     * Get the value of id
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of user
     */ 
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @return  self
     */ 
    public function setUser($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of connectionDate
     */ 
    public function getConnectionDate()
    {
        return $this->connectionDate;
    }

    /**
     * Set the value of connectionDate
     *
     * @return  self
     */ 
    public function setConnectionDate($connectionDate)
    {
        $this->connectionDate = $connectionDate;

        return $this;
    }

    /**
     * Get the value of connectionDuration
     */ 
    public function getConnectionDuration()
    {
        return $this->connectionDuration;
    }

    /**
     * Set the value of connectionDuration
     *
     * @return  self
     */ 
    public function setConnectionDuration($connectionDuration)
    {
        $this->connectionDuration = $connectionDuration;

        return $this;
    }
}