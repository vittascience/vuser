<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

/**
 * @ORM\Entity(repositoryClass="User\Repository\ClassroomUserConnectionLogRepository")
 * @ORM\Table(name="user_classroom_user_connection_logs")
 */
class ClassroomUserConnectionLog  {

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     * @var integer
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE")
     * @var User
     */
    private $user;
    
     /**
     * @ORM\Column(name="gar_id", type="string", length=255, nullable=true)
     * @var string
     */
    private $garId;

     /**
     * @ORM\Column(name="connection_date", type="datetime",options={"default": "CURRENT_TIMESTAMP"})
     */
    private $connectionDate;

    /**
     * Get the value of id
     *
     * @return  integer
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of user
     *
     * @return  User
     */ 
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @param  User  $user
     *
     * @return  self
     */ 
    public function setUser(User $user)
    {
        if(!( $user instanceof User)){
            throw new EntityDataIntegrityException("The user has to be an instance of User class");
        }
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of garId
     *
     * @return  string
     */ 
    public function getGarId()
    {
        return $this->garId;
    }

    /**
     * Set the value of garId
     *
     * @param  string  $garId
     *
     * @return  self
     */ 
    public function setGarId($garId)
    {
        if (is_string($garId) || $garId == NULL) {
            if ((strlen($garId) == 0 || strlen($garId) < 255)) {
                $this->garId = $garId;
            } else {
                throw new EntityDataIntegrityException("garId needs to have a lenght null or less than 255 characters");
            }
        } else {
            throw new EntityDataIntegrityException("garId needs to be string or null ");
        }
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

    public function jsonSerialize(): mixed
    {
        return [
            'id' => $this->getId(),
            'user' => $this->getUser()->getId(),
            'garId' => $this->getGarId(),
            'connectionDate' => $this->getConnectionDate()
        ];
    }

    
}