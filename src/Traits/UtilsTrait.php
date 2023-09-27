<?php
namespace User\Traits;

use Classroom\Entity\Groups;
use User\Entity\UserPremium;
use Classroom\Entity\Restrictions;
use Classroom\Entity\UsersLinkGroups;
use Classroom\Entity\ClassroomLinkUser;
use Classroom\Entity\UsersRestrictions;

trait UtilsTrait {

    public function getUserRestrictions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return ["error" => "Method not Allowed"];

        if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

        $restrictionsArray = [
            'maxClassrooms' => 0, // > 1 = premium
            'maxStudents' => 0, // > 50 = premium
            'dateBegin' => 0,
            'dateEnd' => 0,
            'premium' => false, // user_premium table
            'totalClassrooms' => 0,
            'totalStudents' => 0,
        ];

        // get user's classroom
        $classrooms = $this->entityManager->getRepository(ClassroomLinkUser::class)->findBy(['user' => $_SESSION['id'], 'rights' => 2]);
        if ($classrooms) {
            foreach ($classrooms as $classroom) {
                $restrictionsArray['totalClassrooms'] += 1;
                $students = $this->entityManager->getRepository(ClassroomLinkUser::class)->findBy(['classroom' => $classroom->getClassroom()]);
                if ($students) {
                    $restrictionsArray['totalStudents'] += count($students);
                    $restrictionsArray['totalStudents'] -= 1;
                }
            }
        }


        $checkPremium = $this->entityManager->getRepository(UserPremium::class)->findOneBy(['user' => $_SESSION['id']]);
        if ($checkPremium) {
            $restrictionsArray['premium'] = true;
        }
        // get default restrictions
        $userDefaultRestrictions = $this->entityManager->getRepository(Restrictions::class)->findOneBy(['name' => "userDefaultRestrictions"]);
        $groupDefaultRestrictions = $this->entityManager->getRepository(Restrictions::class)->findOneBy(['name' => "groupDefaultRestrictions"]);
        $userGroups = $this->entityManager->getRepository(UsersLinkGroups::class)->findOneBy(['user' => $_SESSION['id']]);
        
        $usersRestrictionAmount = (array)json_decode($userDefaultRestrictions->getRestrictions());
        $restrictionsArray['maxClassrooms'] = $usersRestrictionAmount['maxClassrooms'];
        $restrictionsArray['maxStudents'] = $usersRestrictionAmount['maxStudents'];
        $restrictionsArray['dateBegin'] = -1;
        $restrictionsArray['dateEnd'] = -1;

        // get default MaxClassrooms restrictions
        if ($userGroups) {
            $groupsRestrictionAmount = (array)json_decode($groupDefaultRestrictions->getRestrictions());

            if ($restrictionsArray['maxClassrooms'] < $groupsRestrictionAmount['maxClassroomsPerTeacher']) {
                $restrictionsArray['maxClassrooms'] = $groupsRestrictionAmount['maxClassroomsPerTeacher'];
            }
            if ($restrictionsArray['maxStudents'] < $groupsRestrictionAmount['maxStudentsPerTeacher']) {
                $restrictionsArray['maxStudents'] = $groupsRestrictionAmount['maxStudentsPerTeacher'];
            }


            $group = $this->entityManager->getRepository(Groups::class)->findOneBy(['id' => $userGroups->getGroup()]);
            if ($group->getDateEnd() > new \DateTime('now') || $group->getDateEnd() == null) {
                $restrictionsArray['dateBegin'] = $group->getDateBegin();
                $restrictionsArray['dateEnd'] = $group->getDateEnd();

                if ($restrictionsArray['maxStudents'] < $group->getmaxStudentsPerTeachers()) {
                    $restrictionsArray['maxStudents'] = $group->getmaxStudentsPerTeachers();
                }

                if ($restrictionsArray['maxClassrooms'] < $group->getmaxClassroomsPerTeachers()) {
                    $restrictionsArray['maxClassrooms'] = $group->getmaxClassroomsPerTeachers();
                }
            }
        }

        // GET USER RESTRICTIONS
        $userRestrictions = $this->entityManager->getRepository(UsersRestrictions::class)->findOneBy(['user' => $_SESSION['id']]);
        if ($userRestrictions) {
            if ($userRestrictions->getDateEnd() > new \DateTime('now') || $userRestrictions->getDateEnd() == null) {
                $restrictionsArray['dateBegin'] = $userRestrictions->getDateBegin();
                $restrictionsArray['dateEnd'] = $userRestrictions->getDateEnd();

                if ($restrictionsArray['maxStudents'] < $userRestrictions->getMaxStudents()) {
                    $restrictionsArray['maxStudents'] = $userRestrictions->getMaxStudents();
                }

                if ($restrictionsArray['maxClassrooms'] < $userRestrictions->getMaxClassrooms()) {
                    $restrictionsArray['maxClassrooms'] = $userRestrictions->getMaxClassrooms();
                }
            }
        }

        return $restrictionsArray;
    }
}

