<?php

namespace User\Entity;

use Lti13\Entity\LtiConsumer;
use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;

/**
 * @ORM\Entity(repositoryClass="User\Repository\LtiUserRepository")
 * @ORM\Table(name="user_lti_users")
 */
class LtiUser{

    /**
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @ORM\Column(name="id", type="integer")
     * @var integer
     */
    private $id;

    /**
     * @ORM\OneToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="user_id", referencedColumnName="id", onDelete="CASCADE")
     * @var User
     */
    private $user;

    /**
     * @ORM\ManyToOne(targetEntity="Lti13\Entity\LtiConsumer")
     * @ORM\JoinColumn(name="consumer_id", referencedColumnName="id", onDelete="CASCADE")
     *
     * @var LtiConsumer
     */
    private $ltiConsumer;

    /**
     * @ORM\Column(name="lti_user_id",type="string",length=255,  nullable=false)
     *
     * @var integer
     */
    private $ltiUserId;

    /**
     * @ORM\Column(name="lti_course_id",type="string", length=255, nullable=true)
     */
    private $ltiCourseId;

    /**
     * @ORM\Column(name="is_teacher", type="boolean", options={"default":false})
     *
     * @var bool
     */
    private $isTeacher;

    /**
     * Get the value of id
     *
     * @return  integer
     */ 
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the value of user
     *
     * @return  User
     */ 
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set the value of user
     *
     * @param  User  $user
     *
     * @return  self
     */ 
    public function setUser($user)
    {
        if(!( $user instanceof User)){
            throw new EntityDataIntegrityException("The user has to be an instance of User class");
        }
        $this->user = $user;

        return $this;
    }

    /**
     * Get the value of ltiTool
     *
     * @return  LtiConsumer
     */ 
    public function getLtiConsumer()
    {
        return $this->ltiConsumer;
    }

    /**
     * Set the value of ltiTool
     *
     * @param  LtiConsumer  $ltiConsumer
     *
     * @return  self
     */ 
    public function setLtiConsumer($ltiConsumer)
    {
        if(!($ltiConsumer instanceof LtiConsumer)){
            throw new EntityDataIntegrityException("The lti consumer has to be an instance of LtiTool class");
        }
        $this->ltiConsumer = $ltiConsumer;

        return $this;
    }

    /**
     * Get the value of ltiUserId
     *
     * @return  integer
     */ 
    public function getLtiUserId()
    {
        return $this->ltiUserId;
    }

    /**
     * Set the value of ltiUserId
     *
     * @param  integer  $ltiUserId
     *
     * @return  self
     */ 
    public function setLtiUserId($ltiUserId)
    {
       // the $ltiUserId is not a string (meaning an object or array) OR is empty
       if(!is_string($ltiUserId) || !$ltiUserId){
        throw new EntityDataIntegrityException("Invalid Value provided for the lti user id");
        }
        $this->ltiUserId = $ltiUserId;

        return $this;
    }

    /**
     * Get the value of ltiCourseId
     * 
     * @return  integer
     */ 
    public function getLtiCourseId()
    {
        return $this->ltiCourseId;
    }

    /**
     * Set the value of ltiCourseId
     *
     * @param  integer  $ltiCourseId
     * 
     * @return  self
     */ 
    public function setLtiCourseId($ltiCourseId)
    {
        if( !is_string($ltiCourseId)  || !$ltiCourseId ){
            throw new EntityDataIntegrityException("Invalid value provided for lti course id");
        }
        $this->ltiCourseId = $ltiCourseId;

        return $this;
    }

    /**
     * Get the value of isTeacher
     *
     * @return  bool
     */ 
    public function getIsTeacher()
    {
        return $this->isTeacher;
    }

    /**
     * Set the value of isTeacher
     *
     * @param  bool  $isTeacher
     *
     * @return  self
     */ 
    public function setIsTeacher($isTeacher)
    {
        if(!is_bool($isTeacher)){
            throw new EntityDataIntegrityException("The isTeacher fields has to be a boolean value");
        }
        $this->isTeacher = $isTeacher;

        return $this;
    }
}