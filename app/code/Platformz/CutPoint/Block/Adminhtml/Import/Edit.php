<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Block\Adminhtml\Import;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Backend\Model\UrlInterface;

class Edit extends Template
{
    public function __construct(
        Context $context,
        private readonly UrlInterface $urlBuilder,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getFormAction(): string
    {
        return $this->urlBuilder->getUrl('platformz_cutpoint/import/save');
    }
}

