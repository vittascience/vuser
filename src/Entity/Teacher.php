<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

/**
 * @ORM\Entity(repositoryClass="User\Repository\UserRepository")
 * @ORM\Table(name="user_teachers")
 */
class Teacher
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="id", referencedColumnName="id", onDelete="CASCADE")
     * @var User
     */
    private $user;


    /**
     * @ORM\Column(name="subject", type="integer", length=3, nullable=true)
     * the correspondance table is in /resources
     * @var integer
     */
    private $subject;


    /**
     * @ORM\Column(name="school", type="string", length=255, nullable=true)
     * @var string
     */
    private $school;


    /**
     * @ORM\Column(name="grade", type="integer", length=3, nullable=true)
     * * the correspondance table is in /resources
     * @var integer
     */
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
        if (is_int($subject) && $subject >= 1 && $subject <= 38)
            $this->subject = $subject;
        else
            throw new EntityDataIntegrityException("subject needs to be integer and between 1 and 38");
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
        if (is_int($grade) && $grade >= 1 && $grade <= 5) {
            $this->grade = $grade;
        } else
            throw new EntityDataIntegrityException("grade needs to be integer and between 1 and 5");
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
