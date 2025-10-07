<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="App\Repository\UserConnectionHistoryRepository")
 * @ORM\Table(name="user_connection_history", indexes={
 *     @ORM\Index(name="user_idx", columns={"user_id"}),
 *     @ORM\Index(name="timestamp_idx", columns={"timestamp"})
 * })
 */
class UserConnectionHistory
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private int $id;

    /** @ORM\Column(name="user_id", type="integer") */
    private int $userId;

    /** @ORM\Column(type="datetime") */
    private \DateTime $timestamp;

    /** @ORM\Column(type="string", length=255, nullable=true) */
    private ?string $device = null;

    /** @ORM\Column(type="string", length=2, nullable=true) */
    private ?string $country = null;

    /** @ORM\Column(type="string", length=55, nullable=true) */
    private ?string $ip = null;

    // Getters and setters (optionally typed for IDE support)
    public function getId(): int { return $this->id; }

    public function getUserId(): int { return $this->userId; }
    public function setUserId(int $userId): self { $this->userId = $userId; return $this; }

    public function getTimestamp(): \DateTime { return $this->timestamp; }
    public function setTimestamp(\DateTime $timestamp): self { $this->timestamp = $timestamp; return $this; }

    public function getDevice(): ?string { return $this->device; }
    public function setDevice(?string $device): self { $this->device = $device; return $this; }

    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): self { $this->country = $country; return $this; }

    public function getIp(): ?string { return $this->ip; }
    public function setIp(?string $ip): self { $this->ip = $ip; return $this; }

}
