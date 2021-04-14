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
     * @ORM\Column(name="subject", type="integer", length=3, nullable=false)
     * the correspondance table is in /resources
     * @var integer
     */
    private $subject;
    /**
     * @ORM\Column(name="school", type="string", length=255, nullable=false)
     * @var string
     */
    private $school;
    /**
     * @ORM\Column(name="grade", type="integer", length=3, nullable=false)
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
    public function getUser()
    {
        return $this->user;
    }
    /**
     * @param User $id
     */
    public function setUser($user)
    {
        $this->user = $user;
    }
    /**
     * @return integer
     */
    public function getSubject()
    {
        return $this->subject;
    }
    /**
     * @param int $subject
     */
    public function setSubject($subject)
    {
        if (is_int($subject) && $subject >= 1 && $subject <= 22) {
            $this->subject = $subject;
        } else
            throw new EntityDataIntegrityException("subject needs to be integer and between 1 and 22");
    }
    /**
     * @return string
     */
    public function getSchool()
    {
        return $this->school;
    }
    /**
     * @param string $school
     */
    public function setSchool($school)
    {
        if (is_string($school)) {
            $this->school = $school;
        } else {
            throw new EntityDataIntegrityException("school needs to be string");
        }
    }
    /**
     * @return integer
     */
    public function getGrade()
    {
        return $this->grade;
    }
    /**
     * @param int $grade
     */
    public function setGrade($grade)
    {
        if (is_int($grade) && $grade >= 1 && $grade <= 13) {
            $this->grade = $grade;
        } else
            throw new EntityDataIntegrityException("grade needs to be integer and between 1 and 13");
    }

    public function jsonSerialize()
    {
        return [
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
