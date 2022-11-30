<?php

namespace Objectiphy\Objectiphy\Tests\UnitTests\Database\MySql;

//Avoid implementing DateTimeInterface for testing purposes
class ExtendedDateTime
{
    private $dateTime;
    
    public function __construct(string $format = '')
    {
        $this->dateTime = new \DateTime($format);
    }
    
    public function format(string $format)
    {
        return $this->dateTime->format($format);
    }
}
