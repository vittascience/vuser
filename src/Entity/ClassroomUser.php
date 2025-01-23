<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

#[ORM\Entity(repositoryClass: "User\Repository\ClassroomUserRepository")]
#[ORM\Table(name: "user_classroom_users")]
class ClassroomUser implements \JsonSerializable, \Utils\JsonDeserializer
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "user", referencedColumnName: "id", onDelete: "CASCADE")]
    private $id = null;

    #[ORM\Column(name: "gar_id", type: "string", length: 255, nullable: true)]
    private ?string $garId = null;

    #[ORM\Column(name: "canope_id", type: "string", length: 255, nullable: true)]
    private ?string $canopeId = null;

    #[ORM\Column(name: "school_id", type: "string", length: 8, nullable: true)]
    private ?string $schoolId = null;

    #[ORM\Column(name: "is_teacher", type: "boolean", nullable: true)]
    private bool $isTeacher = false;

    #[ORM\Column(name: "mail_teacher", type: "string", length: 255, nullable: true)]
    private ?string $mailTeacher = null;

    public function __construct(User $user, $garId = NULL, $schoolId = NULL, $isTeacher = false, $mailTeacher = NULL)
    {
        $this->setId($user);
        $this->setSchoolId($schoolId);
        $this->setGarId($garId);
        $this->setIsTeacher($isTeacher);
        $this->setMailTeacher($mailTeacher);
    }

    public function getGarId(): ?string
    {
        return $this->garId;
    }

    public function setGarId($garId): void
    {
        if (is_string($garId) || $garId == NULL) {
            if ((strlen($garId) == 0 || strlen($garId) < 255)) {
                $this->garId = $garId;
            } else {
                throw new EntityDataIntegrityException("garId needs to have a length null or less than 255 characters");
            }
        } else {
            throw new EntityDataIntegrityException("garId needs to be string or null ");
        }
    }

    public function getCanopeId(): ?string
    {
        return $this->canopeId;
    }

    public function setCanopeId($canopeId): self
    {
        if ((!is_string($canopeId) && !is_numeric($canopeId)) || !$canopeId) {
            throw new EntityDataIntegrityException("Invalid Value provided for the canope user id");
        }
        $this->canopeId = $canopeId;

        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getSchoolId(): ?string
    {
        return $this->schoolId;
    }

    public function setSchoolId($schoolId): void
    {
        if (is_string($schoolId) || $schoolId == NULL) {
            if ((strlen($schoolId) == 0 || strlen($schoolId) == 8)) {
                $this->schoolId = $schoolId;
            } else {
                throw new EntityDataIntegrityException("schoolId needs to have a length null or equal to 8");
            }
        } else {
            throw new EntityDataIntegrityException("schoolId needs to be string or null");
        }
    }

    public function getMailTeacher(): ?string
    {
        return $this->mailTeacher;
    }

    public function setMailTeacher($mailTeacher): void
    {
        if ($mailTeacher == NULL || filter_var($mailTeacher, FILTER_VALIDATE_EMAIL) || $mailTeacher = "") {
            $this->mailTeacher = $mailTeacher;
        } else {
            throw new EntityDataIntegrityException("invalid e-mail address: " . $mailTeacher);
        }
    }

    public function getIsTeacher(): bool
    {
        return $this->isTeacher;
    }

    public function setIsTeacher($isTeacher): void
    {
        if (is_bool($isTeacher)) {
            $this->isTeacher = $isTeacher;
        } else {
            throw new EntityDataIntegrityException("isTeacher needs to be boolean");
        }
    }

    public function jsonSerialize(): array
    {
        $id = $this->getId();
        if ($id != null) {
            $id = $this->getId()->jsonSerialize();
        }
        return [
            'garId' => $this->getGarId(),
            'schoolId' => $this->getSchoolId(),
            'mailTeacher' => $this->getMailTeacher(),
            'isTeacher' => $this->getIsTeacher(),
            'id' => $id,
        ];
    }

    public static function jsonDeserialize($jsonDecoded): self
    {
        $classInstance = new self(new User());
        foreach ($jsonDecoded as $attributeName => $attributeValue) {
            $classInstance->{$attributeName} = $attributeValue;
        }
        return $classInstance;
    }
}
