<?php

namespace Objectiphy\Objectiphy\Tests\IntegrationTests;

use Objectiphy\Objectiphy\Config\ConfigEntity;
use Objectiphy\Objectiphy\Config\ConfigOptions;
use Objectiphy\Objectiphy\Exception\ObjectiphyException;
use Objectiphy\Objectiphy\Query\QB;
use Objectiphy\Objectiphy\Tests\Entity\TestCustomer;
use Objectiphy\Objectiphy\Tests\Entity\TestEmployee;
use Objectiphy\Objectiphy\Tests\Entity\TestOrder;
use Objectiphy\Objectiphy\Tests\Entity\TestPet;
use Objectiphy\Objectiphy\Tests\Entity\TestChild;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestUser;

class DeleteTest extends IntegrationTestBase
{
    public function setUp(): void
    {
        parent::setUp();
        $this->objectRepository->setClassName(TestParent::class);
    }

    public function testDeleteEntity()
    {
        $this->testName = 'Delete entity';
        $this->objectRepository->setClassName(TestUser::class);
        $countUsers = $this->objectRepository->count();
        $user = $this->objectRepository->find(1);
        $deleteCount = $this->objectRepository->deleteEntity($user);
        $this->assertEquals(1, $deleteCount);
        $newCountUsers = $this->objectRepository->count();
        $this->assertEquals($countUsers - 1, $newCountUsers);
    }

    public function testDeleteEntities()
    {
        $this->testName = 'Delete entities';
        $this->objectRepository->setClassName(TestUser::class);
        $countUsers = $this->objectRepository->count();
        $users = $this->objectRepository->findBy(['id' => ['operator' => 'IN', 'value' => [1, 2]]]);
        $deleteCount = $this->objectRepository->deleteEntities($users);
        $this->assertEquals(2, $deleteCount);
        $newCountUsers = $this->objectRepository->count();
        $this->assertEquals($countUsers - 2, $newCountUsers);
    }

    public function testDeleteParentOwner()
    {
        $this->testName = 'Delete where parent is owner';
        $parent = $this->objectRepository->find(1);

        //Remove a child entity where the parent owns the relationship
        $parent->setUser(null);
        $this->objectRepository->saveEntity($parent);
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
        $parent->setChild(
            null
        ); //This sets the parent property of the existing child to null (but only because we told it to - Objectiphy does not handle this).
        $this->objectRepository->saveEntity($emancipatedChild);
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
        $this->objectRepository->clearCache(); //Necessary due to no persistence
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals(4, count($bereavedParent->pets));

        //While we're here (not really a delete test, this) update a child object on a one-to-many lazy-loaded relationship
        $bereavedParent->getPets()[1]->name = 'Slartibartfast';
        $this->objectRepository->saveEntity($bereavedParent);
        $this->objectRepository->clearCache(); //Necessary to get the new ordering
        $parentWithNewPetName = $this->objectRepository->find(1);
        $this->assertEquals(
            'Slartibartfast',
            $parentWithNewPetName->getPets()[2]->name
        ); //As they are ordered by name, it will have moved to last place!
    }

    public function testOrphanRemoval()
    {
        $this->testName = 'Orphan removal';
        
        // Test that orphans are only removed on a many-to-one relationship if no other parent has the child
        // (many to many is tested separately in ManyToManyTest.php)

        //Remove Olivia as the union rep from Carmen - should not delete Olivia (because Carruthers still has her as union rep)
        //Then remove Olivia as the union rep from Carruthers - should delete Olivia (because nobody has Olivia as union rep)
        $this->objectRepository->setClassName(TestEmployee::class);
        $carmen = $this->objectRepository->find(8);
        $carmen->unionRep = null;
        $this->objectRepository->saveEntity($carmen);
        $refreshedOlivia = $this->objectRepository->find(7);
        $this->assertNotNull($refreshedOlivia);
        $this->assertEquals('Olivia', $refreshedOlivia->name);
        $carruthers = $this->objectRepository->find(9);
        $carruthers->unionRep = null;
        $this->objectRepository->saveEntity($carruthers);
        $zombieOlivia = $this->objectRepository->find(7);
        $this->assertNull($zombieOlivia);

        //Test orphan removal in a one to one relationship (no need to check for other parents)
        $this->objectRepository->setClassName(TestParent::class);
        $userlessParent = $this->objectRepository->find(2);
        $removedUserId = $userlessParent->getUser()->getId();
        $userlessParent->setUser(null);
        $insertCount = 0;
        $updateCount = 0;
        $deleteCount = 0;
        $this->objectRepository->saveEntity($userlessParent, null, false, $insertCount, $updateCount, $deleteCount);
        $this->assertEquals(0, $insertCount);
        $this->assertEquals(1, $updateCount);
        $this->assertEquals(1, $deleteCount);
        $refreshedParent = $this->objectRepository->find(2);
        $this->assertEquals(null, $refreshedParent->user);
        $this->objectRepository->setClassName(TestUser::class);
        $zombieUser = $this->objectRepository->find($removedUserId);
        $this->assertNull($zombieUser);

        $this->objectRepository->setClassName(TestParent::class);
        $bereavedParent = $this->objectRepository->find(1);
        //Remove a one to many child entity which has orphan removal - it should be deleted
        $pets = $bereavedParent->getPets();
        $elderlyPetId = $pets[count($pets) - 1]->id;
        $pets->offsetUnset(count($pets) - 1); //Euthanised! :(
        $this->objectRepository->saveEntity($bereavedParent);
        //Check that we only have three pets
        $bereavedParent = $this->objectRepository->find(1);
        $remainingPets = $bereavedParent->pets;
        $this->assertEquals(3, count($remainingPets));
        //Check that the euthanised pet is really dead
        $this->objectRepository->setClassName(TestPet::class);
        $zombiePet = $this->objectRepository->find($elderlyPetId);
        $this->assertEquals(null, $zombiePet);
        $this->objectRepository->setClassName(TestParent::class);

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
        $this->objectRepository->setClassName(TestChild::class);
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
        $this->objectRepository->setClassName(TestPet::class);
        $deadPet = $this->objectRepository->find(13);
        $this->assertNull($deadPet);

        //Test orphan removal example from docs (first without orphan removal, then with)
        $this->objectRepository->setClassName(TestCustomer::class);
        $customer = $this->objectRepository->find(3);
        $this->assertEquals(2, count($customer->orders));
        $firstOrderId = $customer->orders[0]->id;
        unset($customer->orders[0]);

        $this->objectRepository->saveEntity($customer);
        $refreshedCustomer = $this->objectRepository->find(3);
        $this->assertEquals(1, count($refreshedCustomer->orders));
        $this->assertNotEquals($firstOrderId, reset($refreshedCustomer->orders)->id);
        $this->objectRepository->setClassName(TestOrder::class);
        $orphan = $this->objectRepository->find($firstOrderId);
        $this->assertNotNull($orphan);
        $this->assertEquals($firstOrderId, $orphan->id);

        //Now delete the parent and check orphans are not removed (later turn on orphan removal and check they are removed)
        $this->objectRepository->setClassName(TestCustomer::class);
        $customer2 = $this->objectRepository->find(1);
        $this->assertEquals(2, count($customer2->orders));
        $orderIds = [$customer2->orders[0]->id, $customer2->orders[1]->id];
        $this->objectRepository->deleteEntity($customer2);
        $this->objectRepository->setClassName(TestOrder::class);
        $orphans = $this->objectRepository->findBy($orderIds);
        $this->assertTrue(in_array($orphans[0]->id, $orderIds));
        $this->assertTrue(in_array($orphans[1]->id, $orderIds));

        $this->setUp(); //We need our orders back!
        $this->objectRepository->setEntityConfigOption(
            TestCustomer::class,
            ConfigEntity::RELATIONSHIP_OVERRIDES,
            ['orders' => ['orphanRemoval' => true]]
        );
        $this->objectRepository->setClassName(TestCustomer::class);
        $customer = $this->objectRepository->find(3);
        $this->assertEquals(2, count($customer->orders));
        $firstOrderId = $customer->orders[0]->id;
        unset($customer->orders[0]);
        $this->objectRepository->saveEntity($customer);
        $refreshedCustomer = $this->objectRepository->find(3);
        $this->assertEquals(1, count($refreshedCustomer->orders));
        $this->assertNotEquals($firstOrderId, reset($refreshedCustomer->orders)->id);
        $this->objectRepository->setClassName(TestOrder::class);
        $orphan = $this->objectRepository->find($firstOrderId);
        $this->assertNull($orphan);

        //Check orphans are removed when parent deleted
        $this->objectRepository->setClassName(TestCustomer::class);
        $customer2 = $this->objectRepository->find(1);
        $this->assertEquals(2, count($customer2->orders));
        $orderIds = [$customer2->orders[0]->id, $customer2->orders[1]->id];
        $this->objectRepository->deleteEntity($customer2);
        $this->objectRepository->setClassName(TestOrder::class);
        $orphans = $this->objectRepository->findBy($orderIds);
        $this->assertEmpty($orphans);
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
        $this->objectRepository->setClassName(TestChild::class);
        $orphan = $this->objectRepository->find($childAtRiskId);
        $this->assertEquals($childAtRiskId, $orphan->getId()); //Make sure child was loaded
        $this->assertEquals(null, $orphan->getParent()); //Make sure child is an orphan
        //Will need to hit the database directly to see if foreign key is null (as objectiphy uses the pk of the joined table)
        $sql = "SELECT parent_id FROM child WHERE id = 2";
        $stm = $this->pdo->prepare($sql);
        $stm->execute();
        $parentId = $stm->fetchColumn();
        $this->assertEquals(null, $parentId);
        $this->objectRepository->setClassName(TestPet::class);
        foreach ($petsToDie as $deadPet) {
            $zombiePet = $this->objectRepository->find($deadPet->id);
            $this->assertEquals(null, $zombiePet);
        }

        //Delete using an ObjectReference
        $alivePet = $this->objectRepository->find(2);
        $this->assertEquals(2, $alivePet->id);
        $petReference = $this->objectRepository->getObjectReference(TestPet::class, ['id' => 2]);
        $this->objectRepository->deleteEntity($petReference);
        $deadPet = $this->objectRepository->find(2);
        $this->assertEquals(null, $deadPet);

        //Test cascade example from docs (first with just the relationship removed, then with the parent entity deleted)
        $this->objectRepository->setEntityConfigOption(
            TestCustomer::class,
            ConfigEntity::RELATIONSHIP_OVERRIDES,
            ['orders' => ['cascadeDeletes' => true]]
        );
        $this->objectRepository->setClassName(TestCustomer::class);
        $customer = $this->objectRepository->find(3);
        $this->assertEquals(2, count($customer->orders));
        $firstOrderId = $customer->orders[0]->id;
        unset($customer->orders[0]);
        $this->objectRepository->saveEntity($customer);
        $refreshedCustomer = $this->objectRepository->find(3);
        $this->assertEquals(1, count($refreshedCustomer->orders));
        $this->assertNotEquals($firstOrderId, reset($refreshedCustomer->orders)->id);
        $this->objectRepository->setClassName(TestOrder::class);
        $orphan = $this->objectRepository->find($firstOrderId);
        $this->assertNotNull($orphan); //Should not have been deleted, as it is not a cascade
        $this->assertEquals($firstOrderId, $orphan->id);

        //Check that it cascades when parent deleted
        $this->objectRepository->setClassName(TestCustomer::class);
        $customer2 = $this->objectRepository->find(1);
        $this->assertEquals(2, count($customer2->orders));
        $orderIds = [$customer2->orders[0]->id, $customer2->orders[1]->id];
        $this->objectRepository->deleteEntity($customer2);
        $this->objectRepository->setClassName(TestOrder::class);
        $orphans = $this->objectRepository->findBy($orderIds);
        $this->assertEmpty($orphans);
    }

    public function testSuppressedDeleteParentOwner()
    {
        $this->testName = 'Suppressed deletes where parent is owner';
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_RELATIONSHIPS, true);
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_ENTITIES, true);

        $parent = $this->objectRepository->find(1);
        $user = $parent->getUser();
        //Remove a child entity where the parent owns the relationship
        $parent->setUser(null);
        $this->objectRepository->saveEntity($parent);
        $this->objectRepository->clearCache(); //Necessary to cause user to be re-loaded after suppressed delete
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals($user, $bereavedParent->getUser());
    }

    public function testSuppressedDeleteChildOwner()
    {
        $this->testName = 'Suppressed deletes where child is owner';
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_RELATIONSHIPS, true);
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_ENTITIES, true);
        $parent = $this->objectRepository->find(1);
        //Remove a child entity where the child owns the relationship (essentially, we just update the child,
        //so it is treated the same as if it were the parent and the parent were the child).
        $emancipatedChild = $parent->getChild();
        $parent->setChild(
            null
        ); //This sets the parent property of the existing child to null (but only because we told it to - Objectiphy does not handle this).
        $this->objectRepository->saveEntity($emancipatedChild);
        $this->objectRepository->clearCache(); //Necessary to re-load as the delete was suppressed.
        $bereavedParent = $this->objectRepository->find(1);
        $this->assertEquals($emancipatedChild->getId(), $bereavedParent->getChild()->getId());
    }

    public function testSuppressedOrphanRemoval()
    {
        $this->testName = 'Suppressed orphan removal';
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_RELATIONSHIPS, true);
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_ENTITIES, true);
        $bereavedParent = $this->objectRepository->find(1);
        //Remove a child entity which has orphan removal - it should NOT be deleted
        $pets = $bereavedParent->getPets();
        $elderlyPetId = $pets[0]->id;
        $pets->offsetUnset(count($pets) - 1); //Euthanised! :(
        $bereavedParent->setPets($pets);
        $this->objectRepository->saveEntity($bereavedParent);

        //Check that the euthanised pet is not really dead
        $this->objectRepository->setClassName(TestPet::class);
        $zombiePet = $this->objectRepository->find($elderlyPetId);
        $this->assertEquals($elderlyPetId, $zombiePet->id);
        $this->objectRepository->setClassName(TestParent::class);

        //And still belongs to the parent
        $this->objectRepository->clearCache(); //Necessary to re-load as the orphan removal was suppressed.
        $bereavedParent = $this->objectRepository->find(1);
        $remainingPets = $bereavedParent->pets;
        $this->assertEquals(4, count($remainingPets));
    }

    public function testSuppressedCascadeDeletes()
    {
        $this->testName = 'Suppressed cascade deletes';
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_RELATIONSHIPS, true);
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_ENTITIES, true);
        //Delete a parent entity without cascading (child should NOT be orphaned, and pets should NOT be killed, as
        //parent delete will fail).
        $suicidalParent = $this->objectRepository->find(2);
        $childAtRiskId = $suicidalParent->getChild()->getId();
        $petsToDie = $suicidalParent->getPets();
        $this->objectRepository->deleteEntity($suicidalParent, false, false);
        $zombieParent = $this->objectRepository->find(2);
        $this->assertEquals($suicidalParent->id, $zombieParent->id); //Make sure parent is not really dead
        $this->objectRepository->setClassName(TestChild::class);
        $orphan = $this->objectRepository->find($childAtRiskId);
        $this->assertEquals($childAtRiskId, $orphan->getId()); //Make sure child was loaded
        $this->assertEquals($zombieParent->getId(), $orphan->getParent()->getId()); //Make sure child is not an orphan
        foreach ($petsToDie as $deadPet) {
            $this->objectRepository->setClassName(TestPet::class);
            $zombiePet = $this->objectRepository->find($deadPet->id); //zombie pet should have a parent!
            $this->assertEquals($deadPet->id, $zombiePet->id);
            $this->assertEquals($deadPet->parent->id, $zombiePet->parent->id);
        }
    }

    public function testSuppressedException()
    {
        $this->testName = 'Suppressed exception';
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_RELATIONSHIPS, true);
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_DELETE_ENTITIES, true);
        //Check for exception when attempting to delete (test execution stops on exceptions, so it has to be last)
        $this->objectRepository->setClassName(TestParent::class);
        $suicidalParent = $this->objectRepository->find(2);
        $this->expectException(ObjectiphyException::class);
        $this->objectRepository->deleteEntity($suicidalParent);
    }

    public function testDeleteEntityNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testDeleteEntity();
    }

    public function testDeleteEntitiesNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testDeleteEntities();
    }

    public function testDeleteParentOwnerNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testDeleteParentOwner();
    }

    public function testDeleteChildOwnerNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testDeleteChildOwner();
    }

    public function testDeleteAllChildrenNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testDeleteAllChildren();
    }

    public function testOrphanRemovalNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testOrphanRemoval();
    }

    public function testCascadingNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testCascading();
    }

    public function testSuppressedDeleteParentOwnerNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testSuppressedDeleteParentOwner();
    }

    public function testSuppressedDeleteChildOwnerNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testSuppressedDeleteChildOwner();
    }

    public function testSuppressedOrphanRemovalNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testSuppressedOrphanRemoval();
    }

    public function testSuppressedCascadeDeletesNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testSuppressedCascadeDeletes();
    }

    public function testSuppressedExceptionNoCache()
    {
        $this->objectRepository->setConfigOption(ConfigOptions::DISABLE_ENTITY_CACHE, true);
        $this->testSuppressedException();
    }
}
