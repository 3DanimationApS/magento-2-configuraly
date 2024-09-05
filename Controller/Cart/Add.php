<?php

namespace Configuraly\Configurator\Controller\Cart;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\NotFoundException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;

class Add extends \Magento\Framework\App\Action\Action implements HttpPostActionInterface
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

        if (isset($parentByChild[0])) {
            $parentProduct = $this->productRepository->getById($parentByChild[0]);
            $options = $this->getConfigurableOptions($parentProduct, $product);
            
            $params = array(
                'product' => $parentProduct->getId(),
                'qty' => $quantity,
                'super_attribute' => $options
            );
            $request = new \Magento\Framework\DataObject();
            $request->setData($params);

            $this->cart->addProduct($parentProduct, $request);
        } else {
            $this->cart->addProduct($product, $quantity);
        }
        
        // Important: Save the cart after each product addition
        $this->cart->save();
        
    } catch (NoSuchEntityException $noEntityException) {
        return null;
    } catch (\Exception $e) {
        // Log the exception
        // $this->logger->error('Error adding product ' . $SKU . ': ' . $e->getMessage());
        return null;
    }

    return $product;
}

protected function getConfigurableOptions($parentProduct, $childProduct)
{
    $options = array();
    $productAttributeOptions = $parentProduct->getTypeInstance()->getConfigurableAttributesAsArray($parentProduct);

    foreach ($productAttributeOptions as $productAttribute) {
        $allValues = array_column($productAttribute['values'], 'value_index');
        $currentProductValue = $childProduct->getData($productAttribute['attribute_code']);
        if (in_array($currentProductValue, $allValues)) {
            $options[$productAttribute['attribute_id']] = $currentProductValue;
        }
    }

    return $options;
}

  public function execute()
{
    $response = $this->resultJsonFactory->create();
    if ($this->getRequest()->isAjax()) {
        $configuration = $this->getRequest()->getParam('configuration');

        $missingProducts = array();
        $addedProducts = array();

        if(isset($configuration['parts']) && is_array($configuration['parts'])){
            foreach ($configuration['parts'] as $part) {
                $sku = $part['partnumber'];
                $quantity = $part['quantity'];

                $product = $this->AddProduct($sku, $quantity);

                if ($product === null) {
                    $missingProducts[] = $sku;
                } else {
                    $addedProducts[] = $sku;
                }
            }

            // Remove this line as we're saving after each addition
            // $this->cart->save();
            
            $this->session->setCartWasUpdated(true);
        }

        $result = array(
            'success' => count($missingProducts) === 0,
            'missingProducts' => $missingProducts,
            'addedProducts' => $addedProducts
        );

        return $response->setData($result);
    }
}
}
