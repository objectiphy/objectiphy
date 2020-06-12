<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Doctrine\ORM\Mapping as ORM;
use Objectiphy\Objectiphy;

/**
 * Class TestUser
 * @Objectiphy\Table(name="objectiphy_test.user")
 * @property int $id
 * @property string $type
 * @property string $email
 */
class TestUser
{
    /**
     * @ORM\Id
     * @var int
     * @ORM\Column(type="integer")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $type;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $email;

    public function getId()
    {
        return $this->id;
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($value)
    {
        $this->type = $value;
    }

    public function getEmail()
    {
        return $this->email;
    }
}
