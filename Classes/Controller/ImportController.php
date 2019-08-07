<?php

namespace In2code\Publications\Controller;

use In2code\Publications\Service\ImportService;
use TYPO3\CMS\Core\Messaging\AbstractMessage;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Validation\Error;

class ImportController extends ActionController
{
    public function overviewAction()
    {
        $this->view->assignMultiple(
            [
                'availableImporter' => $this->getExistingImporter()
            ]
        );
    }

    /**
     * @param array $file
     * @validate $file \In2code\Publications\Validation\Validator\UploadValidator
     * @param string $importer
     * @validate $importer \In2code\Publications\Validation\Validator\ClassValidator
     *
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\UnsupportedRequestTypeException
     */
    public function importAction(array $file, string $importer)
    {
        $importService =
            $this->objectManager->get(
                ImportService::class,
                $file['tmp_name'],
                $this->objectManager->get($importer)
            );

        $importService->import();
    }

    /**
     * Error Action
     *
     * @return string
     * @throws \TYPO3\CMS\Extbase\Mvc\Exception\StopActionException
     */
    public function errorAction()
    {
        $this->clearCacheOnError();
        $this->addValidationErrorMessages();
        $this->addErrorFlashMessage();
        $this->forwardToReferringRequest();

        return $this->getFlattenedValidationErrorMessage();
    }

    /**
     * @return bool|string
     */
    protected function getErrorFlashMessage()
    {
        return false;
    }

    /**
     * Adds the Validation error Messages to the FlashMessage Queue
     */
    protected function addValidationErrorMessages()
    {
        if ($this->controllerContext->getArguments()->getValidationResults()->hasErrors()) {
            $validationErrors = $this->controllerContext->getArguments()->getValidationResults()->getFlattenedErrors();
            foreach ($validationErrors as $errors) {
                if (!empty($errors)) {
                    /** @var Error $error */
                    foreach ($errors as $error) {
                        /** @var FlashMessage $message */
                        $message = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
                            \TYPO3\CMS\Core\Messaging\FlashMessage::class,
                            $error->getMessage(),
                            $error->getTitle(),
                            \TYPO3\CMS\Core\Messaging\FlashMessage::ERROR,
                            true
                        );
                        $this->controllerContext->getFlashMessageQueue()->addMessage($message);
                    }
                }
            }
        }
    }

    /**
     * @return array
     */
    protected function getExistingImporter(): array
    {
        $importer = [];

        if (isset($GLOBALS['TYPO3_CONF_VARS']['EXT']['publications']['importer'])
            && is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['publications']['importer'])
        ) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['publications']['importer'] as $importerTitle => $importerClass) {
                if (!class_exists($importerClass)) {
                    $this->addFlashMessage(
                        'Importer class "' . $importerClass . '" was not found',
                        'Importer class not found',
                        AbstractMessage::ERROR
                    );
                } else {
                    $importer[$importerClass] = $importerTitle;
                }
            }

        }

        return $importer;
    }
}
