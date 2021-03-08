<?php

namespace Objectiphy\Objectiphy\Tests\Entity;

use Objectiphy\Objectiphy\Mapping;

class TestAddress
{
    /**
     * @var string
     * @Objectiphy\Objectiphy\Mapping\Groups(groups={"Default"})
     * @Mapping\Column(name="address_line1")
     */
    protected $line1;
    /**
     * @var string
     * @Objectiphy\Objectiphy\Mapping\Groups(groups={"Default"})
     * @Mapping\Column(name="address_line2")
     */
    protected $line2;
    /**
     * @var string
     * @Objectiphy\Objectiphy\Mapping\Groups(groups={"Default"})
     * @Mapping\Column(name="address_town")
     */
    protected $town;
    /**
     * @var string
     * @Objectiphy\Objectiphy\Mapping\Groups(groups={"Default"})
     * @Mapping\Column(name="address_postcode")
     */
    protected $postcode;
    /**
     * @var string
     * @Mapping\Column(name="address_country_code",type="string")
     * @Objectiphy\Objectiphy\Mapping\Groups(groups={"Default"})
     */
    protected $countryCode;
    /**
     * @var string Scalar join based on a code stored with the address fields
     * @Objectiphy\Objectiphy\Mapping\Groups(groups={"Default"})
     * @Mapping\Relationship(
     *     relationshipType="scalar",
     *     targetScalarValueColumn="objectiphy_test.country.description",
     *     joinTable="objectiphy_test.country",
     *     sourceJoinColumn="address_country_code",
     *     targetJoinColumn="objectiphy_test.country.code"
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
