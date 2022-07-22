<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

/**
 * @ORM\Entity(repositoryClass="User\Repository\UserRepository")
 * @ORM\Table(name="user_classroom_users")
 */
class ClassroomUser implements \JsonSerializable, \Utils\JsonDeserializer
{
    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="user", referencedColumnName="id", onDelete="CASCADE")
     * @var User
     */
    private $id = null;

    /**
     * @ORM\Column(name="gar_id", type="string", length=255, nullable=true)
     * @var string
     */
    private $garId;

    /**
     * @ORM\Column(name="canope_id", type="string", length=255, nullable=true)
     * @var string
     */
    private $canopeId;
    
    /**
     * @ORM\Column(name="school_id", type="string", length=8, nullable=true)
     * @var string
     */
    private $schoolId;
    /**
     * @ORM\Column(name="is_teacher", type="boolean", nullable=true)
     * @var string
     */
    private $isTeacher = false;
    /**
     * @ORM\Column(name="mail_teacher", type="string", length=255, nullable=true)
     * @var string
     */
    private $mailTeacher;

    public function __construct(User $user, $garId = NULL, $schoolId = NULL, $isTeacher = false, $mailTeacher = NULL)
    {
        $this->setId($user);
        $this->setSchoolId($schoolId);
        $this->setGarId($garId);
        $this->setIsTeacher($isTeacher);
        $this->setMailTeacher($mailTeacher);
    }

    /**
     * @return string
     */
    public function getGarId()
    {
        return $this->garId;
    }
    /**
     * @param string $garId
     */
    public function setGarId($garId)
    {
        if (is_string($garId) || $garId == NULL) {
            if ((strlen($garId) == 0 || strlen($garId) < 255)) {
                $this->garId = $garId;
            } else {
                throw new EntityDataIntegrityException("garId needs to have a lenght null or less than 255 characters");
            }
        } else {
            throw new EntityDataIntegrityException("garId needs to be string or null ");
        }
    }

    /**
     * Get the value of canopeId
     *
     * @return  string
     */ 
    public function getCanopeId()
    {
        return $this->canopeId;
    }

    /**
     * Set the value of canopeId
     *
     * @param  string  $canopeId
     *
     * @return  self
     */ 
    public function setCanopeId($canopeId)
    {
        // the $canopeId is not a string and not an int (meaning an object or array) OR is empty
        if((!is_string($canopeId) && !is_numeric($canopeId)) || !$canopeId){
            throw new EntityDataIntegrityException("Invalid Value provided for the canope user id");
        }
        $this->canopeId = $canopeId;

        return $this;
    }

    /**
     * @return User
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param User $id
     */
    public function setId($id)
    {

        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getSchoolId()
    {
        return $this->schoolId;
    }
    /**
     * @param string $schoolId
     */
    public function setSchoolId($schoolId)
    {
        if (is_string($schoolId) || $schoolId == NULL) {
            if ((strlen($schoolId) == 0 || strlen($schoolId) == 8)) {
                $this->schoolId = $schoolId;
            } else {
                throw new EntityDataIntegrityException("schoolId needs to have a lenght null or equal to 8");
            }
        } else {
            throw new EntityDataIntegrityException("schoolId needs to be string or null");
        }
    }
    /**
     * @return string
     */
    public function getMailTeacher()
    {
        return $this->mailTeacher;
    }
    /**
     * @param string $mailTeacher
     */
    public function setMailTeacher($mailTeacher)
    {
        if ($mailTeacher == NULL || filter_var($mailTeacher, FILTER_VALIDATE_EMAIL) || $mailTeacher = "") {
            $this->mailTeacher = $mailTeacher;
        } else {
            throw new EntityDataIntegrityException("invalid e-mail adresse : " . $mailTeacher);
        }
    }

    /**
     * @return boolean
     */
    public function getIsTeacher()
    {
        return $this->isTeacher;
    }
    /**
     * @param boolean $isTeacher
     */
    public function setIsTeacher($isTeacher)
    {
        if (is_bool($isTeacher)) {
            $this->isTeacher = $isTeacher;
        } else {
            throw new EntityDataIntegrityException("isTeacher needs to be boolean");
        }
    }

    public function jsonSerialize()
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

    public static function jsonDeserialize($jsonDecoded)
    {
        $classInstance = new self(new User());
        foreach ($jsonDecoded as $attributeName => $attributeValue) {
            $classInstance->{$attributeName} = $attributeValue;
        }
        return $classInstance;
    }
}
