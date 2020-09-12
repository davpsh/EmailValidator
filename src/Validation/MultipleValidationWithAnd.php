<?php

namespace Egulias\EmailValidator\Validation;

use Egulias\EmailValidator\EmailLexer;
use Egulias\EmailValidator\Result\InvalidEmail;
use Egulias\EmailValidator\Validation\Exception\EmptyValidationList;
use Egulias\EmailValidator\Result\MultipleErrors;

class MultipleValidationWithAnd implements EmailValidation
{
    /**
     * If one of validations fails, the remaining validations will be skept.
     * This means MultipleErrors will only contain a single error, the first found.
     */
    const STOP_ON_ERROR = 0;

    /**
     * All of validations will be invoked even if one of them got failure.
     * So MultipleErrors will contain all causes.
     */
    const ALLOW_ALL_ERRORS = 1;

    /**
     * @var EmailValidation[]
     */
    private $validations = [];

    /**
     * @var array
     */
    private $warnings = [];

    /**
     * @var MultipleErrors|null
     */
    private $error;

    /**
     * @var int
     */
    private $mode;

    /**
     * @param EmailValidation[] $validations The validations.
     * @param int               $mode        The validation mode (one of the constants).
     */
    public function __construct(array $validations, $mode = self::ALLOW_ALL_ERRORS)
    {
        if (count($validations) == 0) {
            throw new EmptyValidationList();
        }

        $this->validations = $validations;
        $this->mode = $mode;
    }

    /**
     * {@inheritdoc}
     */
    public function isValid($email, EmailLexer $emailLexer) : bool
    {
        $result = true;
        foreach ($this->validations as $validation) {
            $emailLexer->reset();
            $validationResult = $validation->isValid($email, $emailLexer);
            $result = $result && $validationResult;
            $this->warnings = array_merge($this->warnings, $validation->getWarnings());
            if (!$validationResult) {
                $this->processError($validation);
            }

            if ($this->shouldStop($result)) {
                break;
            }
        }

        return $result;
    }

    private function initErrorStorage()
    {
        if (null === $this->error) {
            $this->error = new MultipleErrors();
        }
    }

    private function processError(EmailValidation $validation)
    {
        if (null !== $validation->getError()) {
            $this->initErrorStorage();
            $this->error->addReason($validation->getError()->reason());
        }
    }

    /**
     * @param bool $result
     *
     * @return bool
     */
    private function shouldStop($result)
    {
        return !$result && $this->mode === self::STOP_ON_ERROR;
    }

    /**
     * Returns the validation errors.
     *
     */
    public function getError() : ?InvalidEmail
    {
        return $this->error;
    }

    /**
     * {@inheritdoc}
     */
    public function getWarnings() : array
    {
        return $this->warnings;
    }
}
