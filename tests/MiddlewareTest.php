<?php

class MiddlewareTest extends TestCase
{

    const CLIENT_CODE = "SF00016427";
    const BILL_NUMBER = "BIOF2201003350";
    const ORDER_NUMBER = "BIOCP220102916";
    const QUOTE_NUMBER = "BIODV230100001";
    const CATALOGUE = "BIOMATERIAUX";
    const SEARCH_QUERY = "K30P";
    const EMAIL = "a.louche@biotech-dental.com";

    /**
     * /shippingAddresses/clientCode [GET]
     */
    public function testShouldGetShippingAddresses()
    {
        $uri = "/api/middleware/shippingAddresses/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /billingStatus/billNumber [GET]
     */
    public function testShouldGetBillingStatus()
    {
        $uri = "/api/middleware/billingStatus/" . SELF::BILL_NUMBER;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /bills/clientCode [GET]
     */
    public function testShouldGetBills()
    {
        $uri = "/api/middleware/bills/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /billsAndStatus/clientCode [GET]
     */
    public function testShouldGetBillsAndStatus()
    {
        $uri = "/api/middleware/billsAndStatus/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);
    }


    /**
     * /billLines/clientCode/billNumber [GET]
     */
    public function testShouldGetBillLines()
    {
        $uri = "/api/middleware/billLines/" . SELF::CLIENT_CODE . '/' . SELF::BILL_NUMBER;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /orderLines/clientCode/billNumber [GET]
     */
    public function testShouldGetOrderLines()
    {
        $uri = "/api/middleware/billLines/" . SELF::CLIENT_CODE . '/' . SELF::BILL_NUMBER;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }


    /**
     * /quoteLines/clientCode/quoteNumber [GET]
     */
    public function testShouldGetQuoteLines()
    {
        $uri = "/api/middleware/billLines/" . SELF::CLIENT_CODE . '/' . SELF::QUOTE_NUMBER;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /credits/clientCode [GET]
     */
    public function testShouldGetCredits()
    {
        $uri = "/api/middleware/credits/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /creditsSold/clientCode [GET]
     */
    public function testShouldGetCreditsSold()
    {
        $uri = "/api/middleware/creditsSold/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /shipments/clientCode [GET]
     */
    public function testShouldGetShipments()
    {
        $uri = "/api/middleware/shipments/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /products [GET]
     */
    public function testShouldGetProducts()
    {
        $uri = "/api/middleware/products";

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }


    /**
     * /productsByCatalogue/params [GET]
     */
    public function testShouldGetProductsByCatalogue()
    {
        $uri = "/api/middleware/productsByCatalogue/" . SELF::CATALOGUE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /productsBySearchQuery/searchQuery [GET]
     */
    public function testShouldGetProductsBySearchQuery()
    {
        $uri = "/api/middleware/productsBySearchQuery/" . SELF::SEARCH_QUERY;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /catalogue [GET]
     */
    public function testShouldGetCatalogue()
    {
        $uri = "/api/middleware/catalogue";

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /catalogueTopTenProducts/cat [GET]
     */
    public function testShouldGetCatalogueTopTenProducts()
    {
        $uri = "/api/middleware/catalogueTopTenProducts/" . SELF::CATALOGUE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /checkEmail/email [GET]
     */
    public function testShouldCheckEmail()
    {
        $uri = "/api/middleware/checkEmail/" . SELF::EMAIL;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /clientInfos/clientCode [GET]
     */
    public function testShouldGetCLientInfos()
    {
        $uri = "/api/middleware/clientInfos/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }

    /**
     * /unlockClient/clientCode [GET]
     */
    public function testShouldUnlockCLient()
    {
        $uri = "/api/middleware/unlockClient/" . SELF::CLIENT_CODE;

        $this->get($uri, []);

        $this->seeStatusCode(200);

        $this->seeJson([
            'success' => true,
        ]);
    }
}