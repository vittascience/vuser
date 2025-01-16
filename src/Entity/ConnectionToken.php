<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;

#[ORM\Entity(repositoryClass: "User\Repository\ConnectionTokenRepository")]
#[ORM\Table(name: "connection_tokens")]
class ConnectionToken 
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id", type: "integer")]
    private int $id;

    #[ORM\ManyToOne(targetEntity: "User\Entity\User", cascade: ["remove"])]
    #[ORM\JoinColumn(name: "user_ref", referencedColumnName: "id", onDelete: "CASCADE")]
    private ?User $userRef;

    #[ORM\Column(name: "token", type: "string", length: 255, nullable: true)]
    private ?string $token;

    #[ORM\Column(name: "last_time_active", type: "datetime", nullable: true)]
    private ?\DateTime $lastTimeActive;

    #[ORM\Column(name: "date_inserted", type: "datetime", nullable: true)]
    private ?\DateTime $dateInserted;

    #[ORM\Column(name: "is_expired", type: "boolean", nullable: true)]
    private ?bool $isExpired;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserRef(): ?User
    {
        return $this->userRef;
    }

    public function setUserRef(?User $userRef): void
    {
        $this->userRef = $userRef;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function setToken(?string $token): void
    {
        $this->token = $token;
    }

    public function getLastTimeActive(): ?\DateTime
    {
        return $this->lastTimeActive;
    }

    public function setLastTimeActive(?\DateTime $lastTimeActive): void
    {
        $this->lastTimeActive = $lastTimeActive;
    }

    public function getDateInserted(): ?\DateTime
    {
        return $this->dateInserted;
    }

    public function setDateInserted(?\DateTime $dateInserted): void
    {
        $this->dateInserted = $dateInserted;
    }

    public function getIsExpired(): ?bool
    {
        return $this->isExpired;
    }

    public function setIsExpired(?bool $isExpired): void
    {
        $this->isExpired = $isExpired;
    }
}
