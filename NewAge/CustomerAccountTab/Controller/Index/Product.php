<?php
namespace NewAge\CustomerAccountTab\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Model\ProductFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Catalog\Api\ProductRepositoryInterfaceFactory;
use Magento\Catalog\Helper\ImageFactory;
use Magento\CatalogInventory\Model\Stock\StockItemRepository;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Magento\Bundle\Api\ProductLinkManagementInterface;
use Magento\Catalog\Model\Product as ProductModel;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\RequestInterface;

class Product extends Action
{


    private $resultJsonFactory;
    private $collectionFactory;
    private $productFactory;
    private $stockItemRepository;
    private $product;

    private $productModel;

    private $logger;

    private $productLinkManagement;


    private $request;


    /**
     * @var \Magento\Store\Model\App\Emulation
     */
    protected $appEmulation;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterfaceFactory
     */
    protected $productRepositoryFactory;

    /**
     * @var \Magento\Catalog\Helper\ImageFactory
     */
    protected $imageHelperFactory;


    /**
     * @param JsonFactory $resultJsonFactory
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param ProductFactory $productFactory
     * @param StoreManagerInterface $storeManager
     * @param Emulation $appEmulation
     * @param ProductRepositoryInterfaceFactory $productRepositoryFactory
     * @param ImageFactory $imageHelperFactory
     * @param StockItemRepository $stockItemRepository
     * @param PriceCurrencyInterface $priceCurrencyInterface
     * @param LoggerInterface $logger
     * @param ProductLinkManagementInterface $productLinkManagement
     * @param ProductModel $productModel
     * @param ResultFactory $resultFactory
     * @param RequestInterface $request
     */
    public function __construct(
        JsonFactory $resultJsonFactory,
        Context $context,
        CollectionFactory $collectionFactory,
        ProductFactory $productFactory,
        StoreManagerInterface $storeManager,
        Emulation $appEmulation,
        ProductRepositoryInterfaceFactory $productRepositoryFactory,
        ImageFactory $imageHelperFactory,
        StockItemRepository $stockItemRepository,
        PriceCurrencyInterface $priceCurrencyInterface,
        LoggerInterface $logger,
        ProductLinkManagementInterface $productLinkManagement,
        ProductModel $productModel,

        ResultFactory $resultFactory,
        RequestInterface $request
        )
    {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->collectionFactory = $collectionFactory;
        $this->productFactory = $productFactory;
        $this->storeManager = $storeManager;
        $this->appEmulation = $appEmulation;
        $this->productRepositoryFactory = $productRepositoryFactory;
        $this->imageHelperFactory = $imageHelperFactory;
        $this->stockItemRepository = $stockItemRepository;
        $this->priceCurrencyInterface = $priceCurrencyInterface;
        $this->logger = $logger;
        $this->productLinkManagement = $productLinkManagement;
        $this->productModel = $productModel;

        $this->request = $request;
        $this->resultFactory = $resultFactory;
    }
    /**
     * @param $pricefrom
     * @param $priceto
     * @param $orderBy
     * @return string
     */
    public function filterProduct($pricefrom,$priceto,$orderBy)
    {
        $productCollection = $this->collectionFactory->create();
        if($orderBy == "asc"){
            $productCollectionResults = $productCollection
                        ->addFieldToFilter('price', ['gteq' => $pricefrom])
                        ->addFieldToFilter('price', ['lteq' => $priceto])
                        ->addFieldToFilter('visibility',['neq'=>1])
                        // ->addFieldToFilter('type_id',['eq'=>"group"])
                        ->addAttributeToSort('price','asc');
        } else
        if($orderBy == "desc"){
            $productCollectionResults = $productCollection
                      ->addFieldToFilter('price', ['gteq' => $pricefrom])
                        ->addFieldToFilter('price', ['lteq' => $priceto])
                        ->addFieldToFilter('visibility',['neq'=>1])
                    ->addFieldToFilter('visibility',['neq'=>1])
                    // ->addFieldToFilter('type_id',['eq'=>"group"])
                    ->addAttributeToSort('price','desc');
        }
        
        $resultsFound = count($productCollectionResults->getData());
        $productCollectionResults->setPageSize(10);
        // $productCollectionResults->setCurPage($page);
        
        $rows = array();
        foreach ($productCollectionResults as $data){
            $row = array();
            $entityId = $data->getEntityId();
            $product = $this->productFactory->create()->load($entityId);
            $row['resultsFound'] = $resultsFound;
            $row['name'] = $product->getName();
            $row['sku'] = $product->getSku();
            $row['price'] = $product->getFinalPrice();
            $row['formattedPrice'] = $this->formatPrice($product->getFinalPrice());
            $row['qty'] = $this->qty($product);
            $row['thumbnail'] = $this->imgUrl($row['sku']);
            $row['productLink'] = $product->getProductUrl();
            // $row['visibility'] = $product->getVisibility();
            $row['type'] = $product->getTypeId();
            $row['configurableProduct'] = "";
            $row['priceRange'] = 0;
            $row['bundledProduct'] = 0;
            if($product->getTypeId() == "configurable"){
                $row['configurableProduct'] = $this->getChildProducts($product);
            } else
            if($product->getTypeId() == "downloadable"){
                $row['qty'] = "Download";
            } else
            if($product->getTypeId() == "grouped"){
                $row['groupedProduct'] = $this->getGroupedProducts($product);
                $row['priceRange'] = $this->getPriceRange($row['groupedProduct']);
            } else
            if($product->getTypeId() == "bundle"){
                $row['priceRange'] = $this->bundlePriceRange($product);
                $row['bundledProduct'] = $this->getBundledProducts($row['sku']);
            }
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * @param $price
     * @return string
     */
    public function formatPrice($price){
        return $this->priceCurrencyInterface->convertAndFormat($price);
    }

    /**
     * @param $sku
     * @return string
     */
    public function imgUrl($sku)
    {
        try {
            // get the store ID from somewhere (maybe a specific store?)
            $storeId = $this->storeManager->getStore()->getId();
            // emulate the frontend environment
            $this->appEmulation->startEnvironmentEmulation($storeId, \Magento\Framework\App\Area::AREA_FRONTEND, true);
            // load the product however you want
            $product = $this->productRepositoryFactory->create()->get($sku);
        } catch (\Exception $exception) {
            $this->logger->debug($exception->getMessage());
        }

        // now the image helper will get the correct URL with the frontend environment emulated
        $imageUrl = $this->imageHelperFactory->create()
          ->init($product, 'product_thumbnail_image')->getUrl();
        // end emulation
        $this->appEmulation->stopEnvironmentEmulation();
        return $imageUrl;
    }

    /**
     * @param $product
     * @return float
     */
    public function qty($product)
    {
        try {
            $productStockData = $this->stockItemRepository ->get($product->getId());
        } catch (\Exception $exception) {
            $this->logger->debug($exception->getMessage());
        }

        return $productStockData->getQty();
    }

    public function getChildProducts($configProduct)
    {
        $childProducts = array();
        $_children = $configProduct->getTypeInstance()->getUsedProducts($configProduct);
        foreach ($_children as $child){
            $childProducts[] = [
                "name" => $child->getName(),
                "formattedPrice" => $this->formatPrice($child->getPrice()),
                "price" => $child->getPrice(),
                "qty" => $this->qty($child),
                "thumbnail" => $this->imgUrl($child->getSku()),
                "sku" => $child->getSku(),
                "type" => $child->getTypeId()
                ];
        }
        return $childProducts;
    }

    public function getGroupedProducts($groupedProduct)
    {
        $_groupedProducts = array();
        $groupedProducts = $groupedProduct->getTypeInstance(true)->getAssociatedProducts($groupedProduct);
        foreach ($groupedProducts as $_product){
            $_groupedProducts[] = [
                "name" => $_product->getName(),
                "formattedPrice" => $this->formatPrice($_product->getPrice()),
                "price" => $_product->getPrice(),
                "qty" => $this->qty($_product),
                "thumbnail" => $this->imgUrl($_product->getSku()),
                "sku" => $_product->getSku(),
                "type" => $_product->getTypeId()
                ];
        }
        return $_groupedProducts;
    }

    /**
     * @param $sku
     * @return array
     * @throws Exception
     */
    public function getBundledProducts($sku)
    {

        // $sku = 'YOUR_PRODUCT_SKU';
        try
        {
            $items = $this->productLinkManagement->getChildren($sku);
        }
        catch (Exception $exception)
        {
            throw new Exception($exception->getMessage());
        }

        $_bundleProducts = array();
        foreach ($items as $_product){
            // var_dump($_product->getData());
            $productData = $this->productModel->load($_product->getEntityId());
            $_bundleProducts[] = [
                "name" => $productData->getName(),
                "formattedPrice" => $this->formatPrice($productData->getPrice()),
                "price" => $productData->getPrice(),
                "qty" => $this->qty($_product),
                "thumbnail" => $this->imgUrl($_product->getSku()),
                "sku" => $_product->getSku(),
                "type" => $productData->getTypeId()
                ];
        }
        return $_bundleProducts;
    }

    /**
     * @param $groupedProducts
     * @return array|int|void
     */
    public function getPriceRange($groupedProducts)
    {
        $_price = "";
        $prices = array();
        foreach ($groupedProducts as $productData){
            $tmpPrice = $productData["price"];
            if($tmpPrice > 0.00){
                $prices[] = $tmpPrice;
            }
        }
        if(count($prices) > 1){
            $min = min($prices);
            $max = max($prices);
            if($min < $max){
            return [
                'min' => $this->formatPrice($min),
                'max' => $this->formatPrice($max)
                ];
            }
        } else {
            return 0;
        }
    }

    /**
     * @param $product
     * @return array|int
     */
    public function bundlePriceRange($product)
    {
        $bundleObj=$product->getPriceInfo()->getPrice('final_price');
        $min = $bundleObj->getMinimalPrice()->getValue();// For min price
        $max = $bundleObj->getMaximalPrice()->getValue(); // for max price
        if($min < $max && $min > 0){
            return [
                'min' => $this->formatPrice($min),
                'max' => $this->formatPrice($max)
                ];
        } else {
            return 0;
        }
    }

    private function validate($min, $max, $sort)
    {
        $limit = intval($min)*5;
        if($max > $limit ){
            return false;
        } else if(!is_numeric($min)){
            return false;
        } else if(!is_numeric($max)){
            return false;
        } else if (($sort != "asc") && ($sort != "desc")) {
            return false;
        } else if ($max > $limit) {
            return false;
        } else if ($max <= $min) {
            return false;
        } else if ($min < 0) {
            return false;
        } else {
            return true;
        }
    }

    public function execute()
    {
        $params = $this->getRequest()->getParams();
        $resultJson = $this->resultJsonFactory->create();
        if($this->validate($params['from'],$params['to'],$params['sort'])){
            $filterResults = $this->filterProduct($params['from'],$params['to'],$params['sort']);
            return $resultJson->setData(['json_data' => $filterResults]);
        } else {
            return $resultJson->setData(['json_data' => 'error-v']);
        }
    }
}
