<?php

namespace User\Tests;

use PHPUnit\Framework\TestCase;
use Utils\TestConstants;
use User\Entity\User;
use Utils\Exceptions\EntityDataIntegrityException;

class UserTest extends TestCase
{

/*    public function testIdIsSet()
   {
      $user = new User();
      $user->setId(TestConstants::TEST_INTEGER); // right argument
      $this->assertEquals($user->getId(), TestConstants::TEST_INTEGER);
      $this->expectException(EntityDataIntegrityException::class);
      $user->setId(-1); // negative
      $user->setId(true); // boolean
      $user->setId(null); // null
   } */

   // Firstname is a string only
   public function testFirstNameIsSet()
   {
      $user = new User();

      $acceptedFirstName = 'aaaa';
      $user->setFirstName($acceptedFirstName); // right argument
      $this->assertEquals($user->getFirstName(), $acceptedFirstName);
      $this->expectException(TypeError::class);
      $user->setFirstName(TestConstants::TEST_INTEGER); // integer
      $user->setFirstName(true); // boolean
      $user->setFirstName(null); // null
   }
   // Surname is a string only
   public function testSurnameIsSet()
   {
      $user = new User();

      $acceptedFirstName = 'aaaa';

      $user->setSurname($acceptedFirstName); // right argument
      $this->assertEquals($user->getSurname(), $acceptedFirstName);
      $this->expectException(TypeError::class);
      $user->setSurname(TestConstants::TEST_INTEGER); // integer
      $user->setSurname(true); // boolean
      $user->setSurname(null); // null
   }
   // Password is a string only
   public function testPasswordIsSet()
   {
      $user = new User();
      $user->setPassword(TestConstants::TEST_STRING); // right argument
      $this->assertEquals($user->getPassword(), TestConstants::TEST_STRING);
      $this->expectException(TypeError::class);
      $user->setPassword(TestConstants::TEST_INTEGER); // integer
      $user->setPassword(true); // boolean
      $user->setPassword(null); // null
   }

   // Update date is datetime or null only
   public function testDateIsSet()
   {
      $user = new User();
      $date = new \DateTime('now');
      $user->setInsertDate($date);
      $this->assertEquals($user->getInsertDate(), $date);
      $user->setUpdateDate(null); // can be null
      $this->assertEquals($user->getUpdateDate(), null);
      $this->expectException(TypeError::class);
      $user->setUpdateDate(TestConstants::TEST_INTEGER); // should not be integer
      $user->setUpdateDate(TestConstants::TEST_STRING); // should not be a string
   }

   public function testjsonSerialize()
   {
      $user = new User();
      $user->setId(TestConstants::TEST_INTEGER);
      $user->setFirstName('testFirstname');
      $user->setSurname('testSurname');

      //test array
      $test = [
         "id" => TestConstants::TEST_INTEGER,
         "firstname" => "testFirstname",
         "surname" => "testSurname",
         "picture" => null,
         "pseudo" => null
      ];
      $this->assertEquals($user->jsonSerialize(), $test);
   }

   public function testjsonDeserialize()
   {
      $json = '{"id":5,"firstname":"testFirstname","surname":"testSurname"}';
      $user = new User();
      //Deserialize the json
      $user = $user->jsonDeserialize($json);
      //test array
      $test = [
         "id" => TestConstants::TEST_INTEGER,
         "firstname" => "testFirstname",
         "surname" => "testSurname",
         "picture" => null,
         "pseudo" => null
      ];
      $this->assertEquals($user->jsonSerialize(), $test);
   }
}
