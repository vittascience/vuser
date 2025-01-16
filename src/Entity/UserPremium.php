<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;
use Utils\Exceptions\EntityDataIntegrityException;

#[ORM\Entity(repositoryClass: "User\Repository\UserRepository")]
#[ORM\Table(name: "user_premium")]
class UserPremium
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "id_user", referencedColumnName: "id", onDelete: "CASCADE")]
    private $user;

    #[ORM\Column(name: "date_begin", type: "datetime", nullable: true)]
    private ?\DateTime $dateBegin = null;

    #[ORM\Column(name: "date_end", type: "datetime", nullable: true)]
    private ?\DateTime $dateEnd = null;

    public function __construct(User $user, $dateBegin = null, $dateEnd = null)
    {
        $this->setUser($user);
        $this->setDateBegin($dateBegin);
        $this->setDateEnd($dateEnd);
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getDateBegin(): ?\DateTime
    {
        return $this->dateBegin;
    }

    public function setDateBegin($dateBegin): void
    {
        if ($dateBegin instanceof \DateTime || $dateBegin == null) {
            $this->dateBegin = $dateBegin;
        } else {
            throw new EntityDataIntegrityException("dateBegin needs to be DateTime or null");
        }
    }

    public function getDateEnd(): ?\DateTime
    {
        return $this->dateEnd;
    }

    public function setDateEnd($dateEnd): void
    {
        if ($dateEnd instanceof \DateTime || $dateEnd == null) {
            $this->dateEnd = $dateEnd;
        } else {
            throw new EntityDataIntegrityException("dateEnd needs to be DateTime or null");
        }
    }

    public function isTester(): bool
    {
        if (($this->getDateEnd() == null || $this->getDateEnd() > new \DateTime()) && ($this->getDateEnd() == null || $this->getDateEnd() > new \DateTime())) {
            return true;
        }
        return false;
    }

    public function jsonSerialize(): array
    {
        return [
            'dateEnd' => $this->getDateBegin(),
            'dateBegin' => $this->getDateEnd()
        ];
    }
}
