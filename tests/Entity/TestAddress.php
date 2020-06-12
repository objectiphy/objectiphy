<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy;

class TestAddress
{
    /**
     * @var string
     * @Objectiphy\Groups(groups={"Default"})
     * @Objectiphy\Column(name="address_line1")
     */
    protected $line1;
    /**
     * @var string
     * @Objectiphy\Groups(groups={"Default"})
     * @Objectiphy\Column(name="address_line2")
     */
    protected $line2;
    /**
     * @var string
     * @Objectiphy\Groups(groups={"Default"})
     * @Objectiphy\Column(name="address_town")
     */
    protected $town;
    /**
     * @var string
     * @Objectiphy\Groups(groups={"Default"})
     * @Objectiphy\Column(name="address_postcode")
     */
    protected $postcode;
    /**
     * @var string
     * @Objectiphy\Column(name="address_country_code",type="string")
     * @Objectiphy\Groups(groups={"Default"})
     */
    protected $countryCode;
    /**
     * @var string Scalar join based on a code stored with the address fields
     * @Objectiphy\Groups(groups={"Default"})
     * @Objectiphy\Column(
     *     type="string",
     *     name="objectiphy_test.country.description",
     *     joinTable="objectiphy_test.country",
     *     sourceJoinColumn="address_country_code",
     *     joinColumn="objectiphy_test.country.code"
     * )
     */
    protected $countryDescription;

    public function getLine1()
    {
        return $this->line1;
    }

    public function getTown()
    {
        return $this->town;
    }

    public function setTown($value = null)
    {
        $this->town = $value;
    }

    public function getCountryCode()
    {
        return $this->countryCode;
    }

    public function setCountryCode($value)
    {
        $this->countryCode = $value;
    }

    public function getCountryDescription()
    {
        return $this->countryDescription;
    }

    public function setCountryDescription($value)
    {
        $this->countryDescription = $value;
    }
}
