<?php

namespace User\Entity;

use Lti13\Entity\LtiConsumer;
use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

#[ORM\Entity(repositoryClass: "User\Repository\LtiUserRepository")]
#[ORM\Table(name: "user_lti_users")]
class LtiUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id", type: "integer")]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: "Lti13\Entity\LtiConsumer")]
    #[ORM\JoinColumn(name: "consumer_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private ?LtiConsumer $ltiConsumer = null;

    #[ORM\Column(name: "lti_user_id", type: "string", length: 255, nullable: true)]
    private ?string $ltiUserId = null;

    #[ORM\Column(name: "lti_course_id", type: "string", length: 255, nullable: true)]
    private ?string $ltiCourseId = null;

    #[ORM\Column(name: "is_teacher", type: "boolean", nullable: true, options: ["default" => null])]
    private ?bool $isTeacher = null;

    public function __construct()
    {

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser($user): self
    {
        if (!($user instanceof User)) {
            throw new EntityDataIntegrityException("The user has to be an instance of User class");
        }
        $this->user = $user;

        return $this;
    }

    public function getLtiConsumer(): ?LtiConsumer
    {
        return $this->ltiConsumer;
    }

    public function setLtiConsumer($ltiConsumer): self
    {
        if ($ltiConsumer !== null && !($ltiConsumer instanceof LtiConsumer)) {
            throw new EntityDataIntegrityException("The lti consumer has to be an instance of LtiConsumer class");
        }
        $this->ltiConsumer = $ltiConsumer;

        return $this;
    }

    public function getLtiUserId(): ?string
    {
        return $this->ltiUserId;
    }

    public function setLtiUserId($ltiUserId): self
    {
        if ($ltiUserId === null || !is_string($ltiUserId) || empty($ltiUserId)) {
            throw new EntityDataIntegrityException("Invalid Value provided for the lti user id");
        }
        $this->ltiUserId = $ltiUserId;

        return $this;
    }

    public function getLtiCourseId(): ?string
    {
        return $this->ltiCourseId;
    }

    public function setLtiCourseId($ltiCourseId): self
    {
        // Si la valeur n'est pas une chaîne ou est une chaîne vide, lever une exception
        if (!is_string($ltiCourseId) || trim($ltiCourseId) === "") {
            throw new EntityDataIntegrityException("Invalid value provided for lti course id");
        }
        $this->ltiCourseId = $ltiCourseId;
        return $this;
    }

    public function getIsTeacher(): ?bool
    {
        return $this->isTeacher;
    }

    public function setIsTeacher($isTeacher): self
    {
        if ($isTeacher === null || !is_bool($isTeacher)) {
            throw new EntityDataIntegrityException("The isTeacher field has to be a boolean value");
        }
        $this->isTeacher = $isTeacher;
    
        return $this;
    }
}