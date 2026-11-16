<?php

namespace User\Traits;

use Classroom\Entity\Groups;
use User\Entity\Regular;
use User\Entity\UserPremium;
use Classroom\Entity\Restrictions;
use Classroom\Entity\UsersLinkGroups;
use Classroom\Entity\ClassroomLinkUser;
use Classroom\Entity\UsersRestrictions;

trait UtilsTrait {
    public static function getUserRestrictions($entityManager, $teacherId = null) {
        if (empty($_SESSION['id']) && empty($teacherId)) {
            return ["errorType" => "userNotRetrievedNotAuthenticated"];
        }

        $idToCheck = $teacherId ?: $_SESSION['id'];
        $now = new \DateTimeImmutable('now');
        // A group / personal premium only counts within valid dates (both set, begin <= now < end), like the apps "outDated" check
        $isWithinDates = static function ($dateBegin, $dateEnd) use ($now): bool {
            return $dateBegin !== null && $dateEnd !== null && $dateBegin <= $now && $dateEnd > $now;
        };

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
                // Deterministic order: the first premium teacher found also owns the student's shared AI quota
                $studentLinks = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['user' => $idToCheck, 'rights' => 0], ['classroom' => 'ASC']);
                if ($studentLinks) {
                    $hasTeacher = false;
                    $checkedTeachers = [];
                    foreach ($studentLinks as $studentLink) {
                        $classroomTeachers = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['classroom' => $studentLink->getClassroom(), 'rights' => 2], ['user' => 'ASC']);
                        foreach ($classroomTeachers as $classroomTeacher) {
                            $hasTeacher = true;
                            $classroomTeacherId = $classroomTeacher->getUser()->getId();
                            if (isset($checkedTeachers[$classroomTeacherId])) continue;
                            $checkedTeachers[$classroomTeacherId] = true;

                            $teacherRestrictions = self::getUserRestrictions($entityManager, $classroomTeacherId);
                            if (isset($teacherRestrictions['errorType']) || empty($teacherRestrictions['premium'])) continue;

                            return [
                                'maxClassrooms' => -1,
                                'maxStudents' => -1,
                                'dateBegin' => $teacherRestrictions['dateBegin'],
                                'dateEnd' => $teacherRestrictions['dateEnd'],
                                'premium' => true,
                                'totalClassrooms' => -1,
                                'totalStudents' => -1,
                                'type' => 'StudentOfPremium',
                                'premiumOwnerId' => $classroomTeacherId
                            ];
                        }
                    }

                    if (!$hasTeacher) {
                        return ["errorType" => "studentWithoutTeacher"];
                    }

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
            $restrictionsArray['legacyDateBegin'] = $checkPremium->getDateBegin();
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
            if ($group && $isWithinDates($group->getDateBegin(), $group->getDateEnd())) {
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
        if ($userRestrictions && $isWithinDates($userRestrictions->getDateBegin(), $userRestrictions->getDateEnd())) {
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

    /**
     * Mobile app access (premium-only app): a teacher needs their own premium, a student
     * gets it from the first premium teacher of their classrooms.
     * dateEnd: \DateTimeInterface, or null for a premium without end date.
     */
    public static function getMobileAppAccess($entityManager, $userId) {
        if ($entityManager->getRepository(Regular::class)->find($userId)) {
            return self::getOwnMobilePremium($entityManager, $userId) + ['role' => 'teacher'];
        }

        $studentLinks = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['user' => $userId, 'rights' => 0], ['classroom' => 'ASC']);
        foreach ($studentLinks as $studentLink) {
            $access = self::getClassroomMobileAccess($entityManager, $studentLink->getClassroom());
            if ($access['premium']) return $access + ['role' => 'student'];
        }
        return ['premium' => false, 'dateEnd' => null, 'role' => 'student'];
    }

    public static function getClassroomMobileAccess($entityManager, $classroom) {
        $teacherLinks = $entityManager->getRepository(ClassroomLinkUser::class)->findBy(['classroom' => $classroom, 'rights' => 2], ['user' => 'ASC']);
        foreach ($teacherLinks as $teacherLink) {
            $access = self::getOwnMobilePremium($entityManager, $teacherLink->getUser()->getId());
            if ($access['premium']) return $access;
        }
        return ['premium' => false, 'dateEnd' => null];
    }

    // Unlike getUserRestrictions(), a legacy user_premium row only counts within its dates
    private static function getOwnMobilePremium($entityManager, $userId) {
        $now = new \DateTimeImmutable('now');
        $legacy = $entityManager->getRepository(UserPremium::class)->findOneBy(['user' => $userId]);
        if ($legacy
            && ($legacy->getDateBegin() === null || $legacy->getDateBegin() < $now)
            && ($legacy->getDateEnd() === null || $legacy->getDateEnd() > $now)) {
            return ['premium' => true, 'dateEnd' => $legacy->getDateEnd()];
        }

        $restrictions = self::getUserRestrictions($entityManager, $userId);
        if (isset($restrictions['type']) && in_array($restrictions['type'], ['GroupPremium', 'PersonalPremium'], true)) {
            return ['premium' => true, 'dateEnd' => $restrictions['dateEnd']];
        }
        return ['premium' => false, 'dateEnd' => null];
    }
}
