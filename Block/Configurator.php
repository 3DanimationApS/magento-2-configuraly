<?php

namespace Configuraly\Configurator\Block;

use Magento\Framework\View\Element\Template;
use Magento\Widget\Block\BlockInterface;

class Configurator extends Template implements BlockInterface
{
    protected $_template = "widget/product.phtml";
}