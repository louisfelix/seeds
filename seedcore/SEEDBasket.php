<?php

/* SEEDBasket.php
 *
 * Copyright (c) 2016 Seeds of Diversity Canada
 *
 * Manage a shopping basket of diverse products
 */

class SEEDBasketCore
/*******************
    Core class for managing a shopping basket
 */
{
    public $oDB;

    private $raHandlerDefs;
    private $raHandlers = array();

    function __construct( KeyFrameDB $kfdb, SEEDSession $sess, $raHandlerDefs )
    {
        $uid = method_exists( $sess, 'GetUID' ) ? $sess->GetUID() : 0;
        $this->oDB = new SEEDBasketDB( $kfdb, $uid );
        $this->raHandlerDefs = $raHandlerDefs;
    }


    function DrawProductNewForm( $sProductType )
    {
        $s = "";

        if( !($oHandler = $this->getHandler( $sProductType )) ) goto done;

        $s .= $oHandler->ProductDefine0( null );

        done:
        return( $s );
    }

    function DrawProductForm( KFRecord $kfrP )
    {
        return( ($oHandler = $this->getHandler( $kfrP->Value('product_type') ))
                ? $oHandler->ProductDefine0( $kfrP ) : "" );
    }

    function DrawProduct( KFRecord $kfrP, $bDetail )
    {
        return( ($oHandler = $this->getHandler( $kfrP->Value('product_type') ))
                ? $oHandler->ProductDraw( $kfrP, $bDetail ) : "" );
    }


    private function getHandler( $prodType )
    {
        if( isset($this->raHandlers[$prodType]) )  return( $this->raHandlers[$prodType] );

        $o = null;
        if( isset($this->raHandlerDefs[$prodType]['classname']) ) {
            $o = new $this->raHandlerDefs[$prodType]['classname']( $this );
            if( $o ) {
                $this->raHandlers[$prodType] = $o;
            }
        }
        return( $o );
    }


}


class SEEDBasketProductHandler
/*****************************
    Every time you do something with a product, you use a derivation of this.
    So you have to make a Handler for every productType that you use.

    ProductDefine0          Draw a form to create/update a product definition
    ProductDefine1          Validate a product definition
    ProductDefine2          Save a product definition
    ProductDraw( bDetail)   Show a description of a product in more or less detail
    ProductDelete           Remove a product from the system (this does a soft delete if the product is referenced by any BxP)

    Purchase0               Draw a form for the purchase details stored in a BasketXProduct
    Purchase1               Validate a purchase before adding/updating a BxP
    Purchase2               Add/update a BxP
    PurchaseDraw( bDetail ) Show a description of a BxP in more or less detail
    PurchaseDelete          Remove a BxP from its basket


 */
{
    private $oSB;

    function __construct( SEEDBasketCore $oSB )
    {
        $this->oSB = $oSB;
    }

    function ProductDefine0( KFRecord $kfrP )
    /******************************************
        Draw a form to edit the given product.
        If kfrP is null draw a New product form.
     */
    {
        die( "Override DrawForm" );
    }

    function ProductDefine1( KFRecord $kfrP )
    /******************************************
        Validate a new/updated product definition.
        Return true if the product definition makes sense, otherwise false and an error message.
     */
    {
        return( array(true,"No error") );
    }

    function ProductDefine2( KFRecord $kfrP )
    /******************************************
        Save a product definition
     */
    {
        // probably never used because SEEDBasketCore is just going to use PutDBRow()
    }

    function ProductDraw( KFRecord $kfrP, $bDetail )
    {
        die( "Override ProductDraw" );
    }

    function ProductDelete( $kP )
    {
        if( ($kfrP = $this->oDB->GetProduct( $kP )) ) {
            // if exist BxP where BP.fk_SEEDBasket_Products='$kP'
            //     $kfrP->SetValue( 'bDeleted', true );
            // else
            //     $kfrP->Delete();
        }
    }


    function AddToBasket_Before( KFRecord $kfrP, KFRecord $kfrBP )
    /*************************************************************
        This is called before adding a product to a basket.
        Make any necessary changes to the Product and BasketXProduct.
        Return true if it's okay, false if not.
     */
    {
        return( true ); // default is assume everything is normal
    }

}


class SEEDBasketDB extends KeyFrameNamedRelations
{
    function __construct( KeyFrameDB $kfdb, $uid )
    {
        parent::__construct( $kfdb, $uid );
    }


    function GetBasket( $kBasket )    { return( $this->GetKFR( 'B', $kBasket ) ); }
    function GetProduct( $kProduct )  { return( $this->GetKFR( 'P', $kBasket ) ); }

    function GetBasketList( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetBasketKFRC( $sCond, $raKFParms = array() )  { return( $this->GetList( 'B', $sCond, $raKFParms ) ); }
    function GetProductList( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }
    function GetProductKFRC( $sCond, $raKFParms = array() ) { return( $this->GetKFRC( 'P', $sCond, $raKFParms ) ); }

    protected function initKfrel( KeyFrameDB $kfdb, $uid )
    {
        /* raKfrel['B']   base relation for SEEDBasket_Baskets
         * raKfrel['P']   base relation for SEEDBasket_Products
         * raKfrel['BP']  base relation for SEEDBasket_BP map table
         * raKfrel['BxP'] joins baskets and products via B x BP x P
         */
        $kdefBaskets =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Baskets',
                                             "Alias" => "B",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        $kdefProducts =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Products',
                                             "Alias" => "P",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        $kdefBP =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_BP',
                                             "Alias" => "BP",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ) ) );
        $kdefBxP =
            array( "Tables" => array( array( "Table" => 'seeds.SEEDBasket_Baskets',
                                             "Alias" => "B",
                                             "Type" => "Base",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_BP',
                                             "Alias" => "BP",
                                             "Type" => "Map",
                                             "Fields" => "Auto" ),
                                      array( "Table" => 'seeds.SEEDBasket_Products',
                                             "Alias" => "P",
                                             "Type" => "Children",
                                             "Fields" => "Auto" ) ) );

        $raParms = array( 'logfile' => SITE_LOG_ROOT."SEEDBasket.log" );
        $raKfrel = array();
        $raKfrel['B']   = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBaskets ),  $uid, $raParms );
        $raKfrel['P']   = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefProducts ), $uid, $raParms );
        $raKfrel['BP']  = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBP ),       $uid, $raParms );
        $raKfrel['BxP'] = new KeyFrameRelation( $kfdb, array_merge( array('ver',2), $kdefBxP ),      $uid, $raParms );

        return( $raKfrel );
    }
}




define("SEEDS_DB_TABLE_SEEDBASKET_BASKETS",
"
CREATE TABLE SEEDBasket_Baskets (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    -- About the buyer
    uid_buyer       INTEGER NOT NULL DEFAULT '0',
    buyer_firstname VARCHAR(100) NOT NULL DEFAULT '',
    buyer_lastname  VARCHAR(100) NOT NULL DEFAULT '',
    buyer_company   VARCHAR(100) NOT NULL DEFAULT '',
    buyer_addr      VARCHAR(100) NOT NULL DEFAULT '',
    buyer_city      VARCHAR(100) NOT NULL DEFAULT '',
    buyer_prov      VARCHAR(100) NOT NULL DEFAULT '',
    buyer_postcode  VARCHAR(100) NOT NULL DEFAULT '',
    buyer_country   VARCHAR(100) NOT NULL DEFAULT '',
    buyer_phone     VARCHAR(100) NOT NULL DEFAULT '',
    buyer_email     VARCHAR(100) NOT NULL DEFAULT '',
    buyer_lang      ENUM('E','F') NOT NULL DEFAULT 'E',
    buyer_notes     TEXT NOT NULL DEFAULT '',

    buyer_extra     TEXT NOT NULL DEFAULT '',


    -- About the products

    prod_extra      TEXT NOT NULL DEFAULT '',


    -- About the payment
    pay_eType       ENUM('PayPal','Cheque') NOT NULL DEFAULT 'PayPal',
    pay_total       DECIMAL(8,2)            NOT NULL DEFAULT 0,
    pay_currency    ENUM('CDN','USD')       NOT NULL DEFAULT 'CDN',

    pay_extra       TEXT NOT NULL DEFAULT '',

    pp_name         VARCHAR(200),   -- Set by PPIPN
    pp_txn_id       VARCHAR(200),
    pp_receipt_id   VARCHAR(200),
    pp_payer_email  VARCHAR(200),
    pp_payment_status VARCHAR(200),


    -- About the fulfilment
    eStatus         ENUM('New','Paid','Filled','Cancelled') NOT NULL DEFAULT 'New',
    notes           TEXT NOT NULL DEFAULT '',


    sExtra          TEXT NOT NULL DEFAULT ''            -- e.g. urlencoded metadata about the purchase
-- in sExtra   mail_eBull      BOOL            DEFAULT 1,
-- in sExtra   mail_where      VARCHAR(100),
);
"
);

define("SEEDS_DB_TABLE_SEEDBASKET_PRODUCTS",
"
CREATE TABLE SEEDBasket_Products (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    uid_seller      INTEGER NOT NULL DEFAULT '0',
    product_type    VARCHAR(100) NOT NULL,

    title           VARCHAR(200) NOT NULL DEFAULT '',
    name            VARCHAR(100) NOT NULL DEFAULT '',
    img             VARCHAR(100) NOT NULL DEFAULT '',

    bask_quant_min  INTEGER NOT NULL DEFAULT '0',   -- you have to put at least this many in a basket if you have any
    bask_quant_max  INTEGER NOT NULL DEFAULT '0',   -- you can't put more than this in a basket at once (-1 means no limit)

    quant_type      ENUM('ITEM-1',                  -- it only makes sense to order one of this product at a time
                         'ITEM-N',                  -- you can order one or more at a time
                         'MONEY'),                  -- this product is a buyer-specified amount of money (e.g. a donation)

    item_price      VARCHAR(100) NOT NULL DEFAULT '',  -- e.g. '15', '15:1-9,10:10-24,8:25+'
    item_discount   VARCHAR(100) NOT NULL DEFAULT '',  -- e.g. '0', '0:1-9,5:10-24,7:25+'

    item_price_US    VARCHAR(100) NOT NULL DEFAULT '',
    item_discount_US VARCHAR(100) NOT NULL DEFAULT '',

    shipping        VARCHAR(100) NOT NULL DEFAULT '',  -- e.g. '10', '10:1-9,5:10-14,0:15+'
    shipping_US     VARCHAR(100) NOT NULL DEFAULT '',





    mbr_type        VARCHAR(100),
    donation        INTEGER,
    pub_ssh_en      INTEGER,
    pub_ssh_fr      INTEGER,
    pub_nmd         INTEGER,
    pub_shc         INTEGER,
    pub_rl          INTEGER,



    sExtra          TEXT NOT NULL DEFAULT ''            -- e.g. urlencoded metadata about the product
);
"
);


define("SEEDS_DB_TABLE_SEEDBASKET_BP",
"
CREATE TABLE SEEDBasket_BP (
        _key        INTEGER NOT NULL PRIMARY KEY AUTO_INCREMENT,
        _created    DATETIME,
        _created_by INTEGER,
        _updated    DATETIME,
        _updated_by INTEGER,
        _status     INTEGER DEFAULT 0,

    fk_SEEDBasket_Baskets  INTEGER NOT NULL,
    fk_SEEDBasket_Products INTEGER NOT NULL,
    n                      INTEGER NOT NULL,        -- the number of items if ITEM type
    f                      DECIMAL(7,2) NOT NULL,   -- the amount if MONEY type
    eStatus                ENUM('NEW','PAID','FILLED','CANCELLED') NOT NULL DEFAULT 'NEW',
    bAccountingDone        TINYINT NOT NULL DEFAULT '0',

    sExtra                 TEXT NOT NULL DEFAULT ''            -- e.g. urlencoded metadata about the purchase
);
"
);


/* Test data

INSERT INTO seeds.SEEDBasket_Baskets ( buyer_firstname, buyer_lastname, eStatus ) VALUES ( 'Bob', 'Wildfong', 'PAID' );

INSERT INTO seeds.SEEDBasket_Products ( product_type,uid_seller,title,name,bask_quant_min,bask_quant_max,quant_type,item_price ) VALUES ('donation',1,'Donation','donation',0,-1,'MONEY',-1);
INSERT INTO seeds.SEEDBasket_Products ( product_type,uid_seller,title,name,bask_quant_min,bask_quant_max,quant_type,item_price ) VALUES ('book',1,'How to Save Your Own Seeds, 6th edition','ssh6-en',1,-1,'ITEM-N',15);
INSERT INTO seeds.SEEDBasket_Products ( product_type,uid_seller,title,name,bask_quant_min,bask_quant_max,quant_type,item_price ) VALUES ('membership',1,'Membership - One Year','mbr25',1,1,'ITEM-1',25);

INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,1,0,123.45,'PAID');
INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,2,5,0,'PAID');
INSERT INTO seeds.SEEDBasket_BP (fk_SEEDBasket_Baskets,fk_SEEDBasket_Products,n,f,eStatus) VALUES (1,3,1,0,'PAID');


 */



?>