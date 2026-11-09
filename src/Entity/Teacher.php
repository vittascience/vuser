<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

#[ORM\Table(name: 'user_teachers')]
#[ORM\Entity(repositoryClass: \User\Repository\UserRepository::class)]
class Teacher
{
    /**
     * @var User
     */
    #[ORM\JoinColumn(name: 'id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: \User\Entity\User::class)]
    private $user;


    /**
     * the correspondance table is in /resources
     * @var integer
     */
    #[ORM\Column(name: 'subject', type: 'integer', length: 3, nullable: true)]
    private $subject;


    /**
     * @var string
     */
    #[ORM\Column(name: 'school', type: 'string', length: 255, nullable: true)]
    private $school;


    /**
     * * the correspondance table is in /resources
     * @var integer
     */
    #[ORM\Column(name: 'grade', type: 'integer', length: 3, nullable: true)]
    private $grade;

    public function __construct(User $user, $subject = 1, $school = "Collège", $grade = 1)
    {
        $this->setUser($user);
        $this->setSubject($subject);
        $this->setSchool($school);
        $this->setGrade($grade);
    }

    /**
     * @return User
     */
    public function getUser(): ?User
    {
        return $this->user;
    }
    /**
     * @param User $id
     */
    public function setUser(User $user)
    {
        $this->user = $user;
    }

    /**
     * @return integer
     */
    public function getSubject(): ?int
    {
        return $this->subject;
    }

    /**
     * @param int $subject
     */
    public function setSubject(?int $subject)
    {
        $this->subject = $subject;
    }

    /**
     * @return string
     */
    public function getSchool(): ?string
    {
        return $this->school;
    }

    /**
     * @param string $school
     */
    public function setSchool(?string $school)
    {
        $this->school = $school;
    }

    /**
     * @return integer
     */
    public function getGrade(): ?int
    {
        return $this->grade;
    }

    /**
     * @param int $grade
     */
    public function setGrade(?int $grade)
    {
        $this->grade = $grade;
    }

    public function jsonSerialize()
    {
        return [
            'user' => $this->getUser(),
            'grade' => $this->getGrade(),
            'school' => $this->getSchool(),
            'subject' => $this->getSubject()
        ];
    }

    public static function jsonDeserialize($jsonDecoded)
    {
        $classInstance = new self(new User());
        foreach ($jsonDecoded as $attributeName => $attributeValue) {
            $classInstance->{$attributeName} = $attributeValue;
        }
        return $classInstance;
    }
}
