<?php

namespace Objectiphy\Objectiphy;

use Objectiphy\Objectiphy\Tests\Entity\TestPolicy;
use Objectiphy\Objectiphy\Tests\Entity\TestVehicle;

class ObjectRepositoryTest extends \PHPUnit_Framework_TestCase
{
    /** @var SqlBuilder */
    protected $sqlBuilder;
    /** @var ObjectMapper */
    protected $objectMapper;
    /** @var ObjectBinder */
    protected $objectBinder;
    /** @var \PDO */
    protected $pdo;
    /** @var StorageInterface */
    protected $storage;
    /** @var ObjectRepository */
    protected $object;
    /** @var array */
    protected $criteria = [
        'contact.firstName'=>'Lorraine',
        'contact.lastName'=>'Baines'
    ];

    public function setUp()
    {
        $this->sqlBuilder = $this->getMockBuilder(SqlBuilder::class)->disableOriginalConstructor()->getMock();
        $this->objectMapper = $this->getMockBuilder(ObjectMapper::class)->disableOriginalConstructor()->getMock();
        $repositoryFactory = $this->getMockBuilder(RepositoryFactory::class)->disableOriginalConstructor()->getMock();
        $repository = $this->getMockBuilder(ObjectRepository::class)->disableOriginalConstructor()->getMock();
        $repositoryFactory->expects($this->any())->method('createRepository')->will($this->returnValue($repository));
        $this->objectBinder = $this->getMockBuilder(ObjectBinder::class)->setConstructorArgs([$this->objectMapper, $repositoryFactory, new ProxyFactory()])->getMock();
        $this->objectBinder->expects($this->any())->method('getObjectMapper')->will($this->returnValue($this->objectMapper));
        $this->pdo = $this->getMockBuilder(\PDO::class)->disableOriginalConstructor()->getMock();
        $this->storage = $this->getMockBuilder(PdoStorage::class)->setConstructorArgs([$this->pdo])->getMock();
        $this->object = new ObjectRepository($this->sqlBuilder, $this->objectBinder, $this->storage);
        $this->object->setEagerLoad(true, true);
        $this->criteria = QB::create()->normalize($this->criteria);
    }

    public function testFind()
    {
        $mock = $this->getMockBuilder(ObjectRepository::class)
            ->setConstructorArgs([$this->sqlBuilder, $this->objectBinder, $this->storage])
            ->setMethods(['findOneBy'])
            ->getMock();
        $this->objectMapper->expects($this->any())->method('getIdProperty')->will($this->returnValue('customIdProperty'));
        $mock->expects($this->once())->method('findOneBy')->with(['customIdProperty'=>12345])->will($this->returnValue('success'));
        $result = $mock->find(12345);
        $this->assertEquals('success', $result);
    }

    public function testFindOneBy()
    {
        //Don't mock test subject - let this test run doFindBy
        $params = ['test.contact.forenames'=>'Lorraine', 'test.contact.surname'=>'Baines'];
        $row = ['rowKey'=>'rowValue'];
        $this->sqlBuilder->expects($this->once())->method('setPagination')->with(null);
        $this->sqlBuilder->expects($this->once())->method('setOrderBy')->with(null);
        $this->sqlBuilder->expects($this->once())->method('getSelectQuery')->with($this->criteria, false, false)->will($this->returnValue('SELECT sql'));
        $this->sqlBuilder->expects($this->once())->method('getQueryParams')->will($this->returnValue($params));
        $this->storage->expects($this->once())->method('fetchResult')->will($this->returnValue($row));
        $this->objectBinder->expects($this->once())->method('bindRowToEntity')->with($row, '')->will($this->returnValue('success'));
        $result = $this->object->findOneBy($this->criteria);
        $this->assertEquals('success', $result);
    }

    public function testFindLatestOneBy()
    {
        $mock = $this->getMockBuilder(ObjectRepository::class)
            ->setConstructorArgs([$this->sqlBuilder, $this->objectBinder, $this->storage])
            ->setMethods(['findLatestBy'])
            ->getMock();
        $mock->expects($this->once())
            ->method('findLatestBy')
            ->with($this->criteria)
            ->will($this->returnValue('success'));
        $result = $mock->findLatestOneBy($this->criteria);
        $this->assertEquals('success', $result);
    }

    public function testFindLatestBy()
    {
        //Don't mock test subject (that would be bullying!) - let this test run doFindBy (but not every permutation)
        $params = ['test.contact.forenames'=>'Lorraine', 'test.contact.surname'=>'Baines'];
        $rows = [['row1Key'=>'row1Value'], ['row2Key'=>'row2Value']];
        $this->sqlBuilder->expects($this->at(1))->method('setPagination')->with(null);
        $this->sqlBuilder->expects($this->at(2))->method('setOrderBy')->with(null);
        $this->sqlBuilder->expects($this->at(3))->method('getSelectQuery')->with($this->criteria, true, true)->will($this->returnValue('SELECT sql'));
        $this->sqlBuilder->expects($this->at(4))->method('getQueryParams')->will($this->returnValue($params));
        $this->storage->expects($this->once())->method('fetchResults')->will($this->returnValue($rows));
        $this->objectBinder->expects($this->any())->method('bindRowsToEntities')->with($rows)->will($this->returnValue($rows));
        $result = $this->object->findLatestBy($this->criteria);
        $this->assertEquals($rows, $result);
    }

    public function testFindBy()
    {
        $mock = $this->getMockBuilder(ObjectRepository::class)
            ->setConstructorArgs([$this->sqlBuilder, $this->objectBinder, $this->storage])
            ->setMethods(['doFindBy'])
            ->getMock();
        $mock->expects($this->once())
            ->method('doFindBy')
            ->with($this->criteria, true, 'policyNo')
            ->will($this->returnValue('success'));
        $result = $mock->findBy($this->criteria, ['contact.lastName'=>'DESC'], 75, 150, 'policyNo');
        $this->assertEquals('success', $result);
        $this->assertEquals(75, $mock->getPagination()->getRecordsPerPage());
        $this->assertEquals(3, $mock->getPagination()->getPageNo());
    }

    public function testFindAll()
    {
        $mock = $this->getMockBuilder(ObjectRepository::class)
            ->setConstructorArgs([$this->sqlBuilder, $this->objectBinder, $this->storage])
            ->setMethods(['findBy'])
            ->getMock();
        $mock->expects($this->once())
            ->method('findBy')
            ->with([], [], null, null, 'keyColumn')
            ->will($this->returnValue('success'));
        $result = $mock->findAll([], 'keyColumn');
        $this->assertEquals('success', $result);
    }

    public function testSaveEntityInsert()
    {
        $entity = new TestPolicy();
        $entity->policyNo = 'TEST123456';
        $row = ['table'=>'', 'keyColumn'=>'', 'data'=>['col1'=>'val1']];
        $rows = [spl_object_hash($entity)=>$row];
        $this->objectBinder->expects($this->once())->method('bindEntityToRows')->with($entity)->will($this->returnValue($rows));
        $this->sqlBuilder->expects($this->once())->method('getInsertQueries')->with($row)->will($this->returnValue(['INSERT sql']));
        $this->sqlBuilder->expects($this->once())->method('getQueryParams')->will($this->returnValue([]));
        $this->storage->expects($this->once())->method('executeQuery')->will($this->returnValue(true));
        $this->storage->expects($this->once())->method('getLastInsertId')->will($this->returnValue(999111));
        $result = $this->object->saveEntity($entity);
        $this->assertEquals(999111, $result);
    }

    public function testSaveEntityUpdate()
    {
        $entity = new TestPolicy();
        $entity->id = 222222;
        $entity->policyNo = 'TEST654321';

        $row = [
            'entity' => $entity,
            'table' => 'table',
            'parentRowHash' => null,
            'parentForeignKeyColumn' => null,
            'keyColumn' => 'id',
            'keyProperty' => 'id',
            'data' => []
        ];
        $rows = [spl_object_hash($entity)=>$row];

        $this->objectMapper->expects($this->any())->method('getIdProperty')->will($this->returnValue('id'));
        $this->objectBinder->expects($this->once())->method('bindEntityToRows')->with($entity, true)->will($this->returnValue($rows));
        $this->sqlBuilder->expects($this->once())->method('getUpdateQueries')->with(TestPolicy::class, $row, 222222)->will($this->returnValue(['UPDATE sql']));
        $this->sqlBuilder->expects($this->once())->method('getQueryParams')->with(0)->will($this->returnValue(['param_0'=>1]));
        $this->storage->expects($this->once())->method('getAffectedRecordCount')->will($this->returnValue(12));
        $result = $this->object->saveEntity($entity);
        $this->assertEquals(12, $result);
    }

    public function testGetObjectReference()
    {
        $ref = $this->object->getObjectReference(TestVehicle::class, 12345);
        $this->assertInstanceOf(TestVehicle::class, $ref);
        $this->assertEquals(12345, $ref->getPrimaryKeyValue());
    }
}
