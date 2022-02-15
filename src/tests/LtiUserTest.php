<?php

namespace User\Tests;

use User\Entity\User;
use User\Entity\LtiUser;
use Lti13\Entity\LtiConsumer;
use PHPUnit\Framework\TestCase;
use Utils\Exceptions\EntityDataIntegrityException;

class LtiUserTest extends TestCase{
     private $ltiUser;

    public function setUp() :void {
        $this->ltiUser = new LtiUser;
    }

    public function tearDown() :void{
        $this->ltiUser = null;
    }

    public function testGetIdIsNullByDefault(){
        $this->assertNull($this->ltiUser->getId());
    }

    /** @dataProvider provideFakeIds  */
    public function testGetIdReturnIds($id){
        $this->assertNull($this->ltiUser->getId());

        // use reflection class to set private property $id
        $LtiUserReflexionClass = new \ReflectionClass(LtiUser::class);
        $ltiUserReflectionProperty = $LtiUserReflexionClass->getProperty('id');
        $ltiUserReflectionProperty->setAccessible(true);
        $ltiUserReflectionProperty->setValue($this->ltiUser,$id);

        $this->assertNotNull($this->ltiUser->getId());
        $this->assertEquals($id, $this->ltiUser->getId());
    }

    public function testGetUserIsNullByDefault(){
        $this->assertNull($this->ltiUser->getUser());
    }

    public function testGetUserRetunAnIsntanceOfUserClass(){
        $user = new User;

        // use reflection class to access private property
        $ltiUserReflectionClass = new \ReflectionClass(LtiUser::class);
        $ltiUserReflectionProperty = $ltiUserReflectionClass->getProperty('user');
        $ltiUserReflectionProperty->setAccessible(true);
        $ltiUserReflectionProperty->setValue($this->ltiUser, $user);

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame($user, $this->ltiUser->getUser());
    }

    /** @dataProvider provideNonObjectValues */
    public function testSetUserRejectNonObjectValue($providedValue){
        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiUser->setUser($providedValue);  
    }

    /** @dataProvider provideUserObjectValues */
    public function testSetUserAcceptsUserObjectValue($providedUserObjectValue){
        $this->assertNull($this->ltiUser->getUser());

        $this->ltiUser->setUser($providedUserObjectValue);
        $this->assertInstanceOf(User::class, $this->ltiUser->getUser());
        $this->assertSame($providedUserObjectValue, $this->ltiUser->getUser());
    }

    public function testGetLtiConsumerIsNullByDefault(){
        $this->assertNull($this->ltiUser->getLtiConsumer());
    }

    public function testGetLtiConsumerReturnAnInstanceOfLtiToolClass(){
        $ltiConsumer = new LtiConsumer;

        // create the closure to access and set the private property instead of reflection class
        $fakeSetLtiConsumerClosure = function() use ($ltiConsumer) {
            return $this->ltiConsumer = $ltiConsumer;
        };

        // bind the closure to the object
        $executeFakeSetLtiConsumer = \Closure::bind($fakeSetLtiConsumerClosure,$this->ltiUser, LtiUser::class);

        // run the closure
        $executeFakeSetLtiConsumer();

        $this->assertInstanceOf(LtiConsumer::class, $this->ltiUser->getLtiConsumer());
    }

    /** @dataProvider provideNonObjectValues */
    public function testSetLtiToolRejectNonLtiToolObjectValue($providedValue){
        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiUser->setLtiConsumer($providedValue);
    }

    /** @dataProvider provideLtiToolObjectValues */
    public function testSetLtiConsumerAcceptsLtiToolObjectValue($providedLtiToolObjectValue){
        $this->assertNull($this->ltiUser->getLtiConsumer());
        
        $this->ltiUser->setLtiConsumer($providedLtiToolObjectValue);

        $this->assertInstanceOf(LtiConsumer::class, $this->ltiUser->getLtiConsumer());
        $this->assertEquals($providedLtiToolObjectValue, $this->ltiUser->getLtiConsumer());
    }

    public function testGetLtiUserIdIsNullByDefault(){
        $this->assertNull($this->ltiUser->getLtiUserId());
    }

    /** @dataProvider provideInvalidValues */
    public function testSetLtiUserIdCanNotBeSetWithInvalidValue($falsyValueProvided){
        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiUser->setLtiUserId($falsyValueProvided);
    }

    /** @dataProvider provideValidLtiUserOrCourseIds */
    public function testSetLtiUserAcceptsValidId($providedValue){
        $this->assertNull($this->ltiUser->getLtiUserId());

        $this->ltiUser->setLtiUserId($providedValue);
        $this->assertNotNull($this->ltiUser->getLtiUserId());
        $this->assertEquals($providedValue, $this->ltiUser->getLtiUserId());
    } 

    public function testGetLtiCourseIdIsNullByDefault(){
        $this->assertNull($this->ltiUser->getLtiCourseId());
    }

    /** @dataProvider provideInvalidValues */
    public function testSetCourseIdRejectInvalidId($providedNonValidValue){
        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiUser->setLtiCourseId($providedNonValidValue);
    }

    /** @dataProvider provideValidLtiUserOrCourseIds */
    public function testSetCourseIdAcceptsValidId($providedValue){
        $this->assertNull($this->ltiUser->getLtiCourseId());

        $this->ltiUser->setLtiCourseId($providedValue);
        $this->assertNotNull($this->ltiUser->getLtiCourseId());
        $this->assertSame($providedValue, $this->ltiUser->getLtiCourseId());
    }

    public function testGetIsTeacherIsNullByDefault(){
        $this->assertNull($this->ltiUser->getIsTeacher());
    }

    /** @dataProvider provideInvalidBooleanValues */
    public function testSetIsTeacherRejectsInvalidValue($providedInvalidValue){
        $this->expectException(EntityDataIntegrityException::class);
        $this->ltiUser->setIsTeacher($providedInvalidValue);
    }

    /** @dataProvider provideValidBooleanValues */
    public function testSetIsTeacherAcceptsValidBooleanValue($providedValue){
        $this->assertNull($this->ltiUser->getIsTeacher());

        $this->ltiUser->setIsTeacher($providedValue);
        $this->assertEquals($providedValue, $this->ltiUser->getIsTeacher());
    }

    /** dataProvider for testGetIdReturnIds */
    public function provideFakeIds(){
        return array(
            array(1),
            array(5),
            array(10)
        );
    }

    /** dataProvider for testSetRejectNonObjectValues */
    public function provideNonObjectValues(){
        return array(
            array('1'),
            array(1),
            array(['some value']),
        );
    }

    /** dataProvider for testSetUserAcceptsUserObject */
    public function provideUserObjectValues(){
        // set random value just to not get empty user object
        $user1 = new User;
        $user1->setFirstname('some firstname 1');
        $user2 = new User ;
        $user2->setFirstname('some firstname 2');
        $user3 = new User ;
        $user3->setFirstname('some firstname 3');
        
        return array(
            array($user1),
            array($user1),
            array($user2),
        );
    }

    /**
     * dataProvider for testSetLtiToolAcceptsLtiToolObjectValue
     */
    public function provideLtiToolObjectValues(){
        $ltiConsumer1 = new LtiConsumer;
        $ltiConsumer1->setIssuer('fake issuer 1');
        $ltiConsumer2 = new LtiConsumer;
        $ltiConsumer2->setIssuer('fake issuer 2');
        $ltiConsumer3 = new LtiConsumer;
        $ltiConsumer3->setIssuer('fake issuer 3');

        return array(
            array($ltiConsumer1),
            array($ltiConsumer2),
            array($ltiConsumer3),
        );
    }

    /** dataProvider for testSetLtiUserIdCanNotBeNull */
    public function provideInvalidValues(){
        $someObject = new \stdClass();
        return array(
            array(null),
            array(''),
            array(0),
            array([]),
            array($someObject)
        );
    }

    /** dataProvider for 
     * => testSetLtiUserAcceptsValidId
     * => testSetCourseIdAcceptsValidId
     */
    public function provideValidLtiUserOrCourseIds(){
        return array(
            array(1),
            array(10),
            array('10'),
            array('1')
        );
    }

    public function provideInvalidBooleanValues(){
        $someObject = new \stdClass();
        return array(
            array(null),
            array(''),
            array([]),
            array($someObject)
        );
    }

    /** dataProvider for testSetIsTeacherAcceptsValidBooleanValue */
    public function provideValidBooleanValues(){
        return array(
            array(true),
            array(false)
        );
    }
}