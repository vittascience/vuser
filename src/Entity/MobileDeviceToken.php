<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;

/**
 * @ORM\Entity(repositoryClass="User\Repository\MobileDeviceTokenRepository")
 * @ORM\Table(name="mobile_device_tokens")
 */
class MobileDeviceToken
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id", type="integer")
     * @var int
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="user_ref", referencedColumnName="id", onDelete="CASCADE")
     * @var User
     */
    private $userRef;

    /**
     * @ORM\Column(name="token", type="string", length=255, nullable=false)
     * @var string
     */
    private $token;

    /**
     * @ORM\Column(name="device_id", type="string", length=64, nullable=false)
     * @var string
     */
    private $deviceId;

    /**
     * @ORM\Column(name="device_name", type="string", length=150, nullable=true)
     * @var string
     */
    private $deviceName;

    /**
     * @ORM\Column(name="platform", type="string", length=50, nullable=true)
     * @var string
     */
    private $platform;

    /**
     * @ORM\Column(name="date_inserted", type="datetime", nullable=false)
     * @var \DateTime
     */
    private $dateInserted;

    /**
     * @ORM\Column(name="last_used_at", type="datetime", nullable=true)
     * @var \DateTime
     */
    private $lastUsedAt;

    /**
     * @ORM\Column(name="revoked_at", type="datetime", nullable=true)
     * @var \DateTime
     */
    private $revokedAt;


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

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): void
    {
        $this->deviceId = $deviceId;
    }

    public function getDeviceName(): ?string
    {
        return $this->deviceName;
    }

    public function setDeviceName(?string $deviceName): void
    {
        $this->deviceName = $deviceName;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): void
    {
        $this->platform = $platform;
    }

    public function getDateInserted(): ?\DateTime
    {
        return $this->dateInserted;
    }

    public function setDateInserted(?\DateTime $dateInserted): void
    {
        $this->dateInserted = $dateInserted;
    }

    public function getLastUsedAt(): ?\DateTime
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTime $lastUsedAt): void
    {
        $this->lastUsedAt = $lastUsedAt;
    }

    public function getRevokedAt(): ?\DateTime
    {
        return $this->revokedAt;
    }

    public function setRevokedAt(?\DateTime $revokedAt): void
    {
        $this->revokedAt = $revokedAt;
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }
}
