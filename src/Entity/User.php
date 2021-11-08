<?php

namespace User\Entity;

use Doctrine\ORM\Mapping as ORM;
use Utils\Exceptions\EntityDataIntegrityException;
use Utils\ImageManager;

/**
 * @ORM\Entity(repositoryClass="User\Repository\UserRepository")
 * @ORM\Table(name="users")
 */

class User
{
    // REG_FIRSTNAME: Only letters and digits and length of string is between 1 and 100
    const REG_NAME = "/^[a-zA-ZáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ]{1}[\w\sáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ'&@\-_.()]{0,98}[\wáàâäãåçéèêëíìîïñóòôöõúùûüýÿæœÁÀÂÄÃÅÇÉÈÊËÍÌÎÏÑÓÒÔÖÕÚÙÛÜÝŸÆŒ)]{0,1}$/";
    const REG_UNIQID_13 = "/^[a-f0-9]{13}$/";

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(name="id",type="integer")
     */
    private $id;

    /**
     * @ORM\OneToMany(targetEntity="Learn\Entity\Favorite", mappedBy="user")
     */
    private $favorite;

    /** 
     * @ORM\Column(name="firstname", type="string", length=100, nullable=false)
     * @var string
     */
    private $firstname;
    /** 
     * @ORM\Column(name="pseudo", type="string", length=100, nullable=false)
     * @var string
     */
    private $pseudo;
    /**
     * @ORM\Column(name="surname", type="string", length=100, nullable=false)
     * @var string
     */
    private $surname;
    /**
     * @ORM\Column(name="password", type="string", length=255, nullable=false)
     * @var string
     */
    private $password;
    /**
     * @ORM\Column(name="insert_date", type="datetime", columnDefinition="TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
     * @var \DateTime
     */
    private $insertDate;
    /**
     * @ORM\Column(name="update_date", type="datetime", columnDefinition="TIMESTAMP DEFAULT CURRENT_TIMESTAMP")
     * @var \DateTime
     */
    private $updateDate;
    /**
     * @ORM\Column(name="picture", type="string", length=250, nullable=true)
     * @var string
     */
    private $picture = NULL;
    public function __construct()
    {
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        if (is_int($id) && $id > 0) {
            $this->id = $id;
        } else
            throw new EntityDataIntegrityException("id needs to be integer and positive");
    }

    /**
     * @return string
     */
    public function getFirstname()
    {
        return $this->firstname;
    }

    /**
     * @param string $firstname
     */
    public function setFirstname($firstname)
    {
        if (preg_match(self::REG_NAME, $firstname)) {
            $this->firstname = $firstname;
        } else
            throw new EntityDataIntegrityException("firstname needs to be string and have between 1 and 100 characters");
    }

    /**
     * @return string
     */
    public function getPseudo()
    {
        return $this->pseudo;
    }

    /**
     * @param string $pseudo
     */
    public function setPseudo($pseudo)
    {

        if (preg_match(self::REG_NAME, $pseudo)) {
            $this->pseudo = $pseudo;
        } else
            throw new EntityDataIntegrityException("pseudo needs to be string and have between 1 and 100 characters");
    }

    /**
     * @return string
     */
    public function getSurname()
    {
        return $this->surname;
    }

    /**
     * @param string $surname
     */
    public function setSurname($surname)
    {

        if (preg_match(self::REG_NAME, $surname)) {
            $this->surname = $surname;
        } else {
            throw new EntityDataIntegrityException("surname needs to be string and have between 1 and 100 characters");
        }
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        if (is_string($password)) {
            $this->password = $password;
        } else {
            throw new EntityDataIntegrityException("password needs to be string");
        }
    }

    /**
     * @return \DateTime
     */
    public function getInsertDate()
    {
        return $this->insertDate;
    }

    /**
     * @param \DateTime $insertDate
     */
    public function setInsertDate($insertDate)
    {
        if (isset($insertDate)) {
            if ($insertDate instanceof \DateTime)
                $this->insertDate = $insertDate;
            else
                throw new EntityDataIntegrityException("insetrDate needs to be DateTime");
        } else {
            throw new EntityDataIntegrityException("insartDate should not be null");
        }
    }

    /**
     * @return \DateTime
     */
    public function getUpdateDate()
    {
        return $this->updateDate;
    }

    /**
     * @param \DateTime $updateDate
     */
    public function setUpdateDate($updateDate)
    {
        if ($updateDate instanceof \DateTime || $updateDate == null) {
            $this->updateDate = $updateDate;
        } else {
            throw new EntityDataIntegrityException("updateDate needs to be DateTime or null");
        }
    }
    /**
     * @return string
     */
    public function getPicture()
    {
        return $this->picture;
    }

    /**
     * @param string $picture
     */
    public function setPicture($picture)
    {
        if (is_string($picture) || $picture == null) {
            $this->picture = $picture;
        } else {
            throw new EntityDataIntegrityException("picture needs to be string");
        }
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
