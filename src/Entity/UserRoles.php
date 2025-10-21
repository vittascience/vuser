<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="User\Repository\UserRolesRepository")
 * @ORM\Table(name="user_roles")
 */
class UserRoles
{
    /** @ORM\Id @ORM\GeneratedValue @ORM\Column(type="integer") */
    private int $id;

    /** @ORM\Column(type="string", length=255) */
    private string $name;

    /** @ORM\Column(type="boolean") */
    private bool $active = true;

    public function __construct()
    {
        $this->isActive = true;
    }

    // Getters and setters (optionally typed for IDE support)
    public function getId(): int
    {
        return $this->id;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

}
