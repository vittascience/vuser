<?php

namespace User\Entity;

use DateTime;
use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\ImageManager;

#[ORM\Entity(repositoryClass: "User\Repository\UserRepository")]
#[ORM\Table(name: "users")]
class User
{
    // REG_FIRSTNAME: Only letters and digits and length of string is between 1 and 100
    const REG_NAME = "/^[a-zA-ZáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ]{1}[\w\sáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ'&@\-_.()]{0,98}[\wáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ)]{0,1}$/";
    const REG_UNIQID_13 = "/^[a-f0-9]{13}$/";

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: "id", type: "integer")]
    private $id;

    #[ORM\OneToMany(targetEntity: "Learn\Entity\Favorite", mappedBy: "user")]
    private $favorite;

    #[ORM\Column(name: "firstname", type: "string", length: 100, nullable: false)]
    private $firstname;

    #[ORM\Column(name: "pseudo", type: "string", length: 100, nullable: true)]
    private $pseudo;

    #[ORM\Column(name: "surname", type: "string", length: 100, nullable: false)]
    private $surname;

    #[ORM\Column(name: "password", type: "string", length: 255, nullable: false)]
    private $password;

    #[ORM\Column(name: "insert_date", type: "datetime", columnDefinition: "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")]
    private $insertDate;

    #[ORM\Column(name: "update_date", type: "datetime", columnDefinition: "TIMESTAMP DEFAULT CURRENT_TIMESTAMP")]
    private $updateDate;

    #[ORM\Column(name: "picture", type: "string", length: 250, nullable: true)]
    private $picture = NULL;

    #[ORM\Column(name: "totp_secret", type: "string", length: 250, nullable: true)]
    private $totp_secret = NULL;

    public function __construct()
    {
        $today = new \DateTime();
        $this->setInsertDate($today);
    }

    public function setId($id)
    {
        if (is_int($id) && $id > 0) {
            $this->id = $id;
        } else
            throw new EntityDataIntegrityException("id needs to be integer and positive");
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getFirstname(): string
    {
        return $this->firstname;
    }

    public function setFirstname(string $firstname)
    {
        $this->firstname = $firstname;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(?string $pseudo)
    {
        $this->pseudo = $pseudo;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function setSurname(string $surname)
    {
        $this->surname = $surname;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password)
    {
        $this->password = $password;
    }

    public function getInsertDate(): ?DateTime
    {
        return $this->insertDate;
    }

    public function setInsertDate(?DateTime $insertDate)
    {
        $this->insertDate = $insertDate;
    }

    public function getUpdateDate(): ?DateTime
    {
        return $this->updateDate;
    }

    public function setUpdateDate(?DateTime $updateDate)
    {
        $this->updateDate = $updateDate;
    }

    public function getPicture(): ?string
    {
        return $this->picture;
    }

    public function setPicture(?string $picture)
    {
        $this->picture = $picture;
    }

    public function processPicture($picture)
    {
        $extension = explode("/", $picture["type"])[1];
        $fileId = md5(uniqid());
        $fileName = $fileId . "." . $extension;
        $finalFileName = $fileId . ".jpeg";
        $finalThumbnailName = $fileId . "_thumbnail.jpeg";

        $imgManager = ImageManager::getSharedInstance();
        $finalPath = __DIR__ . "/../public/content/user_data/user_img/" . $finalFileName;
        $finalThumbnailPath = __DIR__ . "/../public/content/user_data/user_img/" . $finalThumbnailName;

        if (!$imgManager->resizeToDimension(
            100,
            $picture["tmp_name"],
            $extension,
            $finalPath
        )) {
            return false;
        }
        if (!$imgManager->makeThumbnail($finalPath, $finalThumbnailPath, 400)) {
            return false;
        }

        if (!unlink($picture["tmp_name"]))
            return false;

        return $finalFileName;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totp_secret;
    }

    public function setTotpSecret(?string $totp_secret)
    {
        $this->totp_secret = $totp_secret;
    }

    public static function jsonDeserialize($json)
    {
        $classInstance = new User();
        if (is_string($json))
            $json = json_decode($json);
        foreach ($json as $key => $value)
            $classInstance->{$key} = $value;
        return $classInstance;
    }

    public function jsonSerialize()
    {
        return [
            'id' => $this->getId(),
            'firstname' => $this->getFirstname(),
            'surname' => $this->getSurname(),
            'picture' => $this->getPicture(),
            'pseudo' => $this->getPseudo()
        ];
    }
}
