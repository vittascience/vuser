<?php

namespace User\Tests;

use PHPUnit\Framework\TestCase;
use Utils\TestConstants;
use User\Entity\User;
use Utils\Exceptions\EntityDataIntegrityException;

class UserTest extends TestCase
{
   public function testIdIsSet()
   {
      $user = new User();
      $user->setId(TestConstants::TEST_INTEGER); // right argument
      $this->assertEquals($user->getId(), TestConstants::TEST_INTEGER);
      $this->expectException(EntityDataIntegrityException::class);
      $user->setId(-1); // negative
      $user->setId(true); // boolean
      $user->setId(null); // null
   }

   public function testFirstNameIsSet()
   {
      $user = new User();

      $acceptedFirstName = 'aaaa';
      $nonAcceptedFirstName = '';
      for ($i = 0; $i <= TestConstants::NAME_MAX_LENGTH; $i++) //add more than 20 characters 
         $nonAcceptedFirstName .= 'a';

      $user->setFirstName($acceptedFirstName); // right argument
      $this->assertEquals($user->getFirstName(), $acceptedFirstName);
      $this->expectException(EntityDataIntegrityException::class);
      $user->setFirstName(TestConstants::TEST_INTEGER); // integer
      $user->setFirstName(true); // boolean
      $user->setFirstName(null); // null
      $user->setFirstName($nonAcceptedFirstName); // more than 20 chars
   }
   public function testSurnameIsSet()
   {
      $user = new User();

      $acceptedFirstName = 'aaaa';
      $nonAcceptedFirstName = '';
      for ($i = 0; $i <= TestConstants::NAME_MAX_LENGTH; $i++) //add more than 20 characters 
         $nonAcceptedFirstName .= 'a';

      $user->setSurname($acceptedFirstName); // right argument
      $this->assertEquals($user->getSurname(), $acceptedFirstName);
      $this->expectException(EntityDataIntegrityException::class);
      $user->setSurname(TestConstants::TEST_INTEGER); // integer
      $user->setSurname(true); // boolean
      $user->setSurname(null); // null
      $user->setSurname($nonAcceptedFirstName); // more than 20 chars
   }

   public function testPasswordIsSet()
   {
      $user = new User();
      $user->setPassword(TestConstants::TEST_STRING); // right argument
      $this->assertEquals($user->getPassword(), TestConstants::TEST_STRING);
      $this->expectException(EntityDataIntegrityException::class);
      $user->setPassword(TestConstants::TEST_INTEGER); // integer
      $user->setPassword(true); // boolean
      $user->setPassword(null); // null
   }

   public function testDateIsSet()
   {
      $user = new User();
      $date = new \DateTime('now');
      $user->setInsertDate($date);
      $this->assertEquals($user->getInsertDate(), $date);
      $user->setUpdateDate(null); // can be null
      $this->assertEquals($user->getUpdateDate(), null);
      $this->expectException(EntityDataIntegrityException::class);
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
