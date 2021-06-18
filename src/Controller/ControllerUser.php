<?php

namespace User\Controller;

require __DIR__."/../../../../../bootstrap.php";
require __DIR__.'/../../../../autoload.php';

use User\Entity\User;
use User\Entity\Regular;
use User\Entity\ClassroomUser;
use User\Entity\Teacher;
use Classroom\Entity\Classroom;
use Classroom\Entity\ClassroomLinkUser;
/**
 * @ THOMAS MODIF 1 line just below
 */
use DAO\RegularDAO;
use Classroom\Entity\ActivityLinkUser;
use Classroom\Entity\ActivityLinkClassroom;
use Utils\ConnectionManager;
use Database\DataBaseManager;
use Utils\Mailer;
use Dotenv\Dotenv;
use Aiken\i18next\i18next;
use Exception;



class ControllerUser extends Controller
{
    public $URL = "";
    public function __construct($entityManager, $user, $url =null)
    {
        // Load env variables 
        $dotenv = Dotenv::createImmutable(__DIR__."/../../../../../");
        $dotenv->load();
        $this->URL = isset($url) ? $url : $_ENV['VS_HOST'];
        parent::__construct($entityManager, $user);
        $this->actions = array(
            'get_all' => function () {
                return $this->entityManager->getRepository('User\Entity\User')
                    ->findAll();
            },
            'generate_classroom_user_password' => function ($data) {
                $user = $this->entityManager->getRepository('User\Entity\User')
                    ->find($data['id']);
                $password = passwordGenerator();
                $user->setPassword($password);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                $pseudo = $user->getPseudo();
                return ['mdp' => $password, 'pseudo' => $pseudo];
            },
            'get_student_password' => function(){

                // accept only POST request
                if($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error"=> "Method not Allowed"];
                
                // bind incoming id
                $id = isset($_POST['id']) ?  intval($_POST['id']) : null;

                // get the current user from user_regulars table
                $userIsRegular = $this->entityManager->getRepository(Regular::class)->find($id);
                if($userIsRegular){
                    // return an error if a record was found as only students are allowed here 
                    return ["errorType"=>"RegularUserNotAllowed"];
                } 

                // get the student data from users table
                $student = $this->entityManager->getRepository(User::class)->find($id);
                if(!$student){
                    // return an error if no record found as only existing students are allowed here
                    return ["errorType"=>"UserNotExists"];
                }
                else 
                {
                    // the user exists in db
                    if(strlen($student->getPassword()) > 4){
                        // return an error if its password is greater than 4 characters long
                        return ["errorType"=>"PasswordLengthInvalid"];
                    } 

                    return array(
                        "id"=> $student->getId(),
                        "password" => $student->getPassword()
                    ); 
                } 
            },
            'reset_student_password' => function(){

                // accept only POST request
                if($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error"=> "Method not Allowed"];
                
                // bind incoming id
                $id = isset($_POST['id']) ? intval($_POST['id']) : null;

                // get the current user from user_regulars table
                $userIsRegular = $this->entityManager->getRepository(Regular::class)->find($id);
                if($userIsRegular){
                    // a record was found,return an error as only students are allowed here 
                    return ["errorType"=>"RegularUserNotAllowed"];
                } 

                // get the student data from users table
                $student = $this->entityManager->getRepository(User::class)->find($id);
                if(!$student){
                    // no record found,return an error as only existing students are allowed here 
                    return ["errorType"=>"UserNotExists"];
                } 
                else 
                {
                    // the student was found
                    // set its current password as $oldPassword to check against the $newPassword  
                    $oldPassword = $student->getPassword();

                    // generate a new password
                    $newPassword = $this->generateUpdatedPassword($student->getPseudo(),$oldPassword);

                    // update student password and save it in db
                    $student->setPassword($newPassword);
                    $this->entityManager->flush();

                    return array(
                        "id"=> $student->getId(),
                        "oldPassword" => $oldPassword,
                        "newPassword" => $student->getPassword()
                    ); 
                } 
                
            },
            'disconnect' => function () {

                try {
                    $manager = ConnectionManager::getSharedInstance();
                    $user = $manager->checkConnected();
                    if (!$user) {
                        return false;
                    } else {
                        $res = $manager->deleteToken($_SESSION["id"], $_SESSION["token"]);
                        if ($res) {
                            if (isset($_GET["url"]) && $_GET["url"] != '') {
                                header('location:' . $this->URL . '/' . $_GET["url"]);
                            } else {
                                header('location:' . $this->URL . '/index.php');
                            }
                        } else {
                            header('location:' . $this->URL . '/index.php');
                        }
                    }
                } catch (\Exception $e) {
                    return false;
                }
            },
            'change_pseudo_classroom_user' => function ($data) {
                $user = $this->entityManager->getRepository('User\Entity\User')
                    ->find($data['id']);
                $user->setPseudo($data['pseudo']);
                $this->entityManager->persist($user);
                $this->entityManager->flush();
                return true;
            },
            'delete' => function ($data) {
                $user = $this->entityManager->getRepository('User\Entity\User')
                    ->find($data['id']);
                $regular = $this->entityManager->getRepository('User\Entity\Regular')
                    ->findOneBy(array('user' => $data['id']));
                /* $teacher = $this->entityManager->getRepository('User\Entity\Teacher')
                    ->findOneBy(array('user' => $data['id'])); */
                $classroomUser = $this->entityManager->getRepository('User\Entity\ClassroomUser')
                    ->findOneBy(array('id' => $data['id']));
                $pseudo = $user->getPseudo();
                $this->entityManager->remove($user);
                if ($regular) {
                    $this->entityManager->remove($regular);
                }
                /* if ($teacher) {
                    $this->entityManager->remove($teacher);
                } */
                if ($classroomUser) {
                    $this->entityManager->remove($classroomUser);
                }
                $this->entityManager->flush();
                return [
                    'pseudo' => $pseudo
                ];
            },
            'get_one_by_pseudo_and_password' => function ($data) {
                $user = $this->entityManager->getRepository('User\Entity\User')
                    ->findBy(array("pseudo" => $data['pseudo']));
                foreach ($user as $u) {
                    if ($data['password'] == $u->getPassword()) {
                        $trueUser = $u;
                        break;
                    }
                }
                if (isset($trueUser)) {
                    $classroom = $this->entityManager->getRepository('Classroom\Entity\Classroom')
                        ->findOneBy(array("link" => $data['classroomLink']));
                    $isThere = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                        ->findOneBy(array("user" => $trueUser, "classroom" => $classroom));
                    if ($isThere) {
                        $_SESSION["id"] = $trueUser->getId();
                        return true;
                    }
                }
                return false;
            },
            'get_one' => function ($data) {
                $regular = $this->entityManager->getRepository('User\Entity\Regular')
                    ->find($data);
                if ($regular) {
                    $teacher = $this->entityManager->getRepository('User\Entity\Teacher')
                        ->find($data);
                    if ($teacher) {
                        return $teacher;
                    } else {
                        return $regular;
                    }
                } else {
                    $classroomUser = $this->entityManager->getRepository('User\Entity\ClassroomUser')
                        ->find($data);
                    return $classroomUser;
                }
            },
            'garSystem' => function () {
                try {
                    $_SESSION['UAI'] = $_GET['uai'];
                    $_SESSION['DIV'] = json_decode(urldecode($_GET['div']));
                    if (isset($_GET['pmel']) && $_GET['pmel'] != '') {
                        $isTeacher = true;
                    } else {
                        $isTeacher = false;
                    } // en fonction des infos sso
                    //check if the user is in the database. If not, create a new User

                    $garUser = $this->entityManager->getRepository('User\Entity\ClassroomUser')
                        ->findBy(array("garId" => $_GET['ido']));
                    if (!$garUser) {
                        $user = new User();
                        $user->setFirstName($_GET['pre']);
                        $user->setSurname($_GET['nom']);
                        $user->setPseudo($_GET['nom'] . " " . $_GET['pre']);
                        $password = passwordGenerator();
                        $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
                        $lastQuestion = $this->entityManager->getRepository('User\Entity\User')->findOneBy([], ['id' => 'desc']);
                        $user->setId($lastQuestion->getId() + 1);
                        $this->entityManager->persist($user);

                        $classroomUser = new ClassroomUser($user);
                        $classroomUser->setGarId($_GET['ido']);
                        $classroomUser->setSchoolId($_GET['uai']);
                        if ($isTeacher) {
                            $classroomUser->setIsTeacher(true);
                            $classroomUser->setMailTeacher($_GET['pmel'] . passwordGenerator());
                            $regular = new Regular($user, $_GET['pmel'] . passwordGenerator());
                            $this->entityManager->persist($regular);

                            /*  $subject = "Votre création de compte Vittascience (test, en prod envoi à l'adresse " . $_GET['pmel'];
                        $body =  "<h4 style=\"font-family:'Open Sans'; margin-bottom:0; color:#27b88e; font-size:28px;\">Bonjour " . $user->getFirstname() . "</h4>";
                        $body .= "<p style=\" font-family:'Open Sans'; \">Vous vous êtes connecté à l'application Vittascience via le GAR.</p>";
                        $body .= "<p style=\" font-family:'Open Sans'; \">Si jamais vous souhaitez vous connecter sans passer par le GAR, voici votre mot de passe provisoire :<bold>" . $password . "</bold>.";

                        Mailer::sendMail("support@vittascience.com", $subject, $body, $body); */
                        } else {
                            $classroomUser->setIsTeacher(false);
                            $classroomUser->setMailTeacher(NULL);

                            $classes = $this->entityManager->getRepository('Classroom\Entity\Classroom')->findBy(array('groupe' => $_SESSION['DIV'], 'school' => $_SESSION['UAI']));
                            foreach ($classes as $c) {
                                $linkToClass = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')->findBy(array('user' => $user));

                                if (!$linkToClass) {
                                    $linkteacherToGroup = new ClassroomLinkUser($user, $c);
                                    $linkteacherToGroup->setRights(0);
                                    $this->entityManager->persist($linkteacherToGroup);
                                }
                            }
                        }
                        $this->entityManager->persist($classroomUser);
                        $this->entityManager->flush();
                        $_SESSION['id'] = $user->getId();
                        $_SESSION['pin'] = $password;

                        if ($user) {
                            header('location:' . $this->URL . '/classroom/home.php');
                        } else {
                            header('location:' . $this->URL . '/classroom/login.php');
                        }
                    }
                    $classes = $this->entityManager->getRepository('Classroom\Entity\Classroom')->findBy(array('groupe' => $_SESSION['DIV'], 'school' => $_SESSION['UAI']));
                    foreach ($classes as $c) {
                        $linkToClass = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')->findOneBy(array('user' => $garUser[0]->getId(), 'classroom' => $c));
                        var_dump($linkToClass);
                        if (!$linkToClass || $linkToClass == NULL) {

                            $linkteacherToGroup = new ClassroomLinkUser($garUser[0]->getId(), $c);
                            $linkteacherToGroup->setRights(0);
                            $this->entityManager->persist($linkteacherToGroup);
                        }
                    }
                    $this->entityManager->flush();
                    $_SESSION['id'] = $garUser[0]->getId()->getId();
                    header('location:' . $this->URL . '/classroom/home.php');
                } catch (Exception $e) {
                    var_dump($e);
                }
            },
            'linkSystem' => function ($data) {
                /**
                 * Limiting learner number @THOMAS MODIF
                 * Added Admin check to allow them an unlimited number of new student @NASER MODIF
                 */
                
                 // retrieve the classroom by its link
                $classroom = $this->entityManager->getRepository('Classroom\Entity\Classroom')
                ->findOneBy(array("link" => $data['classroomLink']));

                // if the current classroom is Blocked by the teacher 
                if($classroom->getIsBlocked() === true ){
                    // disallow students to join
                    return [
                        "isUsersAdded"=>false, 
                        "errorType"=> "classroomBlocked"
                    ];
                }

                $classroomTeacher = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                ->findBy(array("classroom" => $classroom->getId()));

                $currentUserId = $classroomTeacher[0]->getUser()->getId();
                
                // get the statuses for the current classroom owner
                $isPremium = RegularDAO::getSharedInstance()->isTester($currentUserId);
                $isAdmin = RegularDAO::getSharedInstance()->isAdmin($currentUserId);

                $classrooms = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                    ->findBy(array("user" => $currentUserId));

                // initiate the $nbApprenants counter and loop through each classrooms
                $nbApprenants = 0;
                foreach ($classrooms as $c) {
                    $students = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                        ->getAllStudentsInClassroom($c->getClassroom()->getId(), 0);
                    
                    // add the current classroom users number and increase the total
                    $nbApprenants += count($students);
                }

                $learnerNumberCheck = [
                    "idUser"=>$currentUserId, 
                    "isPremium"=>$isPremium, 
                    "isAdmin"=> $isAdmin,
                    "learnerNumber"=>$nbApprenants
                ];

                 // set the $isAllowed () flag to true  if the current user is admin or premium
                 $isAllowed = $learnerNumberCheck["isAdmin"] || $learnerNumberCheck["isPremium"];

                // the current classroom owner is not allowed to have an unlimited number of students
                if(!$isAllowed){
                    
                    // computer the total number of students registered +1 and return an error if > 50
                    $addedLearnerNumber = 1;
                    $totalLearnerCount = $learnerNumberCheck["learnerNumber"] + $addedLearnerNumber;
                    if($totalLearnerCount > 50){
                        return ["isUsersAdded"=>false, "currentLearnerCount"=>$learnerNumberCheck["learnerNumber"], "addedLearnerNumber"=>$addedLearnerNumber];
                    }
                }
                /**
                 * End of learner number limiting
                 */

                // check if the submitted pseudo is vittademo
                if( strtolower($data['pseudo']) == "vittademo"){
                    return [
                        "isUsersAdded"=>false, 
                        "errorType"=> "reservedNickname",
                        "currentNickname"=> "vittademo"
                    ];
                }
                
                $pseudoUsed = $this->entityManager->getRepository('User\Entity\User')->findBy(array('pseudo' => $data['pseudo']));
                foreach ($pseudoUsed as $p) {
                    $pseudoUsedInClassroom = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')->findOneBy(array('user' => $p));
                    if ($pseudoUsedInClassroom) {
                        return false;
                    }
                }


                // related to users table in db
                $user = new User();
                $user->setFirstName("élève");
                $user->setSurname("modèl");
                $user->setPseudo($data['pseudo']);
                $password = passwordGenerator();
                $user->setPassword($password);
                $lastQuestion = $this->entityManager->getRepository('User\Entity\User')->findOneBy([], ['id' => 'desc']);
                $user->setId($lastQuestion->getId() + 1);
                // persist in doctrine memory and save it with the flush()
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // related to a new entry in user_classroom_users table in db
                $classroomUser = new ClassroomUser($user);
                $classroomUser->setGarId(null);
                $classroomUser->setSchoolId(null);
                $classroomUser->setIsTeacher(false);
                $classroomUser->setMailTeacher(NULL);
                // persist in doctrine memory and save it in db later
                $this->entityManager->persist($classroomUser);

                $classroom = $this->entityManager->getRepository('Classroom\Entity\Classroom')
                    ->findOneBy(array("link" => $data['classroomLink']));
                $linkteacherToGroup = new ClassroomLinkUser($user, $classroom);
                $linkteacherToGroup->setRights(0);
                $this->entityManager->persist($linkteacherToGroup);

                $activitiesLinkClassroom = $this->entityManager->getRepository('Classroom\Entity\ActivityLinkClassroom')
                    ->findBy(array("classroom" => $classroom));

                //add all activities linked with the classroom to the learner
                foreach ($activitiesLinkClassroom as $a) {
                    $activityLinkUser = new ActivityLinkUser(
                        $a->getActivity(), 
                        $user, 
                        $a->getDateBegin(),  
                        $a->getDateEnd(), 
                        $a->getEvaluation(), 
                        $a->getAutocorrection(), 
                        $a->getIntroduction(),
                        $a->getReference()
                    );
                    $this->entityManager->persist($activityLinkUser);
                }

                $this->entityManager->flush();
                $user->classroomUser = $classroomUser;
                $user->pin = $password;
                $_SESSION["id"] = $user->getId();
                $_SESSION["pin"] = $password;
                return ["isUsersAdded"=>true, "user"=>$user];
            },
            'login' => function ($data) {

                if (ConnectionManager::getSharedInstance()->checkConnected()) {
                    return ["success" => true];
                }
                if (!empty($data["mail"]) && !empty($data["password"])) {
                    $credentials = ConnectionManager::getSharedInstance()->checkLogin($data["mail"], $data["password"]);
                    if ($credentials !== false) {
                        $_SESSION["id"] = $credentials[0];
                        $_SESSION["token"] = $credentials[1];
                        return ["success" => true];
                    } else {
                        return ["success" => false];
                    }
                    return ["success" => false];
                }
            },
            'register' => function(){

                // return error if the request is not a POST request
                if($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error"=> "Method not Allowed"];

                // bind incoming data to the value provided or null
                $firstname = isset($_POST['firstname']) ? strip_tags(htmlspecialchars($_POST['firstname'])) : null;
                $surname = isset($_POST['surname']) ? strip_tags(htmlspecialchars($_POST['surname'])) : null;
                $pseudo = isset($_POST['pseudo']) ? strip_tags(htmlspecialchars($_POST['pseudo'])) : null;
                $email = $_POST['email'] ?? null;
                $password = $_POST['password'] ?? null;
                $password_confirm = $_POST['password_confirm'] ?? null;

                // At least 8 characters including 1 Uppercase, 1lowercase, 1 digit, and 1 special character
                $regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";

                // create empty $errors and fill it with errors if any
                $errors = [];
                if(empty($firstname)) $errors['firstnameMissing'] = true;
                if(empty($surname)) $errors['surnameMissing'] = true;
                if(empty($pseudo)) $errors['pseudoMissing'] = true;
                if(empty($email)) $errors['emailMissing'] = true;
                elseif(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors['emailInvalid'] = true;
                if(empty($password)) $errors['passwordMissing'] = true;
                if(empty($password_confirm)) $errors['passwordConfirmMissing'] = true;
                elseif($password !== $password_confirm) $errors['passwordsMismatch'] = true;
                elseif(!preg_match($regex,$password,$matches)){
                    $errors['passwordInvalid'] = true;
                }
                
                // check if the email is already listed in db
                $emailAlreadyExists = $this->entityManager
                                        ->getRepository('User\Entity\Regular')
                                        ->findOneBy(array('email'=> $email));
                
                // the email already exists in db,set emailExists error 
                if($emailAlreadyExists) $errors['emailExists'] = true;
                
                // some errors were found, return them to the user
                if(!empty($errors)){
                    return array(
                        'isUserAdded'=>false,
                        "errors" => $errors
                    );                    
                }
                
                // no errors found, we can process the data
                // hash the password
                $passwordHash = password_hash($password,PASSWORD_BCRYPT);

                // create user and persists it in memory
                $user = new User;
                $user->setFirstname($firstname);
                $user->setSurname($surname);
                $user->setPseudo($pseudo);
                $user->setPassword($passwordHash);
                $user->setInsertDate( new \DateTime());
                $this->entityManager->persist($user);
                $this->entityManager->flush();
               
                // retrieve the lastInsertId to use for the next query 
                // this value is only available after a flush()
                $user->setId( $user->getId());
                
                // create record in user_regulars table and persists it in memory
                $regularUser = new Regular($user,$email);
                $regularUser->setActive(true);
                $this->entityManager->persist($regularUser);
                $this->entityManager->flush();
                
                return array(
                    'isUserAdded'=>true,
                    "id" => $user->getId()
                );    
            },
            'update_user_infos' => function(){
              
                // return error if the request is not a POST request
                if($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error"=> "Method not Allowed"];

                // bind incoming id
                $id = intval($_POST['id']);

                // retrieve user by its id
                $userToUpdate = $this->entityManager
                                    ->getRepository(User::class)
                                    ->find($id);

                $regularUserToUpdate = $this->entityManager
                                        ->getRepository(Regular::class)
                                        ->find($id);

                // no userFound
                if(!$userToUpdate) {
                    return array(
                        'isUserUpdated'=>false,
                        "errorType" => "unknownUser"
                    );    
                } 

                // store old email for future check and set trigger for sending email or not
                $tmpOldEmail = $regularUserToUpdate->getEmail();
                $emailUpdatedRequested = false;

                // user found in db, prepare data
                $firstname = isset($_POST['firstname']) 
                                ? htmlspecialchars(strip_tags(trim($_POST['firstname'])))
                                : $userToUpdate->getFirstname();

                $surname = isset($_POST['surname']) 
                                ? htmlspecialchars(strip_tags(trim($_POST['surname']))) 
                                : $userToUpdate->getSurname();

                $pseudo = isset($_POST['pseudo']) 
                                ? htmlspecialchars(strip_tags(trim($_POST['pseudo']))) 
                                : $userToUpdate->getPseudo();

                $email = isset($_POST['email'])
                                ? strip_tags(trim($_POST['email']))
                                : $regularUserToUpdate->getEmail();

                $password = isset($_POST['password']) ? strip_tags(trim($_POST['password'])) : null;

                
                // create empty $errors array and fill it with errors if any and $emailSend if necessary
                $errors = [];
                $emailSent = null;
                if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $errors['emailInvalid'] = true;
                if(!empty($password)){
                    // At least 8 characters including 1 Uppercase, 1 lowercase, 1 digit, and 1 special character
                    $regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";
                    if(!preg_match($regex,$password,$matches)){
                        $errors['passwordInvalid'] = true;
                    }
                } 
                if($email !== $tmpOldEmail){
                   
                     // check if the email is already listed in db
                    $emailAlreadyExists = $this->entityManager
                                            ->getRepository('User\Entity\Regular')
                                            ->findOneBy(array('email'=> $email));
                    if($emailAlreadyExists) $errors['emailExists']=true;
                    else {
                        // set new_mail, confirm_token fields to be filled in user_regulars
                        $regularUserToUpdate->setNewMail($email);
                        $confirmationToken = time()."-".bin2hex($email);
                        $regularUserToUpdate->setConfirmToken($confirmationToken);

                        // set the flag to true for sending email later 
                        $emailUpdatedRequested = true;
                    }
                }
                
                // some errors have been found, return them to the user
                if(!empty($errors)){
                    return array(
                        'isUserUpdated'=>false,
                        "errors" => $errors
                    );    
                }

                // no errors, update the fields value 
                if(!empty($firstname)) $userToUpdate->setFirstname($firstname);
                if(!empty($surname)) $userToUpdate->setSurname($surname);
                if(!empty($pseudo)) $userToUpdate->setPseudo($pseudo);
                $userToUpdate->setUpdateDate(new \DateTime());
                if(!empty($password))
                {
                    // the user requested a password change, so we hash the password
                    $passwordHash = password_hash($password,PASSWORD_BCRYPT);
                    $userToUpdate->setPassword($passwordHash);
                }
                
                // save data in both tables users and user_regulars
                $this->entityManager->flush();
                
                // the user requested its email to changed
                if($emailUpdatedRequested == true)
                {
                    /////////////////////////////////////
                    // PREPARE EMAIL TO BE SENT
                    // received lang param
                    $userLang = isset($_POST['lang']) 
                                ? htmlspecialchars(strip_tags(trim($_POST['lang'])))  
                                : 'fr';

                    // set the email confirmation link and the email template to be used
                    $emailConfirmationLink = "{$this->URL}/classroom/confirm_email_update.php?token=$confirmationToken";
                    $emailTtemplateBody = $userLang."_confirm_email_update";

                    // init i18next instance
                    i18next::init($userLang,__DIR__."/../../../../../classroom/assets/lang/__lng__/ns.json");
                    $emailSubject = i18next::getTranslation('classroom.updateUserInfos.emailUpdateConfirmation.emailSubject');
                    $bodyTitle = i18next::getTranslation('classroom.updateUserInfos.emailUpdateConfirmation.bodyTitle');
                    $textBeforeLink = i18next::getTranslation('classroom.updateUserInfos.emailUpdateConfirmation.textBeforeLink');

                    $body = "
                        <a href='$emailConfirmationLink' 
                            style='text-decoration: none;
                            padding: 10px;
                            background: #27b88e;
                            color: white;
                            margin: 1rem auto;
                            width: 50%;
                            display: block;'
                        >
                            $bodyTitle
                        </a>
                        <br>
                        <br>
                        <p>$textBeforeLink $emailConfirmationLink
                    ";
                    
                    // send the email
                    $emailSent = Mailer::sendMail($email, $emailSubject, $body, strip_tags($body),$emailTtemplateBody); 

                    /////////////////////////////////////
                }
                return array(
                    'isUserUpdated'=>true,
                    "user" => array(
                        "id"=> $userToUpdate->getId(),
                        "emailSent" =>  $emailSent
                    )
                );    
            },
            'disconnect' => function ($data) {

                try {
                    $manager = ConnectionManager::getSharedInstance();
                    $user = $manager->checkConnected();
                    if (!$user) {
                        return false;
                    } else {
                        $res = $manager->deleteToken($_SESSION["id"], $_SESSION["token"]);
                        if ($res) {
                            if (isset($data["url"]) && $data["url"] != '') {
                                return $data["url"];
                            } else {
                                return "/index.php";
                            }
                        } else {
                            return false;
                        }
                    }
                } catch (\Exception $e) {
                    return false;
                }
            },
            'get_schools' => function () {

                $dbGrades = [
                    1 => "Ecole",
                    2 => "Collège",
                    3 => "Lycée",
                ];
                if (!empty($_GET["phrase"]) && !empty($_GET["grade"])) {
                    $words = explode(" ", $_GET["phrase"], 10);
                    $array = [];
                    foreach ($words as $word) {
                        $sql = "SELECT * FROM data_schools WHERE (school_name LIKE ? OR school_address_3 LIKE ?)";
                        $params = ["%" . $word . "%", "%" . $word . "%"];
                        if (array_key_exists($_GET["grade"], $dbGrades)) {
                            $grade = $dbGrades[$_GET["grade"]];
                            $sql .= " AND (school_type LIKE ?) ";
                            $params[] = "%" . $grade . "%";
                        } else {
                            return [];
                        }
                        $sql .= " LIMIT 100";
                        $schools = DatabaseManager::getSharedInstance()
                            ->getAll($sql, $params);

                        foreach ($schools as $school) {
                            $array[] = $school["school_name"] . " - " . $school["school_address_3"];
                        }
                    }

                    $array = array_unique($array);

                    $tmpArray = [];
                    foreach ($array as $value)
                        $tmpArray[] = $value;
                    $finalArray = [];
                    $order = [];
                    foreach ($tmpArray as $result) {
                        $matches = 0;
                        foreach ($words as $word) {
                            if (!empty($word)) {
                                if (preg_match("/" . strtolower($word) . "/", strtolower($result))) {
                                    $matches++;
                                }
                            }
                        }
                        $order[] = $matches;
                    }

                    arsort($order, SORT_NUMERIC);

                    foreach ($order as $key => $index) {
                        $finalArray[] = ["name" => $tmpArray[$key]];
                    }

                    return $finalArray;
                }
            },
            'signup' => function ($data) {

                function setLanguage($lng)
                {
                    setcookie("lng", $lng, time() + 2678400, '/'); //2678400 = 31 days
                    return $lng;
                }

                $lang = isset($_COOKIE['lng']) ? $_COOKIE['lng'] : setLanguage('en');
                $allowedLang = ['en', 'fr'];
                if (!in_array($lang, $allowedLang))
                    $lang = setLanguage('en');
                if (
                    !empty($data["surname"])
                    && !empty($data["firstname"])
                    && !empty($data["email"])
                    && !empty($data["password"])
                    && !empty($data["bio"])
                    && isset($data["newsletter"])
                    && isset($data["private"])
                    && isset($data["contact"])
                    && isset($data["mailMessages"])
                ) {
                    $error = false;
                    $errorMessages = [];

                    $surname = trim(htmlspecialchars($data["surname"]));
                    $firstname = trim(htmlspecialchars($data["firstname"]));
                    $email = trim(htmlspecialchars(strtolower($data["email"])));
                    $password = $data["password"];
                    $bio = trim(htmlspecialchars($data["bio"]));
                    $newsletter = intval($data["newsletter"]);
                    $private = intval($data["private"]);
                    $mailMessages = intval($data["mailMessages"]);
                    $contact = intval($data["contact"]);


                    $hasPhone = false;
                    $hasPicture = false;
                    $isTeacher = false;
                    $user = new User();
                    $user->setFirstName($firstname);
                    $user->setSurname($surname);
                    $user->setPseudo($surname . " " . $firstname);
                    $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
                    $lastQuestion = $this->entityManager->getRepository('User\Entity\User')->findOneBy([], ['id' => 'desc']);
                    $user->setId($lastQuestion->getId() + 1);
                    $this->entityManager->persist($user);

                    $regular = new Regular($user, $email);
                    $regular->setBio($bio);
                    $regular->setNewsletter($newsletter);
                    $regular->setPrivateFlag($private);
                    $regular->setMailMessages($mailMessages);
                    $regular->setContactFlag($contact);
                    $this->entityManager->persist($regular);

                    if (
                        !empty($data["school"])
                        && isset($data["subject"])
                        && isset($data["grade"])
                    ) {
                        $school = trim(htmlspecialchars($data["school"]));

                        $subject = intval(htmlspecialchars($data["subject"]));
                        $grade = intval(htmlspecialchars($data["grade"]));

                        $teacher = new Teacher($user,  $subject, $school, $grade);
                        $this->entityManager->persist($teacher);
                    }

                    if (!empty($_FILES["picture"])) {
                        $hasPicture = true;
                        if (!PicturesUtils::checkPicture($_FILES["picture"], 0.1, 10, 10)) {
                            $error = true;
                            $errorMessages[] = 'La photo de profil est invalide.';
                        }
                    }

                    /*  if ($error) {
                        errorFunc($errorMessages);
                    } */

                    if ($hasPicture) {
                        $finalFileName = $user->processPicture($_FILES["picture"]);
                        if (!$finalFileName)
                            errorFunc(['pictureFormat']);
                    }
                    try {
                        $confirmToken = bin2hex(random_bytes(32));
                    } catch (Exception $e) {
                        errorFunc(['passwordHash']);
                    }

                    $mailBody =
                        "<h4 style=\"font-family:'Open Sans'; margin-bottom:0; color:#27b88e; font-size:28px;\">Bonjour " . $firstname . "</h4>" .
                        "<a style=\" font-family:'Open Sans'; \">Veuillez cliquer sur ce lien pour confirmer votre inscription à Vittascience: <a href='" . $_ENV['VS_HOST'] . "/services/get/confirmSignup.php?token=" . $confirmToken . "&email=" . $email . "'>Confirmer mon mail</a></p>";

                    /* if (!Mailer::sendMail($email, "Confirmez votre inscription chez Vittascience", $mailBody, strip_tags($mailBody))) {
                        errorFunc(['mailSend']);
                    } */
                    $this->entityManager->flush();
                    return ["success" => true];
                } else {
                    return ["success" => false];
                }

                function errorFunc($errorMessages)
                {
                    return ["success" => false, "errors" => $errorMessages];
                }
            }
        );
    }
    /**
     * generateUpdatedPassword
     * 
     * @param  string $pseudo
     * @param  string $oldPassword
     * @return string generate a new password different for the old one
     *  and make sure there are no matching record in db
     */
    private function generateUpdatedPassword($pseudo,$oldPassword){
        
        // generate the new password and check if a record exists in db with these credentials
        do{
            $newPassword = passwordGenerator(); 
            $duplicateUserCredentialsFound = $this->entityManager
                                                ->getRepository(User::class)
                                                ->findOneBy(array(
                                                    'pseudo'=>$pseudo,
                                                    'password'=> $newPassword
                                                ));
        }
        // continue as long as the passwords match and a record exists in db
        while(($oldPassword === $newPassword) && $duplicateUserCredentialsFound !== null) ;
       
        return $newPassword;
    }
}
function passwordGenerator()
{
    $password = '';
    for ($i = 0; $i < 4; $i++) {
        $password .= rand(0, 9);
    }
    return $password;
}
