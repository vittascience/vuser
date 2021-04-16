<?php


namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use User\Entity\User;

/**
 * @ORM\Entity(repositoryClass="User\Repository\UserRepository")
 * @ORM\Table(name="user_regulars")
 */
class Regular
{

    // REG_BIO checks if string contains only letters and digits and the length is between 1 and 1000
    const REG_BIO = "/^[a-zA-ZáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ]{1}[\w\sáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ'&@-_()]{0,1999}[\wáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ)]{0,1}$/";

    /**
     * @ORM\Id
     * @ORM\ManyToOne(targetEntity="User\Entity\User")
     * @ORM\JoinColumn(name="id", referencedColumnName="id", onDelete="CASCADE")
     * @var User
     */
    private $user;

    /**
     * @ORM\Column(name="bio", type="string", length=2000, nullable=true)
     */
    private $bio;
    /** 
     * @ORM\Column(name="email", type="string", length=255, unique=true, nullable=false)
     * @var string
     */
    private $email;
    /**
     * @ORM\Column(name="telephone", type="string", length=255, nullable=true)
     * @var string
     */
    private $telephone = NULL;

    /** 
     * @ORM\Column(name="confirm_token", type="string", length=250, nullable=true)
     * @var string
     */
    private $confirmToken = null;
    /**
     * @ORM\Column(name="contact_flag", type="boolean",options={"default":true})
     * @var bool
     */
    private $contactFlag = false;
    /**
     * @ORM\Column(name="newsletter", type="boolean",options={"default":true})
     * @var bool
     */
    private $newsletter = false;
    /**
     * @ORM\Column(name="mail_messages", type="boolean")
     * @var bool
     */
    private $mailMessages = false;
    /**
     * @ORM\Column(name="is_active", type="boolean",options={"default":false})
     * @var bool
     */
    private $active = false;
    /**
     * @ORM\Column(name="recovery_token", type="string", length=255, nullable=true)
     * @var string
     */
    private $recoveryToken = null;
    /**
     * @ORM\Column(name="new_mail", type="string", length=1000, nullable=true)
     * @var string
     */
    private $newMail = null;
    /**
     * @ORM\Column(name="is_admin", type="boolean",options={"default":false})
     * @var bool
     */
    private $isAdmin = null;
    /**
     * @ORM\Column(name="private_flag", type="boolean")
     * @var bool
     */
    private $privateFlag = false;

    public function __construct(User $user, $email, $bio = NULL, $telephone = NULL, $privateFlag = false, $isAdmin = false, $newMail = null, $recoveryToken = null, $active = false, $mailMessages = false, $newsletter = false, $contactFlag = false, $confirmToken = null)
    {
        $this->setUser($user);
        $this->setEmail($email);
        $this->setBio($bio);
        $this->setTelephone($telephone);
        $this->setPrivateFlag($privateFlag);
        $this->setIsAdmin($isAdmin);
        $this->setNewMail($newMail);
        $this->setRecoveryToken($recoveryToken);
        $this->setActive($active);
        $this->setMailMessages($mailMessages);
        $this->setNewsletter($newsletter);
        $this->setContactFlag($contactFlag);
        $this->setConfirmToken($confirmToken);
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
     * @return bool
     */
    public function getIsAdmin()
    {
        return $this->isAdmin;
    }

    /**
     * @param bool $isAdmin
     */
    public function setIsAdmin($isAdmin)
    {
        if (is_bool($isAdmin)) {
            $this->isAdmin = $isAdmin;
        } else {
            throw new EntityDataIntegrityException("isAdmin needs to be boolean");
        }
    }

    /**
     * @return string
     */
    public function getBio()
    {
        return $this->bio;
    }

    /**
     * @param string $bio
     */
    public function setBio($bio)
    {
        if (preg_match(self::REG_BIO, $bio) || $bio == null) {
            $this->bio = $bio;
        } else {
            throw new EntityDataIntegrityException("bio needs to be string and have between 1 and 1000 characters");
        }
    }


    /**
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        /* if (filter_var($email, FILTER_VALIDATE_EMAIL)) { */
        $this->email = $email;
        /* } else {
            throw new EntityDataIntegrityException("invalid e-mail adresse");
        } */
    }


    /**
     * @return int
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * @param bool $active
     */
    public function setActive($active)
    {

        $this->active = $active;
    }

    /**
     * @return string
     */
    public function getTelephone()
    {
        return $this->telephone;
    }

    /**
     * @param string $telephone
     */
    public function setTelephone($telephone)
    {
        if (is_string($telephone) || $telephone == null) {
            $this->telephone = $telephone;
        } else {
            throw new EntityDataIntegrityException("telephone needs to be string or null");
        }
    }

    /**
     * @return string
     */
    public function getConfirmToken()
    {
        return $this->confirmToken;
    }

    /**
     * @param string $confirmToken
     */
    public function setConfirmToken($confirmToken)
    {
        if (is_string($confirmToken) || $confirmToken == null) {
            $this->confirmToken = $confirmToken;
        } else {
            throw new EntityDataIntegrityException("confirmToken needs to be string or null");
        }
    }

    /**
     * @return bool
     */
    public function isContactFlag()
    {
        return $this->contactFlag;
    }

    /**
     * @param bool $contactFlag
     */
    public function setContactFlag($contactFlag)
    {

        $this->contactFlag = $contactFlag;
    }

    /**
     * @return bool
     */
    public function isNewsletter()
    {
        return $this->newsletter;
    }

    /**
     * @param bool $newsletter
     */
    public function setNewsletter($newsletter)
    {

        $this->newsletter = $newsletter;
    }

    /**
     * @return bool
     */
    public function isMailMessages()
    {
        return $this->mailMessages;
    }

    /**
     * @param bool $mailMessages
     */
    public function setMailMessages($mailMessages)
    {


        $this->mailMessages = $mailMessages;
    }

    /**
     * @return string
     */
    public function getRecoveryToken()
    {
        return $this->recoveryToken;
    }

    /**
     * @param string $recoveryToken
     */
    public function setRecoveryToken($recoveryToken)
    {
        if (is_string($recoveryToken) || $recoveryToken == null) {
            $this->recoveryToken = $recoveryToken;
        } else {
            throw new EntityDataIntegrityException("recoveryToken needs to be string or null");
        }
    }

    /**
     * @return string
     */
    public function getNewMail()
    {
        return $this->newMail;
    }

    /**
     * @param string $newMail
     */
    public function setNewMail($newMail)
    {
        if (is_string($newMail) || $newMail == null) {
            $this->newMail = $newMail;
        } else {
            throw new EntityDataIntegrityException("newMail needs to be string or null");
        }
    }
    /**
     * @return bool
     */
    public function isPrivateFlag()
    {
        return $this->privateFlag;
    }

    /**
     * @param bool $privateFlag
     */
    public function setPrivateFlag($privateFlag)
    {
        $this->privateFlag = $privateFlag;
    }
    public function jsonSerialize()
    {
        if ($this->isPrivateFlag() == false) {
            $array = [
                "bio" => $this->getBio(),
                "email" => $this->getEmail()
            ];
        } else {
            $array = [
                "bio" => $this->getBio(),
                "email" => $this->getEmail()
            ];
        }
        return $array;
    }

    public static function jsonDeserialize($jsonDecoded)
    {
        $classInstance = new self(new User(), "abc@vittascience.com");
        foreach ($jsonDecoded as $attributeName => $attributeValue) {
            $classInstance->{$attributeName} = $attributeValue;
        }
        return $classInstance;
    }
}
