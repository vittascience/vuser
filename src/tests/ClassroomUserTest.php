<?php

namespace ClassroomUser\Tests;

use PHPUnit\Framework\TestCase;
use Utils\TestConstants;
use User\Entity\User;
use User\Entity\ClassroomUser;
use Utils\Exceptions\EntityDataIntegrityException;

class ClassroomUserTest extends TestCase
{
    const GAR_ID_LENGTH = 128;
    const SCHOOL_ID_LENGTH = 8;
    public function testIdIsSet()
    {
        $user = new ClassroomUser(new User());
        $user->setId(TestConstants::TEST_INTEGER); // right argument
        $this->assertEquals($user->getId(), TestConstants::TEST_INTEGER);
    }

    public function testGarIdIsSet()
    {
        $user = new ClassroomUser(new User());

        $acceptedGarId = '';
        for ($i = 0; $i < self::GAR_ID_LENGTH; $i++) //add 128 characters 
            $acceptedGarId .= 'a';

        $user->setGarId($acceptedGarId); // right argument
        $refusedGarId = $acceptedGarId . "a";
        $this->assertEquals($user->getGarId(), $acceptedGarId);
        $this->expectException(EntityDataIntegrityException::class);
        $user->setGarId(TestConstants::TEST_INTEGER); // integer
        $user->setGarId(true); // boolean
        $user->setGarId($refusedGarId); // more than 128 chars
    }
    public function testSchoolIdIsSet()
    {
        $user = new ClassroomUser(new User());

        $acceptedSchoolId = '';
        for ($i = 0; $i < self::SCHOOL_ID_LENGTH; $i++) //add 8 characters 
            $acceptedSchoolId .= 'a';

        $user->setSchoolId($acceptedSchoolId); // right argument
        $refusedSchoolId = $acceptedSchoolId . "a";
        $this->assertEquals($user->getSchoolId(), $acceptedSchoolId);
        $this->expectException(EntityDataIntegrityException::class);
        $user->setSchoolId(TestConstants::TEST_INTEGER); // integer
        $user->setSchoolId(true); // boolean
        $user->setSchoolId(null); // null
        $user->setSchoolId($refusedSchoolId); // more than 8 chars
    }
    public function testIsTeacherIsSet()
    {
        $user = new ClassroomUser(new User());
        $user->setIsTeacher(true); // sould be bool
        $this->assertTrue($user->getIsTeacher());
        $this->expectException(EntityDataIntegrityException::class);
        $user->setIsTeacher(TestConstants::TEST_INTEGER); // should not be an integer
        $user->setIsTeacher(TestConstants::TEST_STRING); // should ne be a string
        $user->setIsTeacher(null); // should not be null
    }

    public function testMailTeacherIsSet()
    {
        $user = new ClassroomUser(new User());
        $user->setMailTeacher(TestConstants::TEST_MAIL); // right argument
        $this->assertEquals($user->getMailTeacher(), TestConstants::TEST_MAIL);
        $this->expectException(EntityDataIntegrityException::class);
        $user->setMailTeacher(TestConstants::TEST_INTEGER); // integer
        $user->setMailTeacher(true); // boolean
    }

    /* public function testjsonSerialize()
    {
        $user = new ClassroomUser(new User());
        $user->setId(TestConstants::TEST_INTEGER);
        $user->setGarId(null);
        $user->setSchoolId(null);

        //test array
        $test = [
            "garId" => null,
            "schoolId" => null,
            "isTeacher" => false,
            "mailTeacher" => NULL
        ];
         $this->assertEquals($user->jsonSerialize(), $test); 
    } */
}
