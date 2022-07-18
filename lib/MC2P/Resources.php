<?php

namespace MC2P;

require_once('Base.php');
require_once('Mixins.php');
require_once('Objects.php');

/**
 * Product resource
 */
class ProductResource extends CRUDResource {}

/**
 * Plan resource
 */
class PlanResource extends CRUDResource {}

/**
 * Tax resource
 */
class TaxResource extends CRUDResource {}

/**
 * Shipping resource
 */
class ShippingResource extends CRUDResource {}

/**
 * Coupon resource
 */
class CouponResource extends CRUDResource {}

/**
 * Transaction resource
 */
class TransactionResource extends CRResource {}

/**
 * Subscription resource
 */
class SubscriptionResource extends CRResource {}

/**
 * Authorization resource
 */
class AuthorizationResource extends CRResource 
{

    protected $cResourceMixin;
    protected $rResourceMixin;
    
    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        parent::__construct($apiRequest, $path, $objItemClass);
        $this->cResourceMixin = new ChargeResourceMixin($apiRequest, $path, $objItemClass, $this->paginatorClass);
        $this->rResourceMixin = new RemoveResourceMixin($apiRequest, $path, $objItemClass, $this->paginatorClass);
    }
        
    /**
     * Charge the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function charge($resourceId, Array $data = null)
    {
        return $this->cResourceMixin->charge($resourceId, $data);
    }
        
    /**
     * Remove authorization the object item
     * 
     * @return array Object item from server
     */
    public function remove($resourceId)
    {
        return $this->rResourceMixin->remove($resourceId);
    }

}

/**
 * Currency resource
 */
class CurrencyResource extends ReadOnlyResource {}
 
/**
 * Gateway resource
 */
class GatewayResource extends ReadOnlyResource {}
 
/**
 * Sale resource
 */
class SaleResource extends ReadOnlyResource
{
    protected $rCVResourceMixin;
    
    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        parent::__construct($apiRequest, $path, $objItemClass);
        $this->rCVResourceMixin = new RefundCaptureVoidResourceMixin($apiRequest, $path, $objItemClass, $this->paginatorClass);
    }
        
    /**
     * Refund the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function refund($resourceId, Array $data = null)
    {
        return $this->rCVResourceMixin->refund($resourceId, $data);
    }

    /**
     * Capture the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function capture($resourceId, Array $data = null)
    {
        return $this->rCVResourceMixin->capture($resourceId, $data);
    }

    /**
     * Void the object item
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function void($resourceId, Array $data = null)
    {
        return $this->rCVResourceMixin->void($resourceId, $data);
    }
}
  
/**
 * PayData resource
 */
class PayDataResource extends DetailOnlyResource
{
    protected $cardShareResourceMixin;
    
    /**
     * @param array    $apiRequest
     */
    public function __construct ($apiRequest, $path, $objItemClass) 
    {
        parent::__construct($apiRequest, $path, $objItemClass);
        $this->cardShareResourceMixin = new CardShareResourceMixin($apiRequest, $path, $objItemClass, $this->paginatorClass);
    }

    /**
     * Send card details
     * 
     * @param array $gatewayCode
     * @param array $data
     * @return array Object item from server
     */
    public function card(string $gatewayCode, array $data = null)
    {
        return $this->cardShareResourceMixin->card($gatewayCode, $data);
    }
     
    /**
     * Send share details
     * 
     * @param array $data
     * @return array Object item from server
     */
    public function share(array $data = null)
    {
        return $this->cardShareResourceMixin->share($data);        
    }
}

/**
 * Client resource
 */
class ClientResource extends CRUDResource {}

/**
 * Wallet resource
 */
class WalletResource extends CRUDResource {}
