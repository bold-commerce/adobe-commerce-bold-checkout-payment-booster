<?php

declare(strict_types=1);

namespace Bold\CheckoutPaymentBooster\Controller\Adminhtml\Log;

use Exception;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem\Driver\File as FileDriver;
use Magento\Framework\Message\ManagerInterface;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;

/**
 * Handles the export of the log file for the bold_checkout_payment_booster module.
 */
class Export extends Action
{
    /** @var string  */
    private const LOG_FILENAME = 'bold_checkout_payment_booster.log';

    /** @var RawFactory  */
    protected $resultRawFactory;

    /** @var FileDriver  */
    protected $fileDriver;

    /** @var DirectoryList  */
    protected $directoryList;

    /** @var ManagerInterface  */
    protected $messageManager;

    /**
     * Constructor method for initializing dependencies.
     *
     * @param Context $context The application context instance.
     * @param RawFactory $resultRawFactory Factory for creating raw result instances.
     * @param FileDriver $fileDriver Handles file-related operations.
     * @param DirectoryList $directoryList Provides information about system directories.
     * @return void
     */
    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        FileDriver $fileDriver,
        DirectoryList $directoryList
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->fileDriver = $fileDriver;
        $this->directoryList = $directoryList;
        $this->messageManager = $context->getMessageManager();
    }

    /**
     * Executes the action to retrieve and download the specified log file.
     *
     * @return ResponseInterface|ResultInterface
     * @throws FileSystemException
     */
    public function execute()
    {
        $logFilePath = $this->directoryList->getPath(DirectoryList::VAR_DIR) . '/log/' . self::LOG_FILENAME;

        if (!$this->fileDriver->isExists($logFilePath)) {
            $this->messageManager->addErrorMessage('Log file not found.');
            return $this->_redirect($this->_redirect->getRefererUrl());
        }

        try {
            $contents = $this->fileDriver->fileGetContents($logFilePath);

            return $this->resultRawFactory->create()
                ->setHeader('Content-Type', 'text/plain', true)
                ->setHeader('Content-Disposition', 'attachment; filename="' .
                    self::LOG_FILENAME . '"', true)
                ->setContents($contents);
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage(sprintf('Unable to read log file: %s', $e->getMessage()));
            return $this->_redirect($this->_redirect->getRefererUrl());
        }
    }
}
