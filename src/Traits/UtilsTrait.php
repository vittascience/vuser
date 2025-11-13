<?php

namespace User\Traits;

use Classroom\Entity\Groups;
use User\Entity\UserPremium;
use Classroom\Entity\Restrictions;
use Classroom\Entity\UsersLinkGroups;
use Classroom\Entity\ClassroomLinkUser;
use Classroom\Entity\UsersRestrictions;

trait UtilsTrait {
    public static function getUserRestrictions($entityManager, $teacherId = null) {
        if (empty($_SESSION['id'])) {
            return ["errorType" => "userNotRetrievedNotAuthenticated"];
        }

        $idToCheck = $teacherId ?: $_SESSION['id'];
        $now = new \DateTimeImmutable('now');

        $betterCap = static function (?int $cur, ?int $cand): int {
            $cur = $cur ?? 0;
            $cand = $cand ?? 0;

            if ($cur === -1 || $cand === -1) return -1;
            return max($cur, $cand);
        };

        $restrictionsArray = [
            'maxClassrooms' => 0,
            'maxStudents' => 0,
            'dateBegin' => 0,
            'dateEnd' => 0,
            'premium' => false,
            'totalClassrooms' => 0,
            'totalStudents' => 0,
            'type' => 'free',
        ];

        if ($teacherId === null) {
            $hasTeacherLink = (bool) $entityManager->getRepository(ClassroomLinkUser::class)->findOneBy(['user' => $idToCheck, 'rights' => 2]);

            if (!$hasTeacherLink) {
                $isStudent = $entityManager->getRepository(ClassroomLinkUser::class)->findOneBy(['user' => $idToCheck, 'rights' => 0]);
                if ($isStudent) {
                    $classroomTeacher = $entityManager->getRepository(ClassroomLinkUser::class)->findOneBy(['classroom' => $isStudent->getClassroom(), 'rights' => 2]);

                    if (!$classroomTeacher) {
                        return ["errorType" => "studentWithoutTeacher"];
                    }

                    $teacherRestrictions = self::getUserRestrictions($entityManager, $classroomTeacher->getUser()->getId());
                    if (isset($teacherRestrictions['errorType'])) {
                        return $teacherRestrictions;
                    }

                    if (!empty($teacherRestrictions['premium'])) {
                        return [
                            'maxClassrooms' => -1,
                            'maxStudents' => -1,
                            'dateBegin' => $teacherRestrictions['dateBegin'],
                            'dateEnd' => $teacherRestrictions['dateEnd'],
                            'premium' => true,
                            'totalClassrooms' => -1,
                            'totalStudents' => -1,
                            'type' => 'StudentOfPremium'
                        ];
                    } else {
                        return [
                            'maxClassrooms' => 0,
                            'maxStudents' => 0,
                            'dateBegin' => 0,
                            'dateEnd' => 0,
                            'premium' => false,
                            'totalClassrooms' => 0,
                            'totalStudents' => 0,
                            'type' => 'free'
                        ];
                    }
                }
            }
        }

        $classrooms = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['user' => $idToCheck, 'rights' => 2]);
        foreach ($classrooms as $classroom) {
            $restrictionsArray['totalClassrooms']++;
            $students = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['classroom' => $classroom->getClassroom()]);
            if ($students) {
                $restrictionsArray['totalStudents'] += max(0, count($students) - 1);
            }
        }

        $checkPremium = $entityManager->getRepository(UserPremium::class)
            ->findOneBy(['user' => $idToCheck]);
        if ($checkPremium) {
            $restrictionsArray['premium'] = true;
            $restrictionsArray['type'] = 'LegacyPersonalPremium';
        }

        $userDefaultRestrictions = $entityManager->getRepository(Restrictions::class)->findOneBy(['name' => "userDefaultRestrictions"]);
        if ($userDefaultRestrictions) {
            $usersRestrictionAmount = (array)json_decode($userDefaultRestrictions->getRestrictions(), true);
            $restrictionsArray['maxClassrooms'] = $usersRestrictionAmount['maxClassrooms'] ?? 0;
            $restrictionsArray['maxStudents']   = $usersRestrictionAmount['maxStudents']   ?? 0;
            $restrictionsArray['dateBegin'] = -1;
            $restrictionsArray['dateEnd']   = -1;
        }

        $userGroups = $entityManager->getRepository(UsersLinkGroups::class)->findOneBy(['user' => $idToCheck]);
        if ($userGroups) {
            $group = $entityManager->getRepository(Groups::class)->findOneBy(['id' => $userGroups->getGroup()]);
            if ($group && ($group->getDateEnd() === null || $group->getDateEnd() > $now)) {
                $groupDefaultRestrictions = $entityManager->getRepository(Restrictions::class)
                    ->findOneBy(['name' => "groupDefaultRestrictions"]);
                if ($groupDefaultRestrictions) {
                    $groupsRestrictionAmount = (array)json_decode($groupDefaultRestrictions->getRestrictions(), true);
                    $restrictionsArray['maxClassrooms'] = $betterCap(
                        $restrictionsArray['maxClassrooms'],
                        $groupsRestrictionAmount['maxClassroomsPerTeacher'] ?? 0
                    );
                    $restrictionsArray['maxStudents'] = $betterCap(
                        $restrictionsArray['maxStudents'],
                        $groupsRestrictionAmount['maxStudentsPerTeacher'] ?? 0
                    );
                }

                $restrictionsArray['dateBegin'] = $group->getDateBegin();
                $restrictionsArray['dateEnd']   = $group->getDateEnd();

                $restrictionsArray['maxStudents'] = $betterCap(
                    $restrictionsArray['maxStudents'],
                    $group->getMaxStudentsPerTeachers()
                );
                $restrictionsArray['maxClassrooms'] = $betterCap(
                    $restrictionsArray['maxClassrooms'],
                    $group->getMaxClassroomsPerTeachers()
                );

                $restrictionsArray['premium'] = true;
                $restrictionsArray['type'] = 'GroupPremium';
            }
        }

        $userRestrictions = $entityManager->getRepository(UsersRestrictions::class)->findOneBy(['user' => $idToCheck]);
        if ($userRestrictions && ($userRestrictions->getDateEnd() === null || $userRestrictions->getDateEnd() > $now)) {
            $restrictionsArray['dateBegin'] = $userRestrictions->getDateBegin();
            $restrictionsArray['dateEnd']   = $userRestrictions->getDateEnd();

            $restrictionsArray['maxStudents'] = $betterCap(
                $restrictionsArray['maxStudents'],
                $userRestrictions->getMaxStudents()
            );
            $restrictionsArray['maxClassrooms'] = $betterCap(
                $restrictionsArray['maxClassrooms'],
                $userRestrictions->getMaxClassrooms()
            );

            $restrictionsArray['premium'] = true;
            $restrictionsArray['type'] = 'PersonalPremium';
        }

        return $restrictionsArray;
    }
}
