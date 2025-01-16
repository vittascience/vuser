<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use User\Entity\User;

#[ORM\Entity(repositoryClass: "User\Repository\UserRepository")]
#[ORM\Table(name: "user_regulars")]
class Regular
{
    // REG_BIO checks if string contains only letters and digits and the length is between 1 and 1000
    const REG_BIO = "/^[a-zA-ZáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ]{1}[\w\sáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ'&@-_()]{0,1999}[\wáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ)]{0,1}$/";

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: "id", referencedColumnName: "id", onDelete: "CASCADE")]
    private User $user;

    #[ORM\Column(name: "bio", type: "string", length: 2000, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(name: "email", type: "string", length: 255, unique: true, nullable: false)]
    private string $email;

    #[ORM\Column(name: "telephone", type: "string", length: 255, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: "confirm_token", type: "string", length: 255, nullable: true)]
    private ?string $confirmToken = null;

    #[ORM\Column(name: "contact_flag", type: "boolean", options: ["default" => true])]
    private bool $contactFlag = false;

    #[ORM\Column(name: "newsletter", type: "boolean", options: ["default" => true])]
    private bool $newsletter = false;

    #[ORM\Column(name: "mail_messages", type: "boolean")]
    private bool $mailMessages = false;

    #[ORM\Column(name: "is_active", type: "boolean", options: ["default" => false])]
    private bool $active = false;

    #[ORM\Column(name: "recovery_token", type: "string", length: 255, nullable: true)]
    private ?string $recoveryToken = null;

    #[ORM\Column(name: "new_mail", type: "string", length: 1000, nullable: true)]
    private ?string $newMail = null;

    #[ORM\Column(name: "is_admin", type: "boolean", options: ["default" => false])]
    private bool $isAdmin = false;

    #[ORM\Column(name: "private_flag", type: "boolean")]
    private bool $privateFlag = false;

    public function __construct(User $user, string $email, ?string $bio = null, ?string $telephone = null, bool $privateFlag = false, bool $isAdmin = false, ?string $newMail = null, ?string $recoveryToken = null, bool $active = false, bool $mailMessages = false, bool $newsletter = false, bool $contactFlag = false, ?string $confirmToken = null)
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user)
    {
        $this->user = $user;
    }

    public function getIsAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function setIsAdmin(bool $isAdmin)
    {
        $this->isAdmin = $isAdmin;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio)
    {
        $this->bio = htmlspecialchars($bio);
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email)
    {
        $this->email = $email;
    }

    public function isActive(): ?int
    {
        return $this->active;
    }

    public function setActive(bool $active)
    {
        $this->active = $active;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone)
    {
        $this->telephone = $telephone;
    }

    public function getConfirmToken(): ?string
    {
        return $this->confirmToken;
    }

    public function setConfirmToken(?string $confirmToken)
    {
        $this->confirmToken = $confirmToken;
    }

    public function isContactFlag(): bool
    {
        return $this->contactFlag;
    }

    public function setContactFlag(bool $contactFlag)
    {
        $this->contactFlag = $contactFlag;
    }

    public function isNewsletter(): bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(bool $newsletter)
    {
        $this->newsletter = $newsletter;
    }

    public function isMailMessages(): bool
    {
        return $this->mailMessages;
    }

    public function setMailMessages(bool $mailMessages)
    {
        $this->mailMessages = $mailMessages;
    }

    public function getRecoveryToken(): ?string
    {
        return $this->recoveryToken;
    }

    public function setRecoveryToken(?string $recoveryToken)
    {
        $this->recoveryToken = $recoveryToken;
    }

    public function getNewMail(): ?string
    {
        return $this->newMail;
    }

    public function setNewMail(?string $newMail)
    {
        $this->newMail = $newMail;
    }

    public function isPrivateFlag(): bool
    {
        return $this->privateFlag;
    }

    public function setPrivateFlag(bool $privateFlag)
    {
        $this->privateFlag = $privateFlag;
    }

    public function jsonSerialize()
    {
        return [
            "bio" => $this->getBio(),
            "email" => $this->getEmail()
        ];
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
