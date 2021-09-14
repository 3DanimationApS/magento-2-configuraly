<?php

namespace Configuraly\Configurator\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;

abstract class Add extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
{
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $productRepository;
    
    /**
     * @var \Magento\Catalog\Api\ProductRepositoryInterface
     */
    protected $catalogProductTypeConfigurable;
    
    /**
     * @var \Magento\Checkout\Model\Cart
     */
    protected $cart;
    
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $session;

    /**
     * @param \Magento\Framework\App\Action\Context $context
     * @param \Magento\Catalog\Api\ProductRepositoryInterface $productRepository
     * @param \Magento\Checkout\Model\Cart $cart
     * @param \Magento\Checkout\Model\Session $session
     * @param \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory
     */
    public function __construct(
        \Magento\Framework\App\Action\Context $context,
        \Magento\Catalog\Api\ProductRepositoryInterface $productRepository,
        \Magento\Checkout\Model\Cart $cart,
        \Magento\Checkout\Model\Session $session,
        \Magento\Framework\Controller\Result\JsonFactory $resultJsonFactory,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $catalogProductTypeConfigurable
    ) {
        parent::__construct($context);
        $this->cart = $cart;
        $this->session = $session;
        $this->productRepository = $productRepository;
        $this->resultJsonFactory = $resultJsonFactory;
        $this->catalogProductTypeConfigurable = $catalogProductTypeConfigurable;
    }

    /**
     * Fetches a Product Instance
     *
     * @return \Magento\Catalog\Model\Product
     */
    protected function AddProduct($SKU, $quantity)
    {
        try {
            $product = $this->productRepository->get($SKU);

            $parentByChild = $this->catalogProductTypeConfigurable->getParentIdsByChild($product->getId());

            if(isset($parentByChild[0])){ 
                $parentproduct = $this->productRepository->getById($parentByChild[0]);
                $productAttributeOptions = $parentproduct->getTypeInstance()->getConfigurableAttributesAsArray($parentproduct);
                $options = array();

                foreach ($productAttributeOptions as $productAttribute) {
                    $allValues = array_column($productAttribute['values'], 'value_index');
                    $currentProductValue = $product->getData($productAttribute['attribute_code']);
                    if (in_array($currentProductValue, $allValues)) {
                        $options[$productAttribute['attribute_id']] = $currentProductValue;
                    }
                }

                $params = array(
                    'product' => $parentproduct->getId(),
                    'qty' => $quantity,
                    'super_attribute' => $options
                );
                $request = new \Magento\Framework\DataObject();
                $request->setData($params);

                $this->cart->addProduct($parentproduct, $request);
            }else{
                $this->cart->addProduct($product, $quantity);
            }

        } catch (NoSuchEntityException $noEntityException) {
           return null;
        }

        return $product;
    }
    
    public function execute()
    {
        $response = $this->resultJsonFactory->create();
        if ($this->getRequest()->isAjax()) 
        {
            $configuration = $this->getRequest()->getParam('configuration');

            $missingProducts = array();

            for($i = 0; $i < count($configuration['parts']); $i++){
                $sku = $configuration['parts'][$i]['partnumber'];
                $quantity = $configuration['parts'][$i]['quantity'];

                $product = $this->AddProduct($sku, $quantity);

                if($product == null){
                    $missingProducts[] = $sku;
                }
            }

            $this->cart->save();
            $this->session->setCartWasUpdated(true);

            $result = array();
            $result['success'] = count($missingProducts) == 0;
            $result['missingProducts'] = $missingProducts;

            return $response->setData($result);
        }
    }
}
