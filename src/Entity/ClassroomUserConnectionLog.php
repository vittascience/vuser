<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

#[ORM\Entity(repositoryClass: "User\Repository\ClassroomUserConnectionLogRepository")]
#[ORM\Table(name: "user_classroom_user_connection_logs")]
class ClassroomUserConnectionLog  {

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id", type: "integer")]
    private int $id;

    #[ORM\OneToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private User $user;
    
    #[ORM\Column(name: "gar_id", type: "string", length: 255, nullable: true)]
    private ?string $garId;

    #[ORM\Column(name: "connection_date", type: "datetime", options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTime $connectionDate;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        if (!($user instanceof User)) {
            throw new EntityDataIntegrityException("The user has to be an instance of User class");
        }
        $this->user = $user;

        return $this;
    }

    public function getGarId(): ?string
    {
        return $this->garId;
    }

    public function setGarId(?string $garId): self
    {
        if (is_string($garId) || $garId === null) {
            if (strlen($garId) === 0 || strlen($garId) < 255) {
                $this->garId = $garId;
            } else {
                throw new EntityDataIntegrityException("garId needs to have a length null or less than 255 characters");
            }
        } else {
            throw new EntityDataIntegrityException("garId needs to be string or null");
        }
        return $this;
    }

    public function getConnectionDate(): \DateTime
    {
        return $this->connectionDate;
    }

    public function setConnectionDate(\DateTime $connectionDate): self
    {
        $this->connectionDate = $connectionDate;

        return $this;
    }

    public function jsonSerialize(): array
    {
        return [
            'id' => $this->getId(),
            'user' => $this->getUser()->getId(),
            'garId' => $this->getGarId(),
            'connectionDate' => $this->getConnectionDate()
        ];
    }
}
