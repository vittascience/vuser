<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\Exceptions\EntityOperatorException;
use Utils\MetaDataMatcher;

/**
 * @ORM\Entity(repositoryClass="User\Repository\UserRepository")
 * @ORM\Table(name="user_premium" )
 */
class UserPremium
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="id_user", referencedColumnName="id", onDelete="CASCADE")
     */
    private $user;
    /**
     * @ORM\Column(name="date_begin", type="datetime", nullable=true)
     * @var \DateTime
     */
    private $dateBegin = null;
    /**
     * @ORM\Column(name="date_end", type="datetime", nullable=true)
     * @var \DateTime
     */
    private $dateEnd = null;


    public function __construct(User $user, $dateBegin = null, $dateEnd = null)
    {
        $this->setUser($user);
        $this->setDateBegin($dateBegin);
        $this->setDateEnd($dateEnd);
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }
    /**
     * @param User $id
     */
    public function setUser($user)
    {
        $this->user = $user;
    }
    /**
     * @return \DateTime
     */
    public function getDateBegin()
    {
        return $this->dateBegin;
    }

    /**
     * @param \DateTime $dateBegin
     */
    public function setDateBegin($dateBegin)
    {
        if ($dateBegin instanceof \DateTime || $dateBegin == null) {
            $this->dateBegin = $dateBegin;
        } else {
            throw new EntityDataIntegrityException("dateBegin needs to be DateTime or null");
        }
    }

    /**
     * @return \DateTime
     */
    public function getDateEnd()
    {
        return $this->dateEnd;
    }

    /**
     * @param \DateTime $dateEnd
     */
    public function setDateEnd($dateEnd)
    {
        if ($dateEnd instanceof \DateTime || $dateEnd == null) {
            $this->dateEnd = $dateEnd;
        } else {
            throw new EntityDataIntegrityException("dateEnd needs to be DateTime or null");
        }
    }
    public function isTester()
    {
        if (($this->getDateEnd() == NULL || $this->getDateEnd() > date('Y-m-d H:i:s')) && ($this->getDateEnd() == NULL || $this->getDateEnd() > date('Y-m-d H:i:s'))) {
            return true;
        }
        return false;
    }
    public function jsonSerialize(): mixed
    {
        return [
            'dateEnd' => $this->getDateBegin(),
            'dateBegin' => $this->getDateEnd()
        ];
    }
}
