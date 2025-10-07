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
    private $id;

    /** @ORM\Column(type="integer") */
    private $userId;

    /** @ORM\Column(type="datetime") */
    private $timestamp;

    /** @ORM\Column(type="string", length=100, nullable=true) */
    private $device;

    /** @ORM\Column(type="string", length=2, nullable=true) */
    private $country;

    // Getters & setters inline
    public function getId(): ?int { return $this->id; }
    public function getUserId(): ?int { return $this->userId; }
    public function setUserId(int $userId): self { $this->userId = $userId; return $this; }
    public function getTimestamp(): ?\DateTimeInterface { return $this->timestamp; }
    public function setTimestamp(\DateTimeInterface $timestamp): self { $this->timestamp = $timestamp; return $this; }
    public function getDevice(): ?string { return $this->device; }
    public function setDevice(?string $device): self { $this->device = $device; return $this; }
    public function getCountry(): ?string { return $this->country; }
    public function setCountry(?string $country): self { $this->country = $country; return $this; }
}
