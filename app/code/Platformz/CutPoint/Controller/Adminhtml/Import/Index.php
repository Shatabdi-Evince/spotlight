<?php
declare(strict_types=1);

namespace Platformz\CutPoint\Controller\Adminhtml\Import;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Platformz_CutPoint::import';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $page = $this->resultPageFactory->create();
        $page->setActiveMenu('Platformz_CutPoint::import');
        $page->getConfig()->getTitle()->prepend(__('Import CutPoint Matrix'));

        return $page;
    }
}

