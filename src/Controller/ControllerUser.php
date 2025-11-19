<?php

namespace User\Controller;

require __DIR__ . "/../../../../../bootstrap.php";
require __DIR__ . '/../../../../autoload.php';

use Exception;
use Utils\Mailer;
use Dotenv\Dotenv;
use DAO\RegularDAO;
use User\Entity\User;
use User\Entity\LtiUser;
use User\Entity\Regular;


/**
 * @ THOMAS MODIF 1 line just below
 */
use User\Entity\Teacher;
use User\Traits\UtilsTrait;
use Aiken\i18next\i18next;
use Classroom\Entity\Groups;
use User\Entity\UserPremium;
use Utils\ConnectionManager;
use Database\DataBaseManager;
use Lti13\Entity\LtiConsumer;
use User\Entity\ClassroomUser;
use Classroom\Entity\Classroom;
use Classroom\Entity\Applications;
use Classroom\Entity\Restrictions;
use Classroom\Entity\UsersLinkGroups;
use Classroom\Entity\ActivityLinkUser;
use Classroom\Entity\ClassroomLinkUser;
use Classroom\Entity\UsersRestrictions;
use Classroom\Entity\ActivityLinkClassroom;
use User\Entity\ClassroomUserConnectionLog;



class ControllerUser extends Controller
{
    public $URL = "";
    public function __construct($entityManager, $user, $url = null)
    {
        // Load env variables
        $dir  = is_file('/run/secrets/app_env') ? '/run/secrets' : __DIR__ . '/../../../../../';
        $file = is_file('/run/secrets/app_env') ? 'app_env'      : '.env';
        Dotenv::createImmutable($dir, $file)->safeLoad();
        $this->URL = isset($url) ? $url : $_ENV['VS_HOST'];
        parent::__construct($entityManager, $user);
        $this->actions = array(
            'generate_classroom_user_password' => function () {
                /**
                 * This method is called by the teacher (inside a classroom=> select 1 student=> clic the cog=>clic re-generate password)
                 * @additionalCheckMissing
                 * => we need the classroom link to check if the current user is really the teacher
                 */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                //  bind and sanitize incoming data or session data
                $studentId = !empty($_POST['id']) ? intval($_POST['id']) : null;
                $teacherId = intval($_SESSION['id']);

                // initialize empty error array and check for errors
                if (empty($studentId)) $errors['studentIdMissing'] = true;

                // return errors if any
                if (!empty($errors)) return array('errors' => $errors);

                // retirve the student in db, else return an error
                $user = $this->entityManager->getRepository('User\Entity\User')->find($studentId);
                if (!$user) return array('errorType' => 'studentNotRetrieved');

                // get the student classroom id
                $userClassroomId = $this->entityManager
                    ->getRepository(ClassroomLinkUser::class)
                    ->findOneBy(array(
                        'user' => $studentId,
                        'rights' => 0
                    ))->getClassroom()->getId();

                // get the teacher of the student classroom
                $teacherFound = $this->entityManager
                    ->getRepository(ClassroomLinkUser::class)
                    ->findOneBy(array(
                        'classroom' => $userClassroomId,
                        'user' => $teacherId,
                        'rights' => 2
                    ));

                // no teacher found
                if (!$teacherFound) return array('errorType' => 'teacherNotRetrieved');

                // teacher found, but logged user id and classroom teacher id do not match
                if ($teacherId != $teacherFound->getUser()->getId()) return array('errorType' => 'notStudentTeacher');

                // all is ok, generate and update password and return data
                $password = passwordGenerator();
                $user->setPassword($password);
                $this->entityManager->flush();
                $pseudo = $user->getPseudo();
                return ['mdp' => $password, 'pseudo' => $pseudo];
            },
            'get_student_password' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                // get and sanitize student id from $_SESSION
                $id = !empty($_SESSION['id']) ?  intval($_SESSION['id']) : null;

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
                /**
                 * This method is called by the student (student profile => parameters => clic on re-initialize button)
                 */

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                // bind incoming id
                $id = !empty($_SESSION['id']) ? intval($_SESSION['id']) : null;

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
            'change_pseudo_classroom_user' => function () {
                /**
                 * This method is called by the teacher (inside a classroom=> select 1 student=> clic the cog=> change pseudo)
                 */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                // bind and sanitize data
                $studentId = !empty($_POST['id']) ? intval($_POST['id']) : null;
                $pseudo = !empty($_POST['pseudo']) ? htmlspecialchars(strip_tags(trim($_POST['pseudo']))) : '';
                $teacherId = intval($_SESSION['id']);

                // initialize empty $error array and look for errors
                $errors = [];
                if (empty($studentId)) $errors['studentIdEmpty'] = true;
                if (empty($pseudo)) $errors['pseudoEmpty'] = true;

                // some errors found, return them
                if (!empty($errors)) {
                    return array('errors' => $errors);
                }

                // no errors found
                // get the student data
                $student = $this->entityManager
                    ->getRepository('User\Entity\User')
                    ->find($studentId);

                // student not found, return an error
                if (!$student) return array('errorType' => 'studentNotExists');

                // get the student classroom
                $studentClassroom = $this->entityManager
                    ->getRepository(ClassroomLinkUser::class)
                    ->findOneBy(array(
                        'user' => $studentId,
                        'rights' => 0
                    ));

                // student classroom not found
                if (!$studentClassroom) return array('errorType' => 'studentNotFoundInClassroom');

                // get the classroom teacher
                $teacherOfClassroom = $this->entityManager
                    ->getRepository(ClassroomLinkUser::class)
                    ->findOneBy(array(
                        'user' => $teacherId,
                        'classroom' => $studentClassroom->getClassroom()->getId(),
                        'rights' => 2
                    ));

                // current logged user is not the classroom teacher, return an error
                if (!$teacherOfClassroom) return array('errorType' => 'userIsNotClassroomTeacher');

                // all checks passed, update the student pseudo and return data
                $student->setPseudo($pseudo);
                $this->entityManager->flush();
                return true;
            },
            'delete' => function () {
                /**
                 * This method is called by the teacher (inside a classroom=> select 1 student => clic the cog => delete)
                 */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                // bind and sanitize data
                $studentId = !empty($_POST['id']) ? intval($_POST['id']) : null;
                $teacherId = intval($_SESSION['id']);

                // initialize empty $errors array and check for errors
                $errors = [];
                if (empty($studentId)) $errors['studentIdEmpty'] = true;

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);

                // no errors found, get the student from db
                $student = $this->entityManager
                    ->getRepository('User\Entity\User')
                    ->find($studentId);

                // student not found, return an error
                if (!$student) return array('errorType' => 'studentNotExists');

                // get student classroom
                $studentClassroom = $this->entityManager
                    ->getRepository('Classroom\Entity\ClassroomLinkUser')
                    ->findOneBy(
                        array(
                            'user' => $studentId,
                            'rights' => 0
                        )
                    );

                // student classroom not found
                if (!$studentClassroom) return array('errorType' => 'studentNotFoundInClassroom');

                // get the classroom teacher
                $teacherOfClassroom = $this->entityManager
                    ->getRepository(ClassroomLinkUser::class)
                    ->findOneBy(array(
                        'user' => $teacherId,
                        'classroom' => $studentClassroom->getClassroom()->getId(),
                        'rights' => 2
                    ));

                // current logged user is not the classroom teacher, return an error
                if (!$teacherOfClassroom) return array('errorType' => 'userIsNotClassroomTeacher');

                // get all the student activities if any
                $studentActivities = $this->entityManager
                    ->getRepository(ActivityLinkUser::class)
                    ->findBy(array('user' => $student));

                // some student activities found, remove them from classroom_activities_link_classroom_users
                if ($studentActivities) {
                    foreach ($studentActivities as $studentActivity) {
                        $this->entityManager->remove($studentActivity);
                        $this->entityManager->flush();
                    }
                }

                // the logged user has enough 'rights', remove the student record from classroom_users_link_classrooms table
                $this->entityManager->remove($studentClassroom);

                // delete student records in users and other joined tables using the CASCADE feature
                $pseudoToReturn = $student->getPseudo();
                $this->entityManager->remove($student);
                $this->entityManager->flush();

                return array('pseudo' => $pseudoToReturn);
            },
            'get_one_by_pseudo_and_password' => function ($data) {
                /**
                 * This method is used by the non logged students to login into a classroom
                 * => /classroom/login.php?link=$CharLink => sign in
                 */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind and sanitize incoming data
                $pseudo = !empty($_POST['pseudo']) ? htmlspecialchars(strip_tags(trim($_POST['pseudo']))) : '';
                $password = !empty($_POST['password']) ? htmlspecialchars(strip_tags(trim($_POST['password']))) : '';
                $classroomLink = !empty($_POST['classroomLink']) ? htmlspecialchars(strip_tags(trim($_POST['classroomLink']))) : '';

                // check for errors
                if (empty($pseudo) || empty($password)) return false;

                // no errors found, retrieve the user in db
                $user = $this->entityManager
                    ->getRepository('User\Entity\User')
                    ->findOneBy(array(
                        "pseudo" => $pseudo,
                        "password" => $password
                    ));

                // no user found, return false
                if (!$user) return false;

                // retrieve the classroom by the link provided
                $classroomExists = $this->entityManager
                    ->getRepository('Classroom\Entity\Classroom')
                    ->findOneBy(array("link" => $classroomLink));

                // no classroom found, return an error
                if (!$classroomExists) return false;

                // check if the user belongs to the classroom
                $userClassroomData = $this->entityManager
                    ->getRepository('Classroom\Entity\ClassroomLinkUser')
                    ->findOneBy(array(
                        "user" => $user,
                        "classroom" => $classroomExists,
                        "rights" => 0
                    ));

                // no classroom data found, return an error
                if (!$userClassroomData) return false;

                // set,save the token and connect the user
                $token = bin2hex(random_bytes(32));
                DatabaseManager::getSharedInstance()
                    ->exec("INSERT INTO connection_tokens (token,user_ref) VALUES (?, ?)", [$token, $user->getId()]);

                $_SESSION['token'] = $token;
                $_SESSION["id"] = $user->getId();
                return true;
            },
            'get_gar_student_available_classrooms' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                $incomingClassrooms = $_POST['classrooms'];
                $classroomsToFind = [];
                // bind and sanitize incoming data
                $uai = isset($_POST['uai']) ? htmlspecialchars(strip_tags(trim($_POST['uai']))) : '';

                foreach ($incomingClassrooms as $incomingClassroom) {
                    list($incomingClassroomGarCode, $incomingClassroomName) = explode('##', $incomingClassroom);
                    $classroomCode = htmlspecialchars(strip_tags(trim($incomingClassroomGarCode)));
                    $classroomName = htmlspecialchars(strip_tags(trim($incomingClassroomName)));
                    if (!empty($classroomName) && !empty($classroomCode)) {
                        array_push($classroomsToFind, array(
                            'name' => $classroomName,
                            'garCode' => $incomingClassroomGarCode,
                        ));
                    }
                }

                $classroomsCreated = [];
                $classroomsNotCreated = [];
                foreach ($classroomsToFind  as $classroomToFind) {
                    $classroomsFound = $this->entityManager->getRepository(Classroom::class)->findBy(array(
                        'name' => $classroomToFind['name'],
                        'garCode' => $classroomToFind['garCode'],
                        'uai' => $uai
                    ));

                    if ($classroomsFound) {
                        foreach ($classroomsFound as $classroomFound) {
                            $teacher = $this->entityManager
                                ->getRepository(ClassroomLinkUser::class)
                                ->findOneBy(array(
                                    'classroom' => $classroomFound,
                                    'rights' => 2
                                ))
                                ->getUser();

                            array_push($classroomsCreated, array(
                                'id' => $classroomFound->getId(),
                                'name' => $classroomFound->getName(),
                                'garCode' => $classroomFound->getGarCode(),
                                'classroomLink' => $classroomFound->getLink(),
                                'teacher' => $teacher->getPseudo(),
                                'teacherId' => $teacher->getId()
                            ));
                        }
                    } else {
                        array_push($classroomsNotCreated, array(
                            'name' => $classroomToFind['name'],
                            'garCode' => $classroomToFind['garCode']
                        ));
                    }
                }

                return array('classrooms' => array(
                    'created' => $classroomsCreated,
                    'notCreated' => $classroomsNotCreated
                ));
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
                $sanitizedData->classroomName = isset($incomingData->classroomName)
                    ? htmlspecialchars(strip_tags(trim($incomingData->classroomName)))
                    : '';
                $sanitizedData->classroomId = isset($incomingData->classroomId) ? intval($incomingData->classroomId) : 0;
                $sanitizedData->classroomTeacherId = isset($incomingData->classroomTeacherId)
                    ? intval($incomingData->classroomTeacherId)
                    : 0;
                $sanitizedData->classroomGarCode = isset($incomingData->classroomGarCode)
                    ? htmlspecialchars(strip_tags(trim($incomingData->classroomGarCode)))
                    : '';
                $sanitizedData->customIdo = "{$sanitizedData->ido}-{$sanitizedData->uai}-{$sanitizedData->classroomName}-{$sanitizedData->classroomGarCode}-{$sanitizedData->classroomTeacherId}";

                // get the student
                $studentRegistered = $this->registerGarStudentIfNeeded($sanitizedData);

                // get the classroom
                $classroom = $this->entityManager->getRepository(Classroom::class)->find($sanitizedData->classroomId);


                if ($studentRegistered instanceof User) {

                    // check if the current user is already registered as being part of this classroom
                    $studentExistsInClassroom = $this->entityManager
                        ->getRepository(ClassroomLinkUser::class)
                        ->getStudentAndClassroomByIds(
                            $studentRegistered->getId(),
                            $sanitizedData->classroomId
                        );

                    if (!$studentExistsInClassroom) {
                        // the student not found in the classroom, add it to classroom_users_link_classrooms table
                        $linkStudentToItsClassroom = new ClassroomLinkUser($studentRegistered, $classroom);
                        $linkStudentToItsClassroom->setRights(0);
                        $this->entityManager->persist($linkStudentToItsClassroom);
                        $this->entityManager->flush();
                    }

                    // get retro attributed activities if any
                    $classroomRetroAttributedActivities = $this->entityManager
                        ->getRepository(ActivityLinkClassroom::class)
                        ->getRetroAttributedActivitiesByClassroom($classroom);
                    // dd($classroomRetroAttributedActivities);

                    // some retro attributed activities found, add them to the student
                    if ($classroomRetroAttributedActivities) {
                        $this->entityManager->getRepository(ActivityLinkUser::class)
                            ->addRetroAttributedActivitiesToStudent($classroomRetroAttributedActivities, $studentRegistered);
                    }

                    $sessionUserId = intval($studentRegistered->getId());
                }

                if ($studentRegistered instanceof ClassroomUser) {

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

                    $sessionUserId = intval($studentRegistered->getId()->getId());
                }

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

                $this->saveGarUserConnection($sanitizedData->customIdo);

                $userAddedToClassroom = $res === true ? true : false;

                return array('userAddedToClassroom' => $userAddedToClassroom);
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
                            "garCode" => $garUserClassroom->getClassroom()->getGarCode(),
                            "studentCount" => $classroomStudentCount,
                            "classroomLink" => $garUserClassroom->getClassroom()->getLink(),
                        ]);
                    }
                    
                    $this->saveGarUserConnection($garUserExists->getGarId());

                    return array(
                        'userId' => $garUserExists->getId()->getId(),
                        'classrooms' => $classroomNames
                    );
                } else {
                    // the teacher is not registered yet
                    // create a hashed password
                    $hashedPassword = password_hash(passwordGenerator(), PASSWORD_BCRYPT);
                    $fakeRegularEmail = $this->generateFakeEmailWithPrefix("gar.teacher");

                    // create the user to be saved in users table
                    $user = new User();
                    $user->setFirstname($pre);
                    $user->setSurname($nom);
                    $user->setPseudo("$pre $nom");
                    $user->setPassword($hashedPassword);

                    // save the user
                    $this->entityManager->persist($user);
                    $this->entityManager->flush();

                    // create a classroomUser to be saved in user_classroom_users
                    $classroomUser = new ClassroomUser($user);
                    $classroomUser->setGarId($ido);
                    $classroomUser->setSchoolId($uai);
                    $classroomUser->setIsTeacher(true);
                    $classroomUser->setMailTeacher($pmel);
                    $this->entityManager->persist($classroomUser);
                    $this->entityManager->flush();

                    // create a regular user to be saved in user_regulars table and persist it
                    $regularUser = new Regular($user, $fakeRegularEmail);
                    $regularUser->setActive(true);
                    $this->entityManager->persist($regularUser);
                    $this->entityManager->flush();

                    // create a premiumUser to be stored in user_premium table and persist it
                    $userPremium = new UserPremium($user);
                    $this->entityManager->persist($userPremium);
                    $this->entityManager->flush();
                    
                    $this->saveGarUserConnection($classroomUser->getGarId());

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

                    list($incomingClassroomCode, $incomingClassroomName) = explode('##', $decodedData->classroomsToCreate[$i]->classroom);
                    // bind and sanitize each classroom and related group
                    $classroomName = htmlspecialchars(strip_tags(trim($incomingClassroomName)));
                    $classroomCode = htmlspecialchars(strip_tags(trim($incomingClassroomCode)));


                    // get the classroom and group from classrooms and classroom_users_link_classrooms joined tables
                    $classroomExists = $this->entityManager
                        ->getRepository(ClassroomLinkUser::class)
                        ->getTeacherClassroomBy($teacherId, $classroomName, $uai, $classroomCode);


                    // the classroom already exists, we do nothing
                    if ($classroomExists) continue;
                    else {
                        // the classroom does not exists
                        // get demoStudent from .env file
                        $demoStudent = !empty($this->envVariables['VS_DEMOSTUDENT'])
                            ? htmlspecialchars(strip_tags(trim(strtolower($this->envVariables['VS_DEMOSTUDENT']))))
                            : 'demostudent';

                        // get the current teacher object for next query
                        $teacher = $this->entityManager
                            ->getRepository(User::class)
                            ->findOneBy(array('id' => $teacherId));


                        // create the classroom
                        $uniqueLink = $this->generateUniqueClassroomLink();
                        $classroom = new Classroom($classroomName);
                        $classroom->setGarCode($classroomCode);
                        $classroom->setUai($uai);
                        $classroom->setLink($uniqueLink);
                        $this->entityManager->persist($classroom);
                        $this->entityManager->flush();
                        $classroom->getId();

                        // add the teacher to the classroom with teacher rights=2
                        $classroomLinkUser = new ClassroomLinkUser($teacher, $classroom, 2);
                        $this->entityManager->persist($classroomLinkUser);
                        $this->entityManager->flush();

                        // create default demoStudent user (required for the dashboard to work properly)
                        $password = passwordGenerator();
                        $user = new User();
                        $user->setFirstname("élève");
                        $user->setSurname("modèl");
                        $user->setPseudo($demoStudent);
                        $user->setPassword(password_hash($password, PASSWORD_DEFAULT));

                        // persist and save demoStudent user in users table
                        $this->entityManager->persist($user);
                        $this->entityManager->flush();

                        // get demoStudent user id from last db query => lastInsertId
                        //$user->setId($user->getId());

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
            'save_gar_employee_as_teacher' => function () {

                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind and sanitize incoming data
                $pre = isset($_POST['pre']) ? htmlspecialchars(strip_tags(trim($_POST['pre']))) : '';
                $nom = isset($_POST['nom']) ? htmlspecialchars(strip_tags(trim($_POST['nom']))) : '';
                $ido = isset($_POST['ido']) ? htmlspecialchars(strip_tags(trim($_POST['ido']))) : '';
                $uai = isset($_POST['uai']) ? htmlspecialchars(strip_tags(trim($_POST['uai']))) : '';
                $pmel = isset($_POST['pmel']) ? strip_tags(trim($_POST['pmel'])) : '';
                $fakeRegularEmail = $this->generateFakeEmailWithPrefix("gar.employee");


                // get the teacher by its ido(opaque identifier)
                $garUserExists = $this->entityManager
                    ->getRepository('User\Entity\ClassroomUser')
                    ->findOneBy(array("garId" => $ido));

                if ($garUserExists) {
                    $this->saveGarUserConnection($garUserExists->getGarId());
                    return array(
                        'userId' => $garUserExists->getId()->getId()
                    );
                }

                // the teacher is not registered yet
                // create a hashed password
                $hashedPassword = password_hash(passwordGenerator(), PASSWORD_BCRYPT);

                // create the user to be saved in users table
                $user = new User();
                $user->setFirstname($pre);
                $user->setSurname($nom);
                $user->setPseudo("$pre $nom");
                $user->setPassword($hashedPassword);

                // save the user
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // create a classroomUser to be saved in user_classroom_users
                $classroomUser = new ClassroomUser($user);
                $classroomUser->setGarId($ido);
                $classroomUser->setSchoolId($uai);
                $classroomUser->setIsTeacher(true);
                $classroomUser->setMailTeacher($pmel);
                $this->entityManager->persist($classroomUser);
                $this->entityManager->flush();

                // create a regular user to be saved in user_regulars table and persist it
                $regularUser = new Regular($user, $fakeRegularEmail);
                $regularUser->setActive(true);
                $this->entityManager->persist($regularUser);
                $this->entityManager->flush();

                // create a premiumUser to be stored in user_premium table and persist it
                $userPremium = new UserPremium($user);
                $this->entityManager->persist($userPremium);
                $this->entityManager->flush();

                $this->saveGarUserConnection($classroomUser->getGarId());

                return array(
                    'userId' => $user->getId()
                );
            },
            'save_gar_employee_classroom' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind and sanitize incoming data
                $uai = isset($_POST['uai']) ? htmlspecialchars(strip_tags(trim($_POST['uai']))) : '';
                $userId = isset($_POST['userId']) ? intval($_POST['userId']) : 0;

                // get the user from db
                $teacher = $this->entityManager->getRepository(User::class)->find($userId);

                // get the classroom for the current teacher
                $classroomExist = $this->entityManager
                    ->getRepository(ClassroomLinkUser::class)
                    ->findOneBy(array(
                        'user' => $teacher,
                        'rights' => 2
                    ));

                // the classroom exists in db return it
                if ($classroomExist) {
                    return array(
                        'classroomId' => $classroomExist->getClassroom()->getId(),
                        'classroomLink' => $classroomExist->getClassroom()->getLink(),
                    );
                }

                // the classroom does not exists
                // get demoStudent from .env file
                $demoStudent = !empty($this->envVariables['VS_DEMOSTUDENT'])
                    ? htmlspecialchars(strip_tags(trim(strtolower($this->envVariables['VS_DEMOSTUDENT']))))
                    : 'demostudent';

                // create the classroom to save
                $uniqueLink = $this->generateUniqueClassroomLink();
                $classroom = new Classroom("Ma classe");
                $classroom->setUai($uai);
                $classroom->setLink($uniqueLink);
                $this->entityManager->persist($classroom);
                $this->entityManager->flush();

                // add the teacher to the classroom with teacher rights=2
                $classroomLinkUser = new ClassroomLinkUser($teacher, $classroom, 2);
                $this->entityManager->persist($classroomLinkUser);
                $this->entityManager->flush();

                // create default demoStudent user (required for the dashboard to work properly)
                $password = passwordGenerator();
                $user = new User();
                $user->setFirstname("élève");
                $user->setSurname("modèl");
                $user->setPseudo($demoStudent);
                $user->setPassword(password_hash($password, PASSWORD_DEFAULT));

                // persist and save demoStudent user in users table
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // add the demoStudent user to the classroom with students rights=0 (classroom_users_link_classrooms table)
                $classroomLinkUser = new ClassroomLinkUser($user, $classroom, 0);
                $this->entityManager->persist($classroomLinkUser);
                $this->entityManager->flush();

                return array(
                    'classroomId' => $classroomLinkUser->getClassroom()->getId(),
                    'classroomLink' => $classroomLinkUser->getClassroom()->getLink(),
                );
            },
            'linkSystem' => function () {
                /**
                 * Limiting learner number @THOMAS MODIF
                 * Added Admin check to allow them an unlimited number of new student @NASER MODIF
                 */
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // bind incoming data
                $classroomLink = !empty($_POST['classroomLink']) ? htmlspecialchars(strip_tags(trim($_POST['classroomLink']))) : '';
                $pseudo = !empty($_POST['pseudo']) ? htmlspecialchars(strip_tags(trim($_POST['pseudo']))) : '';

                // return an error when the pseudo is missing
                if (empty($pseudo)) {
                    return array(
                        'isUsersAdded' => false,
                        'errorType' => 'pseudoIsMissing'
                    );
                }

                // retrieve the classroom by its link
                $classroom = $this->entityManager->getRepository('Classroom\Entity\Classroom')->findOneBy(array("link" => $classroomLink));

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
                $demoStudent = !empty($this->envVariables['VS_DEMOSTUDENT'])
                    ? htmlspecialchars(strip_tags(trim(strtolower($this->envVariables['VS_DEMOSTUDENT']))))
                    : 'demostudent';

                $classrooms = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                    ->findBy(array("user" => $currentUserId));

                // initiate the $nbApprenants counter and loop through each classrooms
                $nbApprenants = 0;
                foreach ($classrooms as $c) {
                    $students = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')
                        ->getAllStudentsInClassroom($c->getClassroom()->getId(), 0, $demoStudent);

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

                if (strtolower($pseudo) == strtolower($demoStudent)) {
                    return [
                        "isUsersAdded" => false,
                        "errorType" => "reservedNickname",
                        "currentNickname" => $demoStudent
                    ];
                }

                $pseudoUsed = $this->entityManager->getRepository('User\Entity\User')->findBy(array('pseudo' => $pseudo));
                foreach ($pseudoUsed as $p) {
                    $pseudoUsedInClassroom = $this->entityManager->getRepository('Classroom\Entity\ClassroomLinkUser')->findOneBy(array('user' => $p, 'classroom' => $classroom));
                    if ($pseudoUsedInClassroom) {
                        return false;
                    }
                }


                // related to users table in db
                $password = passwordGenerator();
                $user = new User();
                $user->setFirstname("links-élève");
                $user->setSurname("links-modèl");
                $user->setPseudo($pseudo);
                $password = passwordGenerator();
                $user->setPassword($password);
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
                    ->findOneBy(array("link" => $classroomLink));
                $linkteacherToGroup = new ClassroomLinkUser($user, $classroom);
                $linkteacherToGroup->setRights(0);
                $this->entityManager->persist($linkteacherToGroup);

                $this->entityManager->flush();

                // get retro attributed activities if any
                $classroomRetroAttributedActivities = $this->entityManager
                    ->getRepository(ActivityLinkClassroom::class)
                    ->getRetroAttributedActivitiesByClassroom($classroom);

                // some retro attributed activities found, add them to the student
                if ($classroomRetroAttributedActivities) {
                    $this->entityManager->getRepository(ActivityLinkUser::class)
                        ->addRetroAttributedActivitiesToStudent($classroomRetroAttributedActivities, $user);
                }

                $user->classroomUser = $classroomUser;
                $user->pin = $password;

                // set/save the token,user and pin in session
                $token = bin2hex(random_bytes(32));
                DatabaseManager::getSharedInstance()
                    ->exec("INSERT INTO connection_tokens (token,user_ref) VALUES (?, ?)", [$token, $user->getId()]);

                $_SESSION['token'] = $token;
                $_SESSION["id"] = $user->getId();
                $_SESSION["pin"] = $password;
                return ["isUsersAdded" => true, "user" => $user];
            },
            'help_request_from_teacher' => function () {
                /**
                 * This method is called by the teacher (teacher profil => clic on help => clic on send message)
                 */
                // allow only POST METHOD
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return array('error' => 'Method not Allowed');

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                // bind incoming data
                $subject = isset($_POST['subject']) ? htmlspecialchars(strip_tags(trim($_POST['subject']))) : null;
                $message = isset($_POST['message']) ? htmlspecialchars(strip_tags(trim($_POST['message']))) : null;
                $id = intval($_SESSION['id']);

                // initialize empty $errors array and $emailSent flag
                $errors = [];
                $emailSent = false;

                // check for errors if any
                if (empty($subject)) $errors['subjectMissing'] = true;
                if (empty($message)) $errors['messageMissing'] = true;
                if (empty($id)) $errors['invalidUserId'] = true;

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
                        <p>from : $replyToName</p>
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
                /**
                 * This method is called by the student (student help panel => clic on send message)
                 */
                // allow only POST METHOD
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return array('error' => 'Method not Allowed');

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                // bind incoming data
                $subject = isset($_POST['subject']) ? htmlspecialchars(strip_tags(trim($_POST['subject']))) : null;
                $message = isset($_POST['message']) ? htmlspecialchars(strip_tags(trim($_POST['message']))) : null;
                $id = intval($_SESSION['id']);

                // initialize empty $errors array and $emailSent flag
                $errors = [];
                $emailSent = false;

                // check for errors if any
                if (empty($subject)) $errors['subjectMissing'] = true;
                if (empty($message)) $errors['messageMissing'] = true;
                if (empty($id)) $errors['invalidUserId'] = true;

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

                    $classroom = $this->entityManager->getRepository(Classroom::class)->findOneBy(["id" => $userClassroomId]);

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
                    $replyToName = '[From : ' . $userFound->getFirstname() . ' ' . $userFound->getSurname() . ' -- Classroom : ' . $classroom->getName() . ']';

                    /////////////////////////////////////
                    // PREPARE EMAIL TO BE SENT
                    // received lang param
                    $userLang = isset($_COOKIE['lng'])
                        ? htmlspecialchars(strip_tags(trim($_COOKIE['lng'])))
                        : 'fr';


                    $emailTtemplateBody = $userLang . "_help_request";

                    $body = "
                        <br>
                        <p>$replyToName</p>
                        <p>$message</p>
                        <br>
                    ";

                    // send email
                    $emailSent = Mailer::sendMail($emailReceiver, $subject, $body, strip_tags($body), $emailTtemplateBody, $replyToMail, $replyToName);
                    /////////////////////////////////////

                    return array(
                        "emailSent" => $emailSent
                    );
                }
            },
            'login' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                $mail = !empty($_POST['mail']) ? htmlspecialchars(strip_tags(trim($_POST['mail']))) : '';
                $password = !empty($_POST['password']) ? htmlspecialchars(strip_tags(trim($_POST['password']))) : '';
                $totp_code = !empty($_POST['totp_code']) ? htmlspecialchars(strip_tags(trim($_POST['totp_code']))) : '';

                if (empty($mail) || empty($password)) return array(
                    'success' => false,
                    'error' => "badInput"
                );

                if (ConnectionManager::getSharedInstance()->checkConnected()) return ["success" => true];

                $reponseLogin = ConnectionManager::getSharedInstance()->checkLogin($mail, $password, $totp_code);
                if (!array_key_exists('success', $reponseLogin)) {
                    $_SESSION["id"] = $reponseLogin[0];
                    $_SESSION["token"] = $reponseLogin[1];
                    return ["success" => true];
                } else {
                    if ($reponseLogin["success"] == false) {
                        return $reponseLogin;
                    }
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
                $user = new User();
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
                //$user->setId($user->getId());

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

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                $id = intval($_SESSION['id']);

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

                // new password when the user wants to update it
                $password = isset($_POST['password']) ? strip_tags(trim($_POST['password'])) : null;
                // get currently registered password from the user
                $currentPassword = !empty($_POST['current_password']) ? strip_tags(trim($_POST['current_password'])) : null;


                // create empty $errors array and fill it with errors if any and $emailSend if necessary
                $errors = [];
                $emailSent = null;
                if (empty($currentPassword)) $errors['currentPasswordInvalid'] = true;
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['emailInvalid'] = true;
                if (!empty($password) && strlen($password) < 7) $errors['passwordInvalid'] = true;
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

                // no update allowed if the current password do not match with stored password hash
                if (!password_verify($currentPassword, $userToUpdate->getPassword())) {
                    $errors['currentPasswordDoesNotMatch'] = true;
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
            'disconnect' => function () {
                // accept only POST request
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

                // accept only connected user
                if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

                // bind and sanitize incoming data and session data
                $sessionUserId = intval($_SESSION["id"]);
                $sessionToken = htmlspecialchars(strip_tags(trim($_SESSION["token"])));
                $url = !empty($_POST['url']) ? htmlspecialchars(strip_tags(trim($_POST['url']))) : '';

                try {
                    $manager = ConnectionManager::getSharedInstance();
                    $user = $manager->checkConnected();
                    if (!$user)  return false;

                    $res = $manager->deleteToken($sessionUserId, $sessionToken);
                    if (!$res) return false;

                    return !empty($url) ? $url : "/index.php";

                    /* else
                    {
                        $res = $manager->deleteToken($sessionUserId, $sessionToken);
                        if ($res) {
                            if (!empty($url)) {
                                return $url;
                            } else {
                                return "/index.php";
                            }
                        } else {
                            return false;
                        }
                    } */
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
                    $user->setFirstname($firstname);
                    $user->setSurname($surname);
                    $user->setPseudo($surname . " " . $firstname);
                    $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
                    $lastQuestion = $this->entityManager->getRepository('User\Entity\User')->findOneBy([], ['id' => 'desc']);
                    //$user->setId($lastQuestion->getId() + 1);
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
            },
            'register_lti13_user' => function () {

                // sanitize incoming data
                $issuer = !empty($_POST['issuer']) ? htmlspecialchars(strip_tags(trim($_POST['issuer']))) : '';
                $ltiCourseId = !empty($_POST['course_id']) ? htmlspecialchars(strip_tags(trim($_POST['course_id']))) : null;
                $ltiUserId = !empty($_POST['user_id']) ? htmlspecialchars(strip_tags(trim($_POST['user_id']))) : null;
                $isTeacher = !empty($_POST['is_teacher']) ? boolval($_POST['is_teacher']) : false;

                // create empty $errors array, validate data and file $errors if any
                $errors = [];
                if (empty($issuer)) array_push($errors, array('errorType' => 'issuerInvalid'));
                if (empty($ltiUserId)) array_push($errors, array('errorType' => 'userIdInvalid'));
                if (!is_bool($isTeacher)) array_push($errors, array('errorType' => 'isTeacherInvalid'));

                // some errors found, return them
                if (!empty($errors)) return array('errors' => $errors);


                // get the ltiTool using the issuer
                $ltiConsumer = $this->entityManager
                    ->getRepository(LtiConsumer::class)
                    ->findOneBy(array('issuer' => $issuer));

                $ltiUserExists = $this->entityManager
                    ->getRepository(LtiUser::class)
                    ->findOneBy(array(
                        'ltiConsumer' => $ltiConsumer,
                        'ltiUserId' => $ltiUserId
                    ));

                // the lti user exists, return its id
                if ($ltiUserExists) {
                    return array('userId' => $ltiUserExists->getUser()->getId());
                }


                // lti user does not exists
                // create the user
                $password = passwordGenerator();
                $user = new User();
                $user->setFirstname("lti_user_firstname");
                $user->setSurname("lti_user_surname");
                $user->setPseudo("lti_user_pseudo");
                $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
                $user->setInsertDate(new \DateTime());
                $user->setUpdateDate(new \DateTime());
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                // create the ltiUser
                $ltiUser = new LtiUser;
                $ltiUser->setLtiConsumer($ltiConsumer)
                    ->setUser($user)
                    ->setLtiUserId($ltiUserId)
                    ->setIsTeacher($isTeacher);

                if ($ltiCourseId) {
                    $ltiUser->setLtiCourseId($ltiCourseId);
                }

                $this->entityManager->persist($ltiUser);
                $this->entityManager->flush();


                return array('userId' => $ltiUser->getUser()->getId());
            },
            'get_user_restriction' => function () {
                if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];
                return UtilsTrait::getUserRestrictions($this->entityManager);
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
            $hashedPassword = password_hash(passwordGenerator(), PASSWORD_BCRYPT);

            // create the user to be saved in users table
            $user = new User();
            $user->setFirstname($sanitizedData->pre);
            $user->setSurname($sanitizedData->nom);
            $user->setPseudo("{$sanitizedData->pre} {$sanitizedData->nom}");
            $user->setPassword($hashedPassword);
            $user->setInsertDate(new \DateTime('now'));

            // save the user
            $this->entityManager->persist($user);
            $this->entityManager->flush();

            // retrieve the lastInsertId to use for the next query
            // this value is only available after a flush()
            //$user->setId($user->getId());

            // create a classroomUser to be saved in user_classroom_users
            $classroomUser = new ClassroomUser($user);
            $classroomUser->setGarId($sanitizedData->customIdo);
            $classroomUser->setSchoolId($sanitizedData->uai);
            $classroomUser->setIsTeacher(false);

            // persist the classroomUser for later flush
            $this->entityManager->persist($classroomUser);
            $this->entityManager->flush();
            return $user;
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

    /**
     * generate fake email for gar users
     * as the email is optional but mandatory for saving a Regular user
     *
     * @param string $prefix
     * @return string
     */
    private function generateFakeEmailWithPrefix($prefix)
    {

        // generate the new password and check if a record exists in db with these credentials
        do {
            $email = $prefix . '.' . passwordGenerator() . "@gmail.com";
            $emailExists = $this->entityManager
                ->getRepository(Regular::class)
                ->findOneBy(array(
                    'email' => $email
                ));
        }
        // continue as long as the passwords match and a record exists in db
        while ($emailExists);

        return $email;
    }
    private function generateUniqueClassroomLink()
    {
        $alphaNums = "abcdefghijklmnopqrstuvwxyz0123456789";
        do {
            $link = "";

            for ($i = 0; $i < 5; $i++) {
                $link .= substr($alphaNums, rand(0, 35), 1);
            }

            $classroomByLinkFound = $this->entityManager
                ->getRepository(Classroom::class)
                ->findOneByLink($link);
        } while ($classroomByLinkFound);

        return $link;
    }
    private function saveGarUserConnection($garId)
    {
        $classroomUser = $this->entityManager->getRepository(ClassroomUser::class)->findOneBy(array('garId' => $garId));

        $connection = new ClassroomUserConnectionLog;
        $connection->setUser($classroomUser->getId())
            ->setGarId($classroomUser->getGarId())
            ->setConnectionDate(new \DateTime());
        $this->entityManager->persist($connection);
        $this->entityManager->flush();
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
