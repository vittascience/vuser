<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;

#[ORM\Entity(repositoryClass: "User\Repository\UserRepository")]
#[ORM\Table(name: "user_teachers")]
class Teacher
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: "User\Entity\User")]
    #[ORM\JoinColumn(name: "id", referencedColumnName: "id", onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(name: "subject", type: "integer", length: 3, nullable: true)]
    private ?int $subject;

    #[ORM\Column(name: "school", type: "string", length: 255, nullable: true)]
    private ?string $school;

    #[ORM\Column(name: "grade", type: "integer", length: 3, nullable: true)]
    private ?int $grade;

    public function __construct(User $user, int $subject = 1, string $school = "Collège", int $grade = 1)
    {
        $this->setUser($user);
        $this->setSubject($subject);
        $this->setSchool($school);
        $this->setGrade($grade);
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    public function getSubject(): ?int
    {
        return $this->subject;
    }

    public function setSubject(?int $subject): void
    {
        $this->subject = $subject;
    }

    public function getSchool(): ?string
    {
        return $this->school;
    }

    public function setSchool(?string $school): void
    {
        $this->school = $school;
    }

    public function getGrade(): ?int
    {
        return $this->grade;
    }

    public function setGrade(?int $grade): void
    {
        $this->grade = $grade;
    }

    public function jsonSerialize(): array
    {
        return [
            'user' => $this->getUser(),
            'grade' => $this->getGrade(),
            'school' => $this->getSchool(),
            'subject' => $this->getSubject()
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
