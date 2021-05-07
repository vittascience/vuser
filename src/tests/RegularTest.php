<?php

namespace User\Tests;

use PHPUnit\Framework\TestCase;
use Utils\TestConstants;
use User\Entity\Regular;
use User\Entity\User;
use Utils\Exceptions\EntityDataIntegrityException;

class RegularTest extends TestCase
{
    /* public function testEmailIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setEmail('test@test.com');
        $this->assertEquals($user->getEmail(), 'test@test.com');
        $this->assertIsString($user->getEmail());
        $this->expectException(EntityDataIntegrityException::class);
        $user->setEmail('test@test'); // wrong email
        $user->setEmail('test@'); // wrong email
        $user->setEmail('test@test.'); // wrong email
        $user->setEmail(TestConstants::TEST_INTEGER); // integer
        $user->setEmail(null); // wrong email
    } */

/*     public function testBioIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);

        $acceptedFirstName = 'aaaa';
        $nonAcceptedFirstName = 'aa';
        for ($i = 0; $i <= TestConstants::BIO_MAX_LENGTH; $i++) //add more than 1000 characters 
            $nonAcceptedFirstName .= 'a';

        $user->setBio($acceptedFirstName); // right value
        $this->assertEquals($user->getBio(), $acceptedFirstName);
        $user->setBio(null); // null
        $this->assertEquals($user->getBio(), null);
        $this->expectException(EntityDataIntegrityException::class); // more than 1000 chars
        $user->setBio($nonAcceptedFirstName);
    }

    public function testTelephoneIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setTelephone('0797989566'); // right value
        $this->assertEquals($user->getTelephone(), '0797989566');
        $user->setTelephone(null); // null
        $this->assertEquals($user->getTelephone(), null);
        $this->expectException(EntityDataIntegrityException::class);
        $user->setTelephone(45220999); // integer expected exception
    }


    public function testConfirmTokenIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setConfirmToken(TestConstants::TEST_STRING);
        $this->assertEquals($user->getConfirmToken(), TestConstants::TEST_STRING);
        $user->setConfirmToken(null); //can be null
        $this->assertEquals($user->getConfirmToken(), null);
        $this->expectException(EntityDataIntegrityException::class);
        $user->setConfirmToken(TestConstants::TEST_INTEGER); // should not be an integer
        $user->setConfirmToken(true); // should not be a boolean
    }

    public function testPrivateFlagIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setPrivateFlag(true); // sould be bool
        $this->assertTrue($user->isPrivateFlag());
        $this->expectException(EntityDataIntegrityException::class);
        $user->setPrivateFlag(TestConstants::TEST_INTEGER); // should not be an integer
        $user->setPrivateFlag(TestConstants::TEST_STRING); // should ne be a string
        $user->setPrivateFlag(null); // should not be null

    }

    public function testContactFlagIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setContactFlag(true); // sould be bool
        $this->assertTrue($user->isContactFlag());
        $this->expectException(EntityDataIntegrityException::class);
        $user->setContactFlag(TestConstants::TEST_INTEGER); // should not be an integer
        $user->setContactFlag(TestConstants::TEST_STRING); // should ne be a string
        $user->setContactFlag(null); // should not be null
    }

    public function testNewsletterIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setNewsletter(true); // sould be bool
        $this->assertTrue($user->isNewsletter());
        $this->expectException(EntityDataIntegrityException::class);
        $user->setNewsletter(TestConstants::TEST_INTEGER); // should not be an integer
        $user->setNewsletter(TestConstants::TEST_STRING); // should ne be a string
        $user->setNewsletter(null); // should not be null
    }

    public function testMailMessagesIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setMailMessages(true); // sould be bool
        $this->assertTrue($user->isMailMessages());
        $this->expectException(EntityDataIntegrityException::class);
        $user->setMailMessages(TestConstants::TEST_INTEGER); // should not be an integer
        $user->setMailMessages(TestConstants::TEST_STRING); // should ne be a string
        $user->setMailMessages(null); // should not be null
    }

    public function testActiveIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setActive(true); // sould be bool
        $this->assertTrue($user->isActive());
        $this->expectException(EntityDataIntegrityException::class);
        $user->setActive(TestConstants::TEST_INTEGER); // should not be an integer
        $user->setActive(TestConstants::TEST_STRING); // should ne be a string
        $user->setActive(null); // should not be null
    }

    public function testRecoveryTokenIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setRecoveryToken(TestConstants::TEST_STRING);
        $this->assertEquals($user->getRecoveryToken(), TestConstants::TEST_STRING);
        $user->setRecoveryToken(null); // null
        $this->assertEquals($user->getRecoveryToken(), null);
        $this->expectException(EntityDataIntegrityException::class);
        $user->setRecoveryToken(TestConstants::TEST_INTEGER); // integer
        $user->setRecoveryToken(true); // boolean
    }

    public function testNewMailIsSet()
    {
        $user = new Regular(new User(), TestConstants::TEST_MAIL);
        $user->setNewMail(TestConstants::TEST_STRING);
        $this->assertEquals($user->getNewMail(), TestConstants::TEST_STRING);
        $user->setNewMail(null); // null
        $this->assertEquals($user->getNewMail(), null);
        $this->expectException(EntityDataIntegrityException::class);
        $user->setNewMail(TestConstants::TEST_INTEGER); // integer
        $user->setNewMail(true); // boolean
    } */
}
