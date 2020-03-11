<?php
declare(strict_types=1);

namespace App\Model;

/**
 * Class AbstractModel
 * @package App\Model
 */
abstract class AbstractModel
{
    const NAME_LENGTH = 250;

    /**
     * @var array
     */
    private $errMessages;

    /**
     * Validates entity
     *
     * @return array
     */
    abstract function validate(): array;

    /**
     * Checks if entity is valid
     *
     * @return boolean
     */
    public function isValid()
    {
        $violations = $this->validate();
        $isValid = true;
        foreach ($violations as $errors) {
            if (count($errors)) {
                foreach ($errors as $error) {
                    $this->setMessage($error->getMessage());
                }
                $isValid = false;
            }
        }
        return $isValid;
    }

    /**
     * Sets error message
     *
     * @param string $message
     * @return void
     */
    public function setMessage(string $message): void
    {
        $this->errMessages[] = $message;
    }

    /**
     * Sets multiple messages
     *
     * @param array $messages
     * @return void
     */
    public function setMessages(array $messages): void
    {
        foreach ($messages as $message) {
            $this->setMessage($message);
        }
    }

    /**
     * Gets error messages
     *
     * @return array
     */
    public function getMessages(): ?array
    {
        return $this->errMessages;
    }

    /**
     * Binds array values to entity
     *
     * @param array $values
     * @return void
     */
    public function bind(array $values): void
    {
        foreach ($values as $key => $value) {
            if (property_exists(get_called_class(), $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Returns entity as array
     *
     * @return array
     */
    public function toArray(): array
    {
        $vars = get_object_vars($this);
        $result = [];
        foreach ($vars as $name => $value) {
            $result[$name] = $value;
        }
        return $result;
    }
}
