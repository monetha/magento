<?php
/**
 * Created by PhpStorm.
 * User: hitrov
 * Date: 2019-03-28
 * Time: 13:33
 */

namespace Monetha\PaymentGateway\Adapter;


use Magento\Payment\Gateway\Data\AddressAdapterInterface;
use Monetha\Adapter\ClientAdapterInterface;

class ClientAdapter implements ClientAdapterInterface
{
    private $zipCode;

    private $countryIsoCode;

    private $contactPhoneNumber;

    private $contactName;

    private $contactEmail;

    private $city;

    private $address;

    public function __construct(AddressAdapterInterface $addressAdapter)
    {
        $this->zipCode = $addressAdapter->getPostcode();
        $this->countryIsoCode = $addressAdapter->getCountryId();
        $this->contactPhoneNumber = preg_replace('/\D/', '', $addressAdapter->getTelephone());
        $this->contactName = $addressAdapter->getFirstname() . " " .  $addressAdapter->getLastname();
        $this->contactEmail = $addressAdapter->getEmail();
        $this->city = $addressAdapter->getCity();
        $this->address = $addressAdapter->getStreetLine1();
    }

    public function getZipCode()
    {
        return $this->zipCode;
    }

    public function getCountryIsoCode()
    {
        return $this->countryIsoCode;
    }

    public function getContactPhoneNumber()
    {
        return $this->contactPhoneNumber;
    }

    public function getContactName()
    {
        return $this->contactName;
    }

    public function getContactEmail()
    {
        return $this->contactEmail;
    }

    public function getCity()
    {
        return $this->city;
    }

    public function getAddress()
    {
        return $this->address;
    }
}