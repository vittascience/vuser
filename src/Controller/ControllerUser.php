<?php

namespace User\Controller;

require __DIR__ . "/../../../../../bootstrap.php";
require __DIR__ . '/../../../../autoload.php';

use Exception;
use Utils\Mailer;
use Dotenv\Dotenv;
use DAO\RegularDAO;
use User\Entity\User;
use User\Entity\Regular;

/**
 * @ THOMAS MODIF 1 line just below
 */

use User\Entity\Teacher;
use Aiken\i18next\i18next;
use User\Entity\UserPremium;
use Utils\ConnectionManager;
use Database\DataBaseManager;
use User\Entity\ClassroomUser;
use Classroom\Entity\Classroom;
use Classroom\Entity\ActivityLinkUser;
use Classroom\Entity\Applications;
use Classroom\Entity\ClassroomLinkUser;
use Classroom\Entity\ActivityLinkClassroom;



class ControllerUser extends Controller
{
    public $URL = "";
    public function __construct($entityManager, $user, $url = null)
    {
        // Load env variables 
        $dotenv = Dotenv::createImmutable(__DIR__ . "/../../../../../");
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
            'get_student_password' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind incoming id
                $id = isset($_POST['id']) ?  intval($_POST['id']) : null;

                // get the current user from user_regulars table
                $userIsRegular = $this->entityManager->getRepository(Regular::class)->find($id);
                if ($userIsRegular) {
                    // return an error if a record was found as only students are allowed here 
                    return ["errorType" => "RegularUserNotAllowed"];
                }

                // get the student data from users table
                $student = $this->entityManager->getRepository(User::class)->find($id);
                if (!$student) {
                    // return an error if no record found as only existing students are allowed here
                    return ["errorType" => "UserNotExists"];
                } else {
                    // the user exists in db
                    if (strlen($student->getPassword()) > 4) {
                        // return an error if its password is greater than 4 characters long
                        return ["errorType" => "PasswordLengthInvalid"];
                    }

                    return array(
                        "id" => $student->getId(),
                        "password" => $student->getPassword()
                    );
                }
            },
            'reset_student_password' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind incoming id
                $id = isset($_POST['id']) ? intval($_POST['id']) : null;

                // get the current user from user_regulars table
                $userIsRegular = $this->entityManager->getRepository(Regular::class)->find($id);
                if ($userIsRegular) {
                    // a record was found,return an error as only students are allowed here 
                    return ["errorType" => "RegularUserNotAllowed"];
                }

                // get the student data from users table
                $student = $this->entityManager->getRepository(User::class)->find($id);
                if (!$student) {
                    // no record found,return an error as only existing students are allowed here 
                    return ["errorType" => "UserNotExists"];
                } else {
                    // the student was found
                    // set its current password as $oldPassword to check against the $newPassword  
                    $oldPassword = $student->getPassword();

                    // generate a new password
                    $newPassword = $this->generateUpdatedPassword($student->getPseudo(), $oldPassword);

                    // update student password and save it in db
                    $student->setPassword($newPassword);
                    $this->entityManager->flush();

                    return array(
                        "id" => $student->getId(),
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
                // Deleted with cascade
                /* $teacher = $this->entityManager->getRepository('User\Entity\Teacher')
                    ->findOneBy(array('user' => $data['id'])); */
                $classroomLinkUser = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                    ->findOneBy(array('user' => $data['id']));
                $classroomUser = $this->entityManager->getRepository('User\Entity\ClassroomUser')
                    ->findOneBy(array('id' => $data['id']));
                $pseudo = $user->getPseudo();


                // fix @Rémi 
                $this->entityManager->remove($classroomLinkUser);
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
            'get_gar_student_available_classrooms' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind and sanitize incoming data
                $uai = isset($_POST['uai']) ? htmlspecialchars(strip_tags(trim($_POST['uai']))) : '';
                $div = isset($_POST['div']) ? htmlspecialchars(strip_tags(trim($_POST['div']))) : '';

                // get the student classroom
                $classroomParts = explode('##', $div);
                $userClassroom = $classroomParts[0];

                // retrieve the user classrooms, there can be either 1 or several classrooms(1classroom is a set of classroom+groupe)
                $classrooms = $this->entityManager
                    ->getRepository(ClassroomLinkUser::class)
                    ->getStudentClassroomsAndRelatedTeacher($userClassroom, $uai);


                // initiate an empty array to fill with the student classrooms+groups
                $classroomsFound = [];
                foreach ($classrooms as $classroom) {

                    array_push($classroomsFound, array(
                        'id' => $classroom['id'],
                        'name' => $classroom['name'],
                        'groupe' => $classroom['groupe'],
                        'teacher' => $classroom['teacher'],
                        'rights' => $classroom['rights']
                    ));
                }

                return array(
                    'classrooms' => $classroomsFound
                );
            },
            'register_and_add_gar_student_to_classroom' => function () {
                // enable cors
                if (isset($_SERVER['HTTP_ORIGIN'])) header("Access-Control-Allow-Origin: *");

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                $incomingData = json_decode(file_get_contents('php://input'));

                // create empty object, bind and sanitize incoming data
                $sanitizedData = new \stdClass();
                $sanitizedData->pre = isset($incomingData->pre) ?  htmlspecialchars(strip_tags(trim($incomingData->pre))) : '';
                $sanitizedData->nom = isset($incomingData->nom) ? htmlspecialchars(strip_tags(trim($incomingData->nom))) : '';
                $sanitizedData->ido = isset($incomingData->ido) ? htmlspecialchars(strip_tags(trim($incomingData->ido))) : '';
                $sanitizedData->uai = isset($incomingData->uai) ? htmlspecialchars(strip_tags(trim($incomingData->uai))) : '';
                $sanitizedData->div = isset($incomingData->div)
                    ? explode('##', htmlspecialchars(strip_tags(trim($incomingData->div))))[0]
                    : '';
                $sanitizedData->classroomId = isset($incomingData->classroomId) ? intval($incomingData->classroomId) : 0;
                $sanitizedData->classroomGroup = isset($incomingData->classroomGroup) ? htmlspecialchars(strip_tags(trim($incomingData->classroomGroup))) : '';
                $sanitizedData->customIdo = "{$sanitizedData->ido}-{$sanitizedData->uai}-{$sanitizedData->div}-{$sanitizedData->classroomGroup}";

                // get the student
                $studentRegistered = $this->registerGarStudentIfNeeded($sanitizedData);

                if ($studentRegistered) {

                    // get the classroom
                    $classroom = $this->entityManager->getRepository(Classroom::class)->find($sanitizedData->classroomId);


                    // check if the current user is already registered as being part of this classroom
                    $studentExistsInClassroom = $this->entityManager
                        ->getRepository(ClassroomLinkUser::class)
                        ->getStudentAndClassroomByIds(
                            $studentRegistered->getId()->getId(),
                            $sanitizedData->classroomId
                        );

                    if (!$studentExistsInClassroom) {
                        // the student not found in the classroom, add it to classroom_users_link_classrooms table
                        $linkStudentToItsClassroom = new ClassroomLinkUser($studentRegistered->getId(), $classroom);
                        $linkStudentToItsClassroom->setRights(0);
                        $this->entityManager->persist($linkStudentToItsClassroom);
                        $this->entityManager->flush();
                    }

                    // prepare the student data to be saved in $_SESSION
                    $sessionUserId = intval($studentRegistered->getId()->getId());
                    $connectionToken = bin2hex(random_bytes(32));

                    // save the connection token in db
                    $res = DatabaseManager::getSharedInstance()
                        ->exec(
                            "INSERT INTO connection_tokens (token,user_ref) VALUES (?, ?)",
                            [$connectionToken, $sessionUserId]
                        );

                    // the token is saved in db, set session and redirect the student to its dashboard
                    $_SESSION["id"] = $sessionUserId;
                    $_SESSION['token'] =  $connectionToken;

                    $userAddedToClassroom = $res === true ? true : false;

                    return array('userAddedToClassroom' => $userAddedToClassroom);
                }
            },
            'save_gar_teacher' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind and sanitize incoming data
                $pre = isset($_POST['pre']) ? htmlspecialchars(strip_tags(trim($_POST['pre']))) : '';
                $nom = isset($_POST['nom']) ? htmlspecialchars(strip_tags(trim($_POST['nom']))) : '';
                $ido = isset($_POST['ido']) ? htmlspecialchars(strip_tags(trim($_POST['ido']))) : '';
                $uai = isset($_POST['uai']) ? htmlspecialchars(strip_tags(trim($_POST['uai']))) : '';
                $pmel = isset($_POST['pmel']) ? strip_tags(trim($_POST['pmel'])) : '';

                // get the teacher by its ido(opaque identifier)
                $garUserExists = $this->entityManager
                    ->getRepository('User\Entity\ClassroomUser')
                    ->findOneBy(array("garId" => $ido));

                // the teacher exists, return its data
                if ($garUserExists) {

                    // get its classrooms
                    $garUserClassrooms = $this->entityManager
                        ->getRepository(ClassroomLinkUser::class)
                        ->findBy(array(
                            'user' => $garUserExists->getId()->getId()
                        ));

                    // initiate an empty array to fill with extracted classrooms names
                    $classroomNames = [];
                    foreach ($garUserClassrooms as $garUserClassroom) {

                        // get the student count for each classroom
                        $classroomStudents = $this->entityManager
                            ->getRepository(ClassroomLinkUser::class)
                            ->findBy(array(
                                'classroom' => $garUserClassroom->getClassroom()->getId(),
                                'rights' => 0
                            ));
                        $classroomStudentCount = count($classroomStudents);

                        array_push($classroomNames, [
                            "name" => $garUserClassroom->getClassroom()->getName(),
                            "group" => $garUserClassroom->getClassroom()->getGroupe(),
                            "studentCount" => $classroomStudentCount,
                            "classroomLink" => $garUserClassroom->getClassroom()->getLink(),
                        ]);
                    }

                    return array(
                        'userId' => $garUserExists->getId()->getId(),
                        'classrooms' => $classroomNames
                    );
                } else {
                    // the teacher is not registered yet
                    // create a hashed password
                    //$hashedPassword = password_hash(passwordGenerator(),PASSWORD_BCRYPT);
                    $hashedPassword = password_hash('Test1234!', PASSWORD_BCRYPT);

                    // create the user to be saved in users table
                    $user = new User;
                    $user->setFirstname($pre);
                    $user->setSurname($nom);
                    $user->setPseudo("$pre $nom");
                    $user->setPassword($hashedPassword);

                    // save the user 
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    // retrieve the lastInsertId to use for the next query 
                    // this value is only available after a flush()
                    $user->setId($user->getId());

                    // create a classroomUser to be saved in user_classroom_users
                    $classroomUser = new ClassroomUser($user);
                    $classroomUser->setGarId($ido);
                    $classroomUser->setSchoolId($uai);
                    $classroomUser->setIsTeacher(true);
                    $classroomUser->setMailTeacher($pmel);
                    $this->entityManager->persist($classroomUser);
                    $this->entityManager->flush();

                    // create a regular user to be saved in user_regulars table and persist it
                    $regularUser = new Regular($user, $pmel = '');
                    $regularUser->setActive(true);
                    $this->entityManager->persist($regularUser);
                    $this->entityManager->flush();

                    // create a premiumUser to be stored in user_premium table and persist it
                    $userPremium = new UserPremium($user);
                    $this->entityManager->persist($userPremium);
                    $this->entityManager->flush();

                    return array(
                        'userId' => $user->getId()
                    );
                }
            },
            'save_gar_teacher_classrooms' => function () {

                // Allow from any origin to be commented in production
                if (isset($_SERVER['HTTP_ORIGIN'])) header("Access-Control-Allow-Origin: *");

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind incoming json data and decode them
                $incomingData = file_get_contents('php://input');
                $decodedData = json_decode($incomingData);

                // sanitize incoming data
                $uai = htmlspecialchars(strip_tags(trim($decodedData->uai)));
                $teacherId = htmlspecialchars(strip_tags(trim($decodedData->teacherId)));


                for ($i = 0; $i < count($decodedData->classroomsToCreate); $i++) {

                    // bind and sanitize each classroom and related group
                    $classroomName = htmlspecialchars(strip_tags(trim($decodedData->classroomsToCreate[$i]->classroom)));
                    $relatedGroup = htmlspecialchars(strip_tags(trim($decodedData->classroomsToCreate[$i]->relatedGroup)));

                    // get the classroom and group from classrooms and classroom_users_link_classrooms joined tables
                    $classroomExists = $this->entityManager
                        ->getRepository(ClassroomLinkUser::class)
                        ->getTeacherClassroomBy($teacherId, $classroomName, $uai, $relatedGroup);


                    // the classroom already exists, we do nothing
                    if ($classroomExists) continue;
                    else {
                        // the classroom does not exists
                        // get demoStudent from .env file
                        $demoStudent = !empty($this->envVariables['demoStudent'])
                            ? htmlspecialchars(strip_tags(trim(strtolower($this->envVariables['demoStudent']))))
                            : 'demostudent';

                        // get the current teacher object for next query
                        $teacher = $this->entityManager
                            ->getRepository(User::class)
                            ->findOneBy(array('id' => $teacherId));


                        // create the classroom
                        $classroom = new Classroom($classroomName);
                        $classroom->setGroupe($relatedGroup);
                        $classroom->setUai($uai);
                        $this->entityManager->persist($classroom);
                        $this->entityManager->flush();
                        $classroom->getId();

                        // add the teacher to the classroom with teacher rights=2
                        $classroomLinkUser = new ClassroomLinkUser($teacher, $classroom, 2);
                        $this->entityManager->persist($classroomLinkUser);
                        $this->entityManager->flush();

                        // create default demoStudent user (required for the dashboard to work properly)
                        $user = new User();
                        $user->setFirstName("élève");
                        $user->setSurname("modèl");
                        $user->setPseudo($demoStudent);
                        $password = passwordGenerator();
                        $user->setPassword(password_hash($password, PASSWORD_DEFAULT));

                        // persist and save demoStudent user in users table
                        $this->entityManager->persist($user);
                        $this->entityManager->flush();

                        // get demoStudent user id from last db query => lastInsertId
                        $user->setId($user->getId());

                        // add the demoStudent user to the classroom with students rights=0 (classroom_users_link_classrooms table)
                        $classroomLinkUser = new ClassroomLinkUser($user, $classroom, 0);
                        $this->entityManager->persist($classroomLinkUser);
                        $this->entityManager->flush();
                    }
                }

                return array(
                    'teacherId' => $teacherId,
                    'classroomsCreated' => true
                );
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
                if ($classroom->getIsBlocked() === true) {
                    // disallow students to join
                    return [
                        "isUsersAdded" => false,
                        "errorType" => "classroomBlocked"
                    ];
                }

                $classroomTeacher = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                    ->findBy(array("classroom" => $classroom->getId()));

                $currentUserId = $classroomTeacher[0]->getUser()->getId();

                // get the statuses for the current classroom owner
                $isPremium = RegularDAO::getSharedInstance()->isTester($currentUserId);
                $isAdmin = RegularDAO::getSharedInstance()->isAdmin($currentUserId);

                // get demoStudent from .env file
                $demoStudent = !empty($this->envVariables['demoStudent'])
                    ? htmlspecialchars(strip_tags(trim(strtolower($this->envVariables['demoStudent']))))
                    : 'demostudent';

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
                    "idUser" => $currentUserId,
                    "isPremium" => $isPremium,
                    "isAdmin" => $isAdmin,
                    "learnerNumber" => $nbApprenants
                ];

                // set the $isAllowed () flag to true  if the current user is admin or premium

                /**
                 * Update Rémi COINTE
                 * if the user is not admin =>
                 * we check how many students he can have
                 * if it has no apps = default number => in the folder "default-restrictions"
                 * otherwise the restrictions is set by the user apps or the group's apps he has
                 */
                if (!$learnerNumberCheck["isAdmin"]) {
                    //@Note : the isPremium check is not deleted to restrein the actual user with the isPremium method
                    // the restrictions by application is not implemented to every user
                    $addedLearnerNumber = 1;
                    if ($learnerNumberCheck["isPremium"]) {
                        // computer the total number of students registered +1 and return an error if > 50
                        $totalLearnerCount = $learnerNumberCheck["learnerNumber"] + $addedLearnerNumber;
                        // check if the 400 students limit is reached and return an error when it is reached
                        if ($totalLearnerCount > 400) {
                            return [
                                "isUsersAdded" => false,
                                "currentLearnerCount" => $learnerNumberCheck["learnerNumber"],
                                "addedLearnerNumber" => $addedLearnerNumber
                            ];
                        }
                    } else {
                        // Groups and teacher limitation per application
                        $limitationsReached = $this->entityManager->getRepository(Applications::class)->isStudentsLimitReachedForTeacher($currentUserId, $addedLearnerNumber);
                        if (!$limitationsReached['canAdd']) {
                            return [
                                "isUsersAdded" => false,
                                "currentLearnerCount" => $limitationsReached["teacherInfo"]["actualStudents"],
                                "addedLearnerNumber" => $addedLearnerNumber,
                                "message" => $limitationsReached['message']
                            ];
                        }
                    }
                }
                /**
                 * End of learner number limiting
                 */

                // check if the submitted pseudo is demoStudent

                if (strtolower($data['pseudo']) == strtolower($demoStudent)) {
                    return [
                        "isUsersAdded" => false,
                        "errorType" => "reservedNickname",
                        "currentNickname" => $demoStudent
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
                return ["isUsersAdded" => true, "user" => $user];
            },
            'help_request_from_teacher' => function () {

                // allow only POST METHOD
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return array('error' => 'Method not Allowed');

                // bind incoming data
                $subject = isset($_POST['subject']) ? htmlspecialchars(strip_tags(trim($_POST['subject']))) : null;
                $message = isset($_POST['message']) ? htmlspecialchars(strip_tags(trim($_POST['message']))) : null;
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                // initialize empty $errors array and $emailSent flag
                $errors = [];
                $emailSent = false;

                // check for errors if any
                if (empty($subject)) $errors['subjectMissing'] = true;
                if (empty($message)) $errors['messageMissing'] = true;
                if ($id == 0) $errors['invalidUserId'] = true;

                // some errors found, return them to the user
                if (!empty($errors)) {
                    return array(
                        'emailSent' => $emailSent,
                        'errors' => $errors
                    );
                }

                // no errors, we can process the data
                // retrieve the user from db
                $regularUserFound = $this->entityManager->getRepository(Regular::class)->find($id);
                if (!$regularUserFound) {
                    // no regularUser found, return an error
                    return array(
                        'emailSent' => $emailSent,
                        'errorType' => 'unknownUser'
                    );
                }

                // the user was found
                if ($regularUserFound) {

                    $user =  $this->entityManager->getRepository(User::class)->find($id);

                    $emailReceiver = $_ENV['VS_REPLY_TO_MAIL'];
                    $replyToMail = $regularUserFound->getEmail();
                    $replyToName = $user->getFirstname() . ' ' . $user->getSurname();

                    /////////////////////////////////////
                    // PREPARE EMAIL TO BE SENT
                    // received lang param
                    $userLang = isset($_COOKIE['lng'])
                        ? htmlspecialchars(strip_tags(trim($_COOKIE['lng'])))
                        : 'fr';


                    $emailTtemplateBody = $userLang . "_help_request";

                    $body = "
                        <br>
                        <p>$message</p>
                        <br>
                    ";

                    // send email
                    $emailSent = Mailer::sendMail($emailReceiver,  $subject, $body, strip_tags($body), $emailTtemplateBody, $replyToMail, $replyToName);
                    /////////////////////////////////////

                    return array(
                        "emailSent" => $emailSent
                    );
                }
                return array(
                    'id ' => $id,
                    'subject' => $subject,
                    'message' => $message
                );
            },
            'help_request_from_student' => function () {

                // allow only POST METHOD
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return array('error' => 'Method not Allowed');

                // bind incoming data
                $subject = isset($_POST['subject']) ? htmlspecialchars(strip_tags(trim($_POST['subject']))) : null;
                $message = isset($_POST['message']) ? htmlspecialchars(strip_tags(trim($_POST['message']))) : null;
                $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

                // initialize empty $errors array and $emailSent flag
                $errors = [];
                $emailSent = false;

                // check for errors if any
                if (empty($subject)) $errors['subjectMissing'] = true;
                if (empty($message)) $errors['messageMissing'] = true;
                if ($id == 0) $errors['invalidUserId'] = true;

                // some errors found, return them to the user
                if (!empty($errors)) {
                    return array(
                        'emailSent' => $emailSent,
                        'errors' => $errors
                    );
                }

                // retrieve the user from db
                $userFound = $this->entityManager->getRepository(User::class)->find($id);

                if (!$userFound) {
                    // no user found, return an error
                    return array(
                        'emailSent' => $emailSent,
                        'errorType' => 'unknownUser'
                    );
                }

                // the user was found
                if ($userFound) {
                    // retrieve its classroom id
                    $userClassroomId = $this->entityManager
                        ->getRepository(ClassroomLinkUser::class)
                        ->findOneBy(array('user' => $id))
                        ->getClassroom()
                        ->getId();

                    // retrieve the classroom teacher id from classroom_users_link_classroom table
                    $classroomTeacherId = $this->entityManager
                        ->getRepository(ClassroomLinkUser::class)
                        ->findOneBy(array(
                            'classroom' => $userClassroomId,
                            'rights' => 2
                        ))
                        ->getUser()
                        ->getId();

                    // get the teacher email
                    $teacherEmail = $this->entityManager
                        ->getRepository(Regular::class)
                        ->find($classroomTeacherId)
                        ->getEmail();

                    if (!$teacherEmail) {
                        return array(
                            'emailSent' => $emailSent,
                            'errorType' => 'unknownEmail'
                        );
                    }
                    $emailReceiver = $teacherEmail;
                    $replyToMail = 'no-reply@gmail.com';
                    $replyToName = $userFound->getFirstname() . ' ' . $userFound->getSurname();

                    /////////////////////////////////////
                    // PREPARE EMAIL TO BE SENT
                    // received lang param
                    $userLang = isset($_COOKIE['lng'])
                        ? htmlspecialchars(strip_tags(trim($_COOKIE['lng'])))
                        : 'fr';


                    $emailTtemplateBody = $userLang . "_help_request";

                    $body = "
                        <br>
                        <p>$message</p>
                        <br>
                    ";

                    // send email
                    $emailSent = Mailer::sendMail($emailReceiver,  $subject, $body, strip_tags($body), $emailTtemplateBody, $replyToMail, $replyToName);
                    /////////////////////////////////////

                    return array(
                        "emailSent" => $emailSent
                    );
                }
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
            'register' => function () {

                // return error if the request is not a POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind incoming data to the value provided or null
                $firstname = isset($_POST['firstname']) ? htmlspecialchars(strip_tags(trim($_POST['firstname']))) : null;
                $surname = isset($_POST['surname']) ? htmlspecialchars(strip_tags(trim($_POST['surname']))) : null;
                $pseudo = isset($_POST['pseudo']) ? htmlspecialchars(strip_tags(trim($_POST['pseudo']))) : null;
                $email = isset($_POST['email'])  ? htmlspecialchars(strip_tags(trim($_POST['email']))) : null;
                $password = isset($_POST['password'])  ? htmlspecialchars(strip_tags(trim($_POST['password']))) : null;
                $password_confirm = isset($_POST['password_confirm'])  ? htmlspecialchars(strip_tags(trim($_POST['password_confirm']))) : null;


                // create empty $errors and fill it with errors if any
                $errors = [];
                if (empty($firstname)) $errors['firstnameMissing'] = true;
                if (empty($surname)) $errors['surnameMissing'] = true;
                if (empty($pseudo)) $errors['pseudoMissing'] = true;
                if (empty($email)) $errors['emailMissing'] = true;
                elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['emailInvalid'] = true;
                if (empty($password)) $errors['passwordMissing'] = true;
                elseif (strlen($password) < 7) $errors['invalidPassword'] = true;
                if (empty($password_confirm)) $errors['passwordConfirmMissing'] = true;
                elseif ($password !== $password_confirm) $errors['passwordsMismatch'] = true;

                // check if the email is already listed in db
                $emailAlreadyExists = $this->entityManager
                    ->getRepository('User\Entity\Regular')
                    ->findOneBy(array('email' => $email));

                // the email already exists in db,set emailExists error 
                if ($emailAlreadyExists) $errors['emailExists'] = true;

                // some errors were found, return them to the user
                if (!empty($errors)) {
                    return array(
                        'isUserAdded' => false,
                        "errors" => $errors
                    );
                }

                // no errors found, we can process the data
                // hash the password and set $emailSent default value
                $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                $emailSent = null;
                // create user and persists it in memory
                $user = new User;
                $user->setFirstname($firstname);
                $user->setSurname($surname);
                $user->setPseudo($pseudo);
                $user->setPassword($passwordHash);
                $user->setInsertDate(new \DateTime());
                $user->setUpdateDate(new \DateTime());
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // retrieve the lastInsertId to use for the next query 
                // this value is only available after a flush()
                $user->setId($user->getId());

                // create record in user_regulars table and persists it in memory
                $regularUser = new Regular($user, $email);
                $regularUser->setActive(false);

                // create the confirm token and set user confirm token
                $confirmationToken = time() . "-" . bin2hex($email);
                $regularUser->setConfirmToken($confirmationToken);
                $this->entityManager->persist($regularUser);
                $this->entityManager->flush();

                /////////////////////////////////////
                // PREPARE EMAIL TO BE SENT
                // received lang param
                $userLang = isset($_COOKIE['lng'])
                    ? htmlspecialchars(strip_tags(trim($_COOKIE['lng'])))
                    : 'fr';

                // create the confirmation account link and set the email template to be used      
                $accountConfirmationLink = "{$this->URL}/classroom/confirm_account.php?token=$confirmationToken";
                $emailTtemplateBody = $userLang . "_confirm_account";

                // init i18next instance
                if (is_dir(__DIR__ . "/../../../../../openClassroom")) {
                    i18next::init($userLang, __DIR__ . "/../../../../../openClassroom/classroom/assets/lang/__lng__/ns.json");
                } else {
                    i18next::init($userLang, __DIR__ . "/../../../../../classroom/assets/lang/__lng__/ns.json");
                }

                $emailSubject = i18next::getTranslation('classroom.register.accountConfirmationEmail.emailSubject');
                $bodyTitle = i18next::getTranslation('classroom.register.accountConfirmationEmail.bodyTitle');
                $textBeforeLink = i18next::getTranslation('classroom.register.accountConfirmationEmail.textBeforeLink');
                $body = "
                    <a href='$accountConfirmationLink' style='text-decoration: none;padding: 10px;background: #27b88e;color: white;margin: 1rem auto;width: 50%;display: block;'>
                        $bodyTitle
                    </a>
                    <br>
                    <br>
                    <p>$textBeforeLink $accountConfirmationLink
                ";

                // send email
                $emailSent = Mailer::sendMail($email,  $emailSubject, $body, strip_tags($body), $emailTtemplateBody);
                /////////////////////////////////////

                return array(
                    'isUserAdded' => true,
                    "id" => $user->getId(),
                    "emailSent" => $emailSent
                );
            },
            'update_user_infos' => function () {

                // return error if the request is not a POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

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
                if (!$userToUpdate) {
                    return array(
                        'isUserUpdated' => false,
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
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['emailInvalid'] = true;
                if (!empty($password)) {
                    // At least 8 characters including 1 Uppercase, 1 lowercase, 1 digit, and 1 special character
                    $regex = "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/";
                    if (!preg_match($regex, $password, $matches)) {
                        $errors['passwordInvalid'] = true;
                    }
                }
                if ($email !== $tmpOldEmail) {

                    // check if the email is already listed in db
                    $emailAlreadyExists = $this->entityManager
                        ->getRepository('User\Entity\Regular')
                        ->findOneBy(array('email' => $email));
                    if ($emailAlreadyExists) $errors['emailExists'] = true;
                    else {
                        // set new_mail, confirm_token fields to be filled in user_regulars
                        $regularUserToUpdate->setNewMail($email);
                        $confirmationToken = time() . "-" . bin2hex($email);
                        $regularUserToUpdate->setConfirmToken($confirmationToken);

                        // set the flag to true for sending email later 
                        $emailUpdatedRequested = true;
                    }
                }

                // some errors have been found, return them to the user
                if (!empty($errors)) {
                    return array(
                        'isUserUpdated' => false,
                        "errors" => $errors
                    );
                }

                // no errors, update the fields value 
                if (!empty($firstname)) $userToUpdate->setFirstname($firstname);
                if (!empty($surname)) $userToUpdate->setSurname($surname);
                if (!empty($pseudo)) $userToUpdate->setPseudo($pseudo);
                $userToUpdate->setUpdateDate(new \DateTime());
                if (!empty($password)) {
                    // the user requested a password change, so we hash the password
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
                    $userToUpdate->setPassword($passwordHash);
                }

                // save data in both tables users and user_regulars
                $this->entityManager->flush();

                // the user requested its email to changed
                if ($emailUpdatedRequested == true) {
                    /////////////////////////////////////
                    // PREPARE EMAIL TO BE SENT
                    // received lang param
                    $userLang = isset($_COOKIE['lng'])
                        ? htmlspecialchars(strip_tags(trim($_COOKIE['lng'])))
                        : 'fr';

                    // set the email confirmation link and the email template to be used
                    $emailConfirmationLink = "{$this->URL}/classroom/confirm_email_update.php?token=$confirmationToken";
                    $emailTtemplateBody = $userLang . "_confirm_email_update";

                    // init i18next instance
                    if (is_dir(__DIR__ . "/../../../../../openClassroom")) {
                        i18next::init($userLang, __DIR__ . "/../../../../../openClassroom/classroom/assets/lang/__lng__/ns.json");
                    } else {
                        i18next::init($userLang, __DIR__ . "/../../../../../classroom/assets/lang/__lng__/ns.json");
                    }

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
                    $emailSent = Mailer::sendMail($email, $emailSubject, $body, strip_tags($body), $emailTtemplateBody);

                    /////////////////////////////////////
                }
                return array(
                    'isUserUpdated' => true,
                    "user" => array(
                        "id" => $userToUpdate->getId(),
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
     * registerGarStudentIfNeeded
     * return a student if already registered
     * or register a student and then return it
     * @param  object $sanitizedData
     * @return object
     */
    private function registerGarStudentIfNeeded($sanitizedData)
    {

        // check if user is already registered
        $garUserExists = $this->entityManager
            ->getRepository('User\Entity\ClassroomUser')
            ->findOneBy(array("garId" => $sanitizedData->customIdo));

        // the user exists, return its data
        if (!$garUserExists) {
            // the student is not registerd yet
            // create a hashed password
            //$hashedPassword = password_hash(passwordGenerator(),PASSWORD_BCRYPT);
            $hashedPassword = password_hash('Test1234!', PASSWORD_BCRYPT);

            // create the user to be saved in users table
            $user = new User;
            $user->setFirstname($sanitizedData->pre);
            $user->setSurname($sanitizedData->nom);
            $user->setPseudo("{$sanitizedData->pre} {$sanitizedData->nom}");
            $user->setPassword($hashedPassword);

            // save the user 
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // retrieve the lastInsertId to use for the next query 
            // this value is only available after a flush()
            $user->setId($user->getId());

            // create a classroomUser to be saved in user_classroom_users
            $classroomUser = new ClassroomUser($user);
            $classroomUser->setGarId($sanitizedData->customIdo);
            $classroomUser->setSchoolId($sanitizedData->uai);
            $classroomUser->setIsTeacher(false);

            // persist the classroomUser for later flush
            $this->entityManager->persist($classroomUser);
            $this->entityManager->flush();
            return $classroomUser;
        } else return $garUserExists;
    }

    /**
     * generateUpdatedPassword
     * 
     * @param  string $pseudo
     * @param  string $oldPassword
     * @return string generate a new password different for the old one
     *  and make sure there are no matching record in db
     */
    private function generateUpdatedPassword($pseudo, $oldPassword)
    {

        // generate the new password and check if a record exists in db with these credentials
        do {
            $newPassword = passwordGenerator();
            $duplicateUserCredentialsFound = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(array(
                    'pseudo' => $pseudo,
                    'password' => $newPassword
                ));
        }
        // continue as long as the passwords match and a record exists in db
        while (($oldPassword === $newPassword) && $duplicateUserCredentialsFound !== null);

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
