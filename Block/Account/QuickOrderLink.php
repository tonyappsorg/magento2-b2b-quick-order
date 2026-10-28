<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Block\Account;

use Magento\Framework\View\Element\Html\Link\Current;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2b\Model\Permissions;

class QuickOrderLink extends Current
{
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly CompanyContext $companyContext,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    public function toHtml()
    {
        return $this->companyContext->isAllowed(Permissions::PROFILE_VIEW) ? parent::toHtml() : '';
    }
}
