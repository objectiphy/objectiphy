<?php

namespace Objectiphy\Objectiphy;

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\DocParser;
use Objectiphy\Objectiphy\Tests\Entity\TestContact;
use Objectiphy\Objectiphy\Tests\Entity\TestEmployee;
use Objectiphy\Objectiphy\Tests\Entity\TestParent;
use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestWeirdPropertyNames;

class ObjectBinderTest extends \PHPUnit_Framework_TestCase
{
    /** @var ObjectBinder */
    protected $object;
    /** @var ObjectMapper */
    protected $objectMapper;
    /** @var ProxyFactory */
    protected $proxyFactory;

    public function setUp()
    {
        $docParser = new DocParser();
        $annotationReader = new AnnotationReader($docParser);
        $mappingProvider = new MappingProviderAnnotation($annotationReader);
        $this->objectMapper = new ObjectMapper($mappingProvider);
        $this->objectMapper->setEagerLoad(true, true);
        $repositoryFactory = $this->getMockBuilder(RepositoryFactory::class)->disableOriginalConstructor()->getMock();
        $repository = $this->getMockBuilder(ObjectRepository::class)->disableOriginalConstructor()->getMock();
        $repositoryFactory->expects($this->any())->method('createRepository')->will($this->returnValue($repository));
        $this->object = new ObjectBinder($this->objectMapper, $repositoryFactory, new ProxyFactory());
        $repository->expects($this->any())->method('getObjectBinder')->will($this->returnValue($this->object));
        $this->object->setEntityClassName(TestPolicy::class);
    }

    public function testSetEntityClassName()
    {
        $mockObjectMapper = $this->getMockBuilder(ObjectMapper::class)->disableOriginalConstructor()->getMock();
        $mockObjectMapper->expects($this->once())->method('setEntityClassName')->with(TestParent::class, ObjectRepository::class);
        $this->object->objectMapper = $mockObjectMapper;
        $this->object->setEntityClassName(TestParent::class);
    }

    public function testSetTableOverride()
    {
        $mockObjectMapper = $this->getMockBuilder(ObjectMapper::class)->disableOriginalConstructor()->getMock();
        $mockObjectMapper->expects($this->once())->method('setTableOverride')->with('className', 'tableName');
        $this->object->objectMapper = $mockObjectMapper;
        $this->object->setTableOverride('className', 'tableName');
    }

    public function testSetSerializationGroups()
    {
        $mockObjectMapper = $this->getMockBuilder(ObjectMapper::class)->disableOriginalConstructor()->getMock();
        $mockObjectMapper->expects($this->once())->method('setSerializationGroups')->with(['group1', 'group2'], true);
        $this->object->objectMapper = $mockObjectMapper;
        $this->object->setSerializationGroups(['group1', 'group2']);
    }

    public function testSetChildRepositoryClass()
    {
        $mockObjectMapper = $this->getMockBuilder(ObjectMapper::class)->disableOriginalConstructor()->getMock();
        $mockObjectMapper->expects($this->once())->method('setChildRepositoryClass')->with('childClass', 'repoClass', 'array');
        $this->object->objectMapper = $mockObjectMapper;
        $this->object->setChildRepositoryClass('childClass', 'repoClass');
    }

    public function testBindRowToEntity()
    {
        $row = [
            'id'=>12345,
            'contact_id'=>54321,
            'vehicle_id'=>112233,
            'contact_first_name'=>'Emmet',
            'last_name'=>'Brown',
            'vehicle_regno'=>'OUTATIME',
            'vehicle_policy_id'=>12345,
            'make'=>'DeLorean',
            'telematics_box_imei'=>'1885',
            'security_pass_id'=>123,
            'nonpkchild_nebulousIdentifier'=>'cloudy',
        ];
        $policy = $this->object->bindRowToEntity($row);
        $this->assertEquals('Brown', $policy->contact->lastName);
        $this->assertEquals('OUTATIME', $policy->vehicle->regNo);
        $this->assertEquals('1885', $policy->vehicle->telematicsBox->unitId);
        $this->assertEquals($policy, $policy->vehicle->policy);
        $this->assertEquals('Emmet', $policy->vehicle->policy->vehicle->policy->contact->firstName);
    }

    public function testBindRowToEntityWeird()
    {
        $row = [
            'primary_key' => 1,
            'firstname' => 'Dave',
            'last_name' => 'Lister',
            'some_random_event_datetime' => '1988-02-15 21:00:00',
            'a_very_very_inconsistentnamingconvention_here' => 'The End',
            'line1' => '13 Nowhere Close',
            'line2' => 'Smuttleton',
            'town' => 'Frogsborough',
            'postcode' => 'FR1 1OG',
            'countrycode' => 'GB',
            'countrydescription' => 'United Kingdom',
            'test_user_id' => 1,
            'test_user_type' => 'branch',
            'test_user_email' => 'danger.mouse@example.com'
        ];
        $this->object->setEntityClassName(TestWeirdPropertyNames::class);
        $weirdo = $this->object->bindRowToEntity($row);
        $this->assertEquals('Dave', $weirdo->firstName);
        $this->assertEquals('The End', $weirdo->a_VERY_Very_InconsistentnamingConvention_here);
        $this->assertEquals('1988-02-15 21:00:00', $weirdo->some_random_event_dateTime->format('Y-m-d H:i:s'));
        $this->assertEquals('United Kingdom', $weirdo->address_with_underscores->getCountryDescription());
        $this->assertEquals('danger.mouse@example.com', $weirdo->test_user->getEmail());
    }

    public function testBindRowToEntityRecursive()
    {
        $row = [
            'id'=>654321,
            'name'=>'Richard Dawkins',
            'mentor_id'=>111111,
            'mentor_name'=>'Bertrand Russell',
            'mentor_mentor_id'=>333333,
            'mentor_mentee_id'=>654321,
            'mentor_unionrep_id'=>333333,
            'mentor_position_positionkey'=>'A',
            'mentor_position_positionvalue'=>'AAA',
            'mentor_position_positiondescription'=>'AAAAAA',
            'mentee_id'=>222222,
            'mentee_name'=>'Ricky Gervais',
            'unionrep_id'=>333333,
            'unionrep_name'=>'Sam Harris'
        ];
        
        $this->object->setEntityClassName(TestEmployee::class);
        $employee = $this->object->bindRowToEntity($row);
        $this->assertEquals('Richard Dawkins', $employee->name);
        $this->assertEquals('Bertrand Russell', $employee->mentor->name);
        $this->assertEquals('Richard Dawkins', $employee->mentor->mentee->name);
        $this->assertEquals('AAAAAA', $employee->mentor->position->positionDescription);

        //Check lazy loading/proxy
        $this->assertInstanceOf(EntityProxyInterface::class, $employee);
        $this->assertEquals(true, $employee->isChildAsleep('mentee'));
        $this->assertEquals(false, $employee->isChildAsleep('mentor'));
    }

    public function testBindRowsToEntities()
    {
        $rows = [
            [
                'id'=>12345,
                'objectiphy_test_policy_id'=>99999,
                'contact_id'=>54321,
                'vehicle_id'=>112233,
                'contact_first_name'=>'Emmet',
                'objectiphy_test_contact_title_code'=>'008',
                'objectiphy_test_contact_title'=>'Doctor',
                'last_name'=>'Brown',
                'vehicle_regno'=>'OUTATIME',
                'vehicle_policy_id'=>99999,
                'make'=>'DeLorean',
                'telematics_box_imei'=>'1885',
                'security_pass_id'=>124,
                'non_pk_child_nebulous_identifier'=>'cirrus'
            ],
            [
                'id'=>54322,
                'policy_id'=>88888,
                'contact_id'=>54322,
                'vehicle_id'=>112234,
                'contact_first_name'=>'Biff',
                'title_code'=>'003',
                'title'=>'Mr',
                'last_name'=>'Tannen',
                'vehicle_regno'=>'Hi2UrMom',
                'vehicle_policy_id'=>88888,
                'telematics_box_imei'=>'2015',
                'security_pass_id'=>125,
                'non_pk_child_nebulous_identifier'=>'cumulus'
            ],
            [
                'id'=>12346,
                'contact_id'=>54323,
                'vehicle_id'=>112235,
                'contact_first_name'=>'Lorraine',
                'title_code'=>'006',
                'title'=>'Miss',
                'last_name'=>'Baines',
                'vehicle_regno'=>'HillValley',
                'vehicle_policy_id'=>12346,
                'telematics_box_imei'=>'1985',
                'security_pass_id'=>126,
                'non_pk_child_nebulous_identifier'=>'noctilucent'
            ]
        ];
        $policies = $this->object->bindRowsToEntities($rows);

        $this->assertEquals(99999, $policies[0]->id);
        $this->assertEquals('Emmet', $policies[0]->contact->firstName);
        $this->assertEquals('Brown', $policies[0]->contact->lastName);
        $this->assertEquals('OUTATIME', $policies[0]->vehicle->regNo);
        $this->assertEquals('1885', $policies[0]->vehicle->telematicsBox->unitId);
        $this->assertEquals('008', $policies[0]->contact->title);
        $this->assertEquals('Doctor', $policies[0]->contact->titleText);

        $this->assertEquals(88888, $policies[1]->id);
        $this->assertEquals('Tannen', $policies[1]->contact->lastName);
        $this->assertEquals('Hi2UrMom', $policies[1]->vehicle->regNo);
        $this->assertEquals('2015', $policies[1]->vehicle->telematicsBox->unitId);
        $this->assertEquals('003', $policies[1]->contact->title);
        $this->assertEquals('Mr', $policies[1]->contact->titleText);

        $this->assertEquals(12346, $policies[2]->id);
        $this->assertEquals(12346, $policies[2]->vehicle->policy->id);
    }

    public function testBindEntityToRows()
    {
        //Insert
        $policy = new TestPolicy();
        $policy->contact = new TestContact();
        $policy->contact->firstName = 'Clara';
        $policy->contact->lastName = 'Clayton';
        $policy->policyNo = 'EQUINE54321';

        $rows = $this->object->bindEntityToRows($policy);
        $this->assertEquals('EQUINE54321', $rows[spl_object_hash($policy)]['data']['`objectiphy_test`.`policy`.`policy_number`']);
        $this->assertEquals('Clayton', $rows[spl_object_hash($policy->contact)]['data']['`objectiphy_test`.`contact`.`last_name`']);
        $this->assertEquals(spl_object_hash($policy), $rows[spl_object_hash($policy->contact)]['parentRowHash']);
        $this->assertEquals('objectiphy_test.policy.contact_id', $rows[spl_object_hash($policy->contact)]['parentForeignKeyColumn']);
    }
}
