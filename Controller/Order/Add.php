<?php
declare(strict_types=1);

namespace Tonyapps\B2bQuickOrder\Controller\Order;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\UrlInterface;
use Tonyapps\B2b\Api\Data\CompanyInterface;
use Tonyapps\B2b\Controller\AbstractCompanyAction;
use Tonyapps\B2b\Model\CompanyContext;
use Tonyapps\B2bQuickOrder\Model\CsvImporter;
use Tonyapps\B2bQuickOrder\Model\QuickOrderService;

class Add extends AbstractCompanyAction implements HttpPostActionInterface
{
    private const MAX_SHOWN_ERRORS = 10;

    public function __construct(
        RequestInterface $request,
        ResultFactory $resultFactory,
        CompanyContext $companyContext,
        Session $customerSession,
        ManagerInterface $messageManager,
        UrlInterface $url,
        private readonly HttpRequest $httpRequest,
        private readonly FileDriver $fileDriver,
        private readonly CsvImporter $csvImporter,
        private readonly QuickOrderService $quickOrderService
    ) {
        parent::__construct($request, $resultFactory, $companyContext, $customerSession, $messageManager, $url);
    }

    protected function executeForCompany(CompanyInterface $company): ResultInterface
    {
        $redirect = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        try {
            $content = $this->getInput();
            $parsed = $this->csvImporter->parse($content);
            $result = $this->quickOrderService->addRows($parsed['rows']);
            $errors = array_merge($parsed['errors'], $result['errors']);

            if ($result['added'] > 0) {
                $this->messageManager->addSuccessMessage(
                    __('Added %1 SKU line(s) to your cart.', $result['added'])
                );
            }
            $this->addErrors($errors);
        } catch (LocalizedException $exception) {
            $this->messageManager->addErrorMessage($exception->getMessage());
        } catch (\Exception) {
            $this->messageManager->addErrorMessage(__('Quick order could not be processed. Please check the file and try again.'));
        }

        return $redirect->setPath($this->httpRequest->getParam('return_to_cart') ? 'checkout/cart' : 'b2bquickorder/order/index');
    }

    private function getInput(): string
    {
        $file = $this->httpRequest->getFiles('import_file');
        $error = is_array($file) ? (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            return (string)$this->request->getParam('items', '');
        }
        if ($error !== UPLOAD_ERR_OK) {
            throw new LocalizedException(__('The CSV upload failed. Please try again.'));
        }
        if ((int)($file['size'] ?? 0) > CsvImporter::MAX_BYTES) {
            throw new LocalizedException(__('CSV files must be no larger than 1 MB.'));
        }
        if (strtolower(pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'csv') {
            throw new LocalizedException(__('Upload a .csv file.'));
        }

        $content = $this->fileDriver->fileGetContents((string)$file['tmp_name']);
        if (strlen($content) > CsvImporter::MAX_BYTES) {
            throw new LocalizedException(__('CSV files must be no larger than 1 MB.'));
        }
        return $content;
    }

    /** @param string[] $errors */
    private function addErrors(array $errors): void
    {
        foreach (array_slice($errors, 0, self::MAX_SHOWN_ERRORS) as $error) {
            $this->messageManager->addErrorMessage($error);
        }
        if (count($errors) > self::MAX_SHOWN_ERRORS) {
            $this->messageManager->addErrorMessage(
                __('%1 additional line error(s) were not shown.', count($errors) - self::MAX_SHOWN_ERRORS)
            );
        }
    }
}
