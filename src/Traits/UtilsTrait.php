<?php
namespace User\Traits;

use Classroom\Entity\Groups;
use User\Entity\UserPremium;
use Classroom\Entity\Restrictions;
use Classroom\Entity\UsersLinkGroups;
use Classroom\Entity\ClassroomLinkUser;
use Classroom\Entity\UsersRestrictions;

trait UtilsTrait {

    public static function getUserRestrictions($entityManager) {
        if (empty($_SESSION['id'])) return ["errorType" => "userNotRetrievedNotAuthenticated"];

        $restrictionsArray = [
            'maxClassrooms' => 0, // > 1 = premium
            'maxStudents' => 0, // > 50 = premium
            'dateBegin' => 0,
            'dateEnd' => 0,
            'premium' => false, // user_premium table
            'totalClassrooms' => 0,
            'totalStudents' => 0,
            'type' => 'free'
        ];

        // get user's classroom
        $classrooms = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['user' => $_SESSION['id'], 'rights' => 2]);
        if ($classrooms) {
            foreach ($classrooms as $classroom) {
                $restrictionsArray['totalClassrooms'] += 1;
                $students = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['classroom' => $classroom->getClassroom()]);
                if ($students) {
                    $restrictionsArray['totalStudents'] += count($students);
                    $restrictionsArray['totalStudents'] -= 1;
                }
            }
        }


        $checkPremium = $entityManager->getRepository(UserPremium::class)->findOneBy(['user' => $_SESSION['id']]);
        if ($checkPremium) {
            $restrictionsArray['premium'] = true;
            $restrictionsArray['type'] = 'LegacyPersonalPremium';
        }
        // get default restrictions
        $userDefaultRestrictions = $entityManager->getRepository(Restrictions::class)->findOneBy(['name' => "userDefaultRestrictions"]);
        $groupDefaultRestrictions = $entityManager->getRepository(Restrictions::class)->findOneBy(['name' => "groupDefaultRestrictions"]);
        $userGroups = $entityManager->getRepository(UsersLinkGroups::class)->findOneBy(['user' => $_SESSION['id']]);
        
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


            $group = $entityManager->getRepository(Groups::class)->findOneBy(['id' => $userGroups->getGroup()]);
            if ($group->getDateEnd() > new \DateTime('now') || $group->getDateEnd() == null) {
                $restrictionsArray['dateBegin'] = $group->getDateBegin();
                $restrictionsArray['dateEnd'] = $group->getDateEnd();

                if (($restrictionsArray['maxStudents'] < $group->getmaxStudentsPerTeachers() && $restrictionsArray['maxStudents'] != -1) || $group->getmaxStudentsPerTeachers() == -1) {
                    $restrictionsArray['maxStudents'] = $group->getmaxStudentsPerTeachers();
                    $restrictionsArray['type'] = 'GroupPremium';
                    $restrictionsArray['premium'] = true;
                }

                if ($restrictionsArray['maxClassrooms'] < $group->getmaxClassroomsPerTeachers() && $restrictionsArray['maxClassrooms'] != -1 || $group->getmaxClassroomsPerTeachers() == -1) {
                    $restrictionsArray['maxClassrooms'] = $group->getmaxClassroomsPerTeachers();
                    $restrictionsArray['type'] = 'GroupPremium';
                    $restrictionsArray['premium'] = true;
                }
            }
        }

        // GET USER RESTRICTIONS
        $userRestrictions = $entityManager->getRepository(UsersRestrictions::class)->findOneBy(['user' => $_SESSION['id']]);
        if ($userRestrictions) {
            if ($userRestrictions->getDateEnd() > new \DateTime('now') || $userRestrictions->getDateEnd() == null) {
                $restrictionsArray['dateBegin'] = $userRestrictions->getDateBegin();
                $restrictionsArray['dateEnd'] = $userRestrictions->getDateEnd();

                if ($restrictionsArray['maxStudents'] < $userRestrictions->getMaxStudents() && $restrictionsArray['maxStudents'] != -1 || $userRestrictions->getMaxStudents() == -1) {
                    $restrictionsArray['maxStudents'] = $userRestrictions->getMaxStudents();
                    $restrictionsArray['type'] = 'PersonalPremium';
                    $restrictionsArray['premium'] = true;
                }

                if ($restrictionsArray['maxClassrooms'] < $userRestrictions->getMaxClassrooms() && $restrictionsArray['maxClassrooms'] != -1 || $userRestrictions->getMaxClassrooms() == -1) {
                    $restrictionsArray['maxClassrooms'] = $userRestrictions->getMaxClassrooms();
                    $restrictionsArray['type'] = 'PersonalPremium';
                    $restrictionsArray['premium'] = true;
                }
            }
        }

        return $restrictionsArray;
    }
}

