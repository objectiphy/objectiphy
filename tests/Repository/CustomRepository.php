<?php

namespace Objectiphy\Objectiphy\Tests\Repository;

use Objectiphy\Objectiphy\Orm\ObjectRepository;

class CustomRepository extends ObjectRepository
{
    public function findParentUsingCustomSql($id)
    {
        $sql = "SELECT parent.id, parent.name, child.id AS child_id, child.name AS child_name, child.height_in_cm AS child_height
                FROM parent INNER JOIN child ON parent.id = child.parent_id 
                WHERE parent.id = :parentId";
        $parent = $this->objectFetcher->fetchResult($sql, ['parentId'=>$id]);
        
        return $parent;
    }

    public function findParentsUsingStringOverrides(array $criteria = [])
    {
        $overrides = [
            'select'=>'SELECT parent.id, parent.name, obj_alias_child.id AS child_id, obj_alias_child.name AS child_name, obj_alias_child.height_in_cm',
            'orderBy'=>'ORDER BY parent.name DESC'
        ];
        $this->overrideQueryParts($overrides);

        return $this->findBy($criteria);
    }

    public function findParentsUsingClosureOverrides(array $criteria = [])
    {
        $callable = [$this, 'replaceUserTable'];
        $overrides = [
            'select'=>$callable,
            'from'=>$callable,
            'joins'=>$callable,
            'where'=>$callable,
            'groupBy'=>$callable,
            'having'=>$callable,
            'orderBy'=>'ORDER BY parent.name DESC'
        ];
        $this->overrideQueryParts($overrides);

        return $this->findBy($criteria);
    }

    public function replaceUserTable($sql)
    {
        $replacement = str_replace('`user`.', '`user_alternative`.', $sql);
        $replacement = str_replace('`user` `obj_alias_user`', '`user_alternative` `obj_alias_user`', $sql);
        
        return $replacement;
    }
}
