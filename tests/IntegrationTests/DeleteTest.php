<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Tests\Entity\TestPet;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;

class DeleteTest extends IntegrationTestBase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->objectRepository->setClassName(TestParent::class);
    }

    public function testDeleteParentOwner()
    {
        $this->testName = 'Delete where parent is owner';
        $parent = $this->objectRepository->find(1);

        //Remove a child entity where the parent owns the relationship
        $parent->setUser(null);
        $this->objectRepository->saveEntity($parent);
        $this->objectRepository->clearCache();
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals(null, $bereavedParent->getUser());
    }
    
    public function testDeleteChildOwner()
    {
        $this->testName = 'Delete where child is owner';
        $parent = $this->objectRepository->find(1);
        //Remove a child entity where the child owns the relationship (essentially, we just update the child,
        //so it is treated the same as if it were the parent and the parent were the child).
        $emancipatedChild = $parent->getChild();
        $parent->setChild(null); //This sets the parent property of the existing child to null (but only because we told it to - Objectiphy does not handle this).
        $this->objectRepository->saveEntity($emancipatedChild);
        $this->objectRepository->clearCache();
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals(null, $bereavedParent->getChild());

        //Emancipated child should still exist, as it does not have orphan removal
        $this->objectRepository->setClassName(get_class($emancipatedChild));
        $reloadedChild = $this->objectRepository->find($emancipatedChild->getId());
        $this->assertEquals($emancipatedChild->getName(), $reloadedChild->getName());
        $this->assertGreaterThan(0, strlen($reloadedChild->getName()));
        $this->objectRepository->setClassName(get_class($parent)); //Should work even though $parent is a proxy object
    }
    
    public function testDeleteAllChildren()
    {
        $this->testName = 'Delete all children';
        $bereavedParent = $this->objectRepository->find(1);
        //Remove all children from parent and check functioning of isset
        $isset = isset($bereavedParent->pets);
        $this->assertEquals(true, $isset);
        $bereavedParent->setPets(null);
        $issetNow = isset($bereavedParent->pets);
        $this->assertEquals(false, $issetNow);

        //We didn't persist that, so pets should all still be there...
        $this->objectRepository->clearCache();
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals(4, count($bereavedParent->pets));

        //While we're here (not really a delete test, this) update a child object on a one-to-many lazy-loaded relationship
        $bereavedParent->getPets()[1]->name = 'Slartibartfast';
        $this->objectRepository->saveEntity($bereavedParent);
        $this->objectRepository->clearCache();
        $parentWithNewPetName = $this->objectRepository->find(1);
        $this->assertEquals('Slartibartfast',
            $parentWithNewPetName->getPets()[2]->name); //As they are ordered by name, it will have moved to last place!
    }
    
    public function testOrphanRemoval()
    {
        $this->testName = 'Orphan removal';
        $bereavedParent = $this->objectRepository->find(1);
        //Remove a child entity which has orphan removal - it should be deleted
        $pets = $bereavedParent->getPets();
        $elderlyPetId = $pets[count($pets) - 1]->id;
        $pets->offsetUnset(count($pets) - 1); //Euthanised! :(
        $bereavedParent->setPets($pets);
        $this->objectRepository->saveEntity($bereavedParent);
        $this->objectRepository->clearCache();
        //Check that we only have three pets
        $bereavedParent = $this->objectRepository->find(1);
        $remainingPets = $bereavedParent->pets;
        $this->assertEquals(3, count($remainingPets));
        //Check that the euthanised pet is really dead
        $this->objectRepository->setEntityClassName(TestPet::class);
        $zombiePet = $this->objectRepository->find($elderlyPetId);
        $this->assertEquals(null, $zombiePet);
        $this->objectRepository->setEntityClassName(TestParent::class);

        //Replace a child of an existing entity that is a property of a new entity
        //(ensure new entity inserted, existing entity updated, orphan entity deleted)
        //Eg. create a new child, assign an existing parent to it, update a property on
        //the parent, remove one of the parent's pets, then add a new pet, then save the child.
        //Removed pet should be deleted, new pet should be inserted, child should be inserted, parent should be updated.
        $existingParent = $this->objectRepository->find(3);
        $existingPets = $existingParent->getPets();
        $this->assertEquals('Trixie', $existingPets[1]->name); //Make sure the pet we want to replace is there
        $this->assertEquals(13, $existingPets[1]->id);
        $newChild = new TestChild();
        $newChild->setName('Arthur');
        $newChild->setParent($existingParent);
        $newChild->getParent()->setName('Updated Parent Name');
        $newPet = new TestPet();
        $newPet->type = 'dog';
        $newPet->name = 'Sam';
        $newPet->weightInGrams = 12689;
        $parentPets =& $newChild->getParent()->getPets();
        $parentPets[1] = $newPet;
        $this->objectRepository->saveEntity($newChild);
        $this->objectRepository->setEntityClassName(TestChild::class);
        $refreshedChild = $this->objectRepository->find($newChild->getId());
        $this->assertEquals('Arthur', $refreshedChild->getName());
        $this->assertEquals($existingParent->id, $refreshedChild->getParent()->id);
        $this->assertEquals('Updated Parent Name', $refreshedChild->getParent()->name);
        $refreshedPets = $refreshedChild->getParent()->pets;
        $this->assertEquals(2, count($refreshedPets));
        $petNames = [$refreshedChild->getParent()->pets[0]->name, $refreshedChild->getParent()->pets[1]->name];
        $this->assertContains('Sam', $petNames);
        $this->assertContains('Spot', $petNames);
        //Make sure orphan was deleted
        $this->objectRepository->setEntityClassName(TestPet::class);
        $deadPet = $this->objectRepository->find(13);
        $this->assertNull($deadPet);
    }
    
    public function testCascading()
    {
        $this->testName = 'Cascade deletes';
        //Delete a parent entity: without cascading, child should be orphaned; with cascading, child should be deleted.
        $suicidalParent = $this->objectRepository->find(2);
        $childAtRiskId = $suicidalParent->getChild()->getId();
        $petsToDie = $suicidalParent->getPets();
        $this->objectRepository->deleteEntity($suicidalParent);
        $zombieParent = $this->objectRepository->find(2);
        $this->assertEquals(null, $zombieParent); //Make sure parent is really dead
        $this->objectRepository->setEntityClassName(TestChild::class);
        $orphan = $this->objectRepository->find($childAtRiskId);
        $this->assertEquals($childAtRiskId, $orphan->getId()); //Make sure child was loaded
        $this->assertEquals(null, $orphan->getParent()); //Make sure child is an orphan
        $this->objectRepository->setEntityClassName(TestPet::class);
        foreach ($petsToDie as $deadPet) {
            $zombiePet = $this->objectRepository->find($deadPet->id);
            $this->assertEquals(null, $zombiePet);
        }

        //Delete using an ObjectReference
        $alivePet = $this->objectRepository->find(2);
        $this->assertEquals(2, $alivePet->id);
        $petReference = $this->objectRepository->getObjectReference(TestPet::class, 2);
        $this->objectRepository->deleteEntity($petReference);
        $deadPet = $this->objectRepository->find(2);
        $this->assertEquals(null, $deadPet);
    }

    public function testSuppressedDeleteParentOwner()
    {
        $this->testName = 'Suppressed deletes where parent is owner';
        $this->objectRepository->setDisableDeletes();

        $parent = $this->objectRepository->find(1);
        $user = $parent->getUser();
        //Remove a child entity where the parent owns the relationship
        $parent->setUser(null);
        $this->objectRepository->saveEntity($parent);
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals($user, $bereavedParent->getUser());
    }
    
    public function testSuppressedDeleteChildOwner()
    {
        $this->testName = 'Suppressed deletes where child is owner';
        $this->objectRepository->setDisableDeletes();
        $parent = $this->objectRepository->find(1);
        //Remove a child entity where the child owns the relationship (essentially, we just update the child,
        //so it is treated the same as if it were the parent and the parent were the child).
        $emancipatedChild = $parent->getChild();
        $parent->setChild(null); //This sets the parent property of the existing child to null (but only because we told it to - Objectiphy does not handle this).
        $this->objectRepository->saveEntity($emancipatedChild);
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals($emancipatedChild->getId(), $bereavedParent->getChild()->getId());
    }
    
    public function testSuppressedOrphanRemoval()
    {
        $this->testName = 'Suppressed orphan removal';
        $this->objectRepository->setDisableDeletes();
        $bereavedParent = $this->objectRepository->find(1);
        //Remove a child entity which has orphan removal - it should NOT be deleted
        $pets = $bereavedParent->getPets();
        $elderlyPetId = $pets[0]->id;
        $pets->offsetUnset(count($pets) - 1); //Euthanised! :(
        $bereavedParent->setPets($pets);
        $this->objectRepository->saveEntity($bereavedParent);

        //Check that the euthanised pet is not really dead
        $this->objectRepository->setEntityClassName(TestPet::class);
        $zombiePet = $this->objectRepository->find($elderlyPetId);
        $this->assertEquals($elderlyPetId, $zombiePet->id);
        $this->objectRepository->setEntityClassName(TestParent::class);

        //And still belongs to the parent
        $bereavedParent = $this->objectRepository->find(1);
        $remainingPets = $bereavedParent->pets;
        $this->assertEquals(4, count($remainingPets));
    }
    
    public function testSuppressedCascadeDeletes()
    {
        $this->testName = 'Suppressed cascade deletes';
        $this->objectRepository->setDisableDeletes();
        //Delete a parent entity without cascading (child should NOT be orphaned, and pets should NOT be killed, as
        //parent delete will fail).
        $suicidalParent = $this->objectRepository->find(2);
        $childAtRiskId = $suicidalParent->getChild()->getId();
        $petsToDie = $suicidalParent->getPets();
        $this->objectRepository->deleteEntity($suicidalParent, false, false);
        $zombieParent = $this->objectRepository->find(2);
        $this->assertEquals($suicidalParent->id, $zombieParent->id); //Make sure parent is not really dead
        $this->objectRepository->setEntityClassName(TestChild::class);
        $orphan = $this->objectRepository->find($childAtRiskId);
        $this->assertEquals($childAtRiskId, $orphan->getId()); //Make sure child was loaded
        $this->assertEquals($zombieParent->getId(), $orphan->getParent()->getId()); //Make sure child is not an orphan
        foreach ($petsToDie as $deadPet) {
            $this->objectRepository->setEntityClassName(TestPet::class);
            $zombiePet = $this->objectRepository->find($deadPet->id); //zombie pet should have a parent!
            $this->assertEquals($deadPet->id, $zombiePet->id);
            $this->assertEquals($deadPet->parent->id, $zombiePet->parent->id);
        }
    }
    
    public function testSuppressedException()
    {
        $this->testName = 'Suppressed exception';
        $this->objectRepository->setDisableDeletes();
        //Check for exception when attempting to delete (test execution stops on exceptions, so it has to be last)
        $this->objectRepository->setEntityClassName(TestParent::class);
        $suicidalParent = $this->objectRepository->find(2);
        $this->expectException(ObjectiphyException::class);
        $this->objectRepository->deleteEntity($suicidalParent);
    }
}
