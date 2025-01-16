<?php

namespace User\Entity;

use Lti13\Entity\LtiConsumer;
use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;

#[ORM\Entity(repositoryClass: "User\Repository\LtiUserRepository")]
#[ORM\Table(name: "user_lti_users")]
class LtiUser
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: "AUTO")]
    #[ORM\Column(name: "id", type: "integer")]
    private int $id;

    #[ORM\OneToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "user_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private User $user;

    #[ORM\ManyToOne(targetEntity: "Lti13\Entity\LtiConsumer")]
    #[ORM\JoinColumn(name: "consumer_id", referencedColumnName: "id", onDelete: "CASCADE")]
    private LtiConsumer $ltiConsumer;

    #[ORM\Column(name: "lti_user_id", type: "string", length: 255, nullable: false)]
    private string $ltiUserId;

    #[ORM\Column(name: "lti_course_id", type: "string", length: 255, nullable: true)]
    private ?string $ltiCourseId;

    #[ORM\Column(name: "is_teacher", type: "boolean", options: ["default" => false])]
    private bool $isTeacher;

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

    public function getLtiConsumer(): LtiConsumer
    {
        return $this->ltiConsumer;
    }

    public function setLtiConsumer(LtiConsumer $ltiConsumer): self
    {
        if (!($ltiConsumer instanceof LtiConsumer)) {
            throw new EntityDataIntegrityException("The lti consumer has to be an instance of LtiTool class");
        }
        $this->ltiConsumer = $ltiConsumer;

        return $this;
    }

    public function getLtiUserId(): string
    {
        return $this->ltiUserId;
    }

    public function setLtiUserId(string $ltiUserId): self
    {
        if (!is_string($ltiUserId) || !$ltiUserId) {
            throw new EntityDataIntegrityException("Invalid Value provided for the lti user id");
        }
        $this->ltiUserId = $ltiUserId;

        return $this;
    }

    public function getLtiCourseId(): ?string
    {
        return $this->ltiCourseId;
    }

    public function setLtiCourseId(?string $ltiCourseId): self
    {
        if (!is_string($ltiCourseId) || !$ltiCourseId) {
            throw new EntityDataIntegrityException("Invalid value provided for lti course id");
        }
        $this->ltiCourseId = $ltiCourseId;

        return $this;
    }

    public function getIsTeacher(): bool
    {
        return $this->isTeacher;
    }

    public function setIsTeacher(bool $isTeacher): self
    {
        if (!is_bool($isTeacher)) {
            throw new EntityDataIntegrityException("The isTeacher fields has to be a boolean value");
        }
        $this->isTeacher = $isTeacher;

        return $this;
    }
}
