<?php

declare(strict_types=1);

namespace Hashemi\Valideto\Engine;

use Hashemi\Valideto\Rules\CustomRule\CustomRuleInterface;
use Hashemi\Valideto\Rules\DefaultRulesInterface;

abstract class ValidetoEngine
{

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var array
     */
    protected $rules = [];

    /**
     * @var array
     */
    protected $errorMessages = [];

    /**
     * @var null
     */
    private $rulesClass = null;

    /**
     * @var string[]
     */
    protected $presets = [
        'required',
        'max',
        'min',
        'gt',
        'gte',
        'lt',
        'lte',
        'eq',
        'nullable',
        'distinct',
        'date',
        'array',
        'url',
        'ip',
        'boolean',
        'date_format',
        'email',
        'string',
        'digits',
        'size',
        'numeric',
        'integer',
        'float',
        'assoc',
    ];

    /**
     * @var array
     */
    protected $messages = [
        'required' => 'This :attribute is required',
        'max' => 'This :attribute exceed max value',
        'min' => 'This :attribute expect at least min value',
        'array' => 'This :attribute should be array',
        'assoc' => 'This :attribute is not associative array',
        'size' => 'This :attribute length should be :value',
        'distinct' => 'This :attribute has duplicate value',
        'string' => 'This :attribute should be string',
        'numeric' => 'This :attribute should be numeric',
        'integer' => 'This :attribute should be integer',
        'float' => 'This :attribute should be float',
        'gt' => 'This :attribute should be greater than :value',
        'gte' => 'This :attribute should be greater than or equal to :value',
        'lt' => 'This :attribute should be less than :value',
        'lte' => 'This :attribute should be less than or equal to :value',
        'eq' => 'This :attribute should be equal to :value',
        'email' => 'This :attribute is not valid email',
        'url' => 'This :attribute is not valid url',
        'ip' => 'This :attribute is not valid ip',
        'date' => 'This :attribute is not valid date',
        'date_format' => 'This :attribute is not valid date format',
    ];

    public function setRulesClass(DefaultRulesInterface $rulesClass): ValidetoEngine
    {
        $this->rulesClass = $rulesClass->setData($this->getData());
        return $this;
    }

    /**
     * @return null
     */
    public function getRulesClass()
    {
        return $this->rulesClass;
    }

    abstract public function setData(array $data): self;
    abstract public function getData(): array;
    abstract public function setRules(array $rules): self;
    abstract public function getRules(): array;
    abstract public function setMessages(array $messages): self;

    /**
     * @param string|null $key
     * @return array|mixed|string|string[]
     */
    public function getMessages(string $key = null)
    {
        return $this->messages[$key] ?? $this->messages;
    }

    /**
     * @return array
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }

    /**
     * @return array
     */
    public function validate(): array
    {
        $data = [];
        $isValidated = true;
        foreach ($this->getRules() as $key => $rules) {
            if ($isValidated &= $this->isValidateByRules($key, $rules)) {
                if($this->rulesClass->isRequired($key)) {
                    $data[$key] = $this->data[$key];
                }
            }
        }

        return $isValidated ? $data : [];
    }

    /**
     * @param string $key
     * @param array $rules
     * @return bool
     */
    protected function isValidateByRules(string $key, array $rules): bool
    {
        $isValid = true;

        foreach($rules as $rule) {
            if (is_string($rule)) {
                $isNullable = false;
                $rule = explode(':', $rule);
                $methodName = $rule[0];
                $rule[0] = $key;
                $method = sprintf("is%s", str_replace(' ', '', ucwords(str_replace('_', '', $methodName))));
                $params = $rule;

                if ($methodName === 'nullable') {
                    $isNullable = true;
                }

                if ($isNullable) {
                    $params[] = true;
                }

                $totalParams = count($params);

                if ($this->isRuleDefaultPreset($methodName)) {
                    if (! call_user_func_array([$this->getRulesClass(), $method], $params) && $methodName !== 'nullable') {

                        $this->errorMessages[$key][$methodName] = preg_replace('/:attribute/i', $key, $this->getMessages($methodName));

                        if ( $totalParams > 0) {
                            $this->errorMessages[$key][$methodName] = preg_replace('/:value/i', $params[$totalParams - 1], $this->errorMessages[$key][$methodName]);
                        }

                        $isValid &= false;
                    }
                }
            }

            if ($rule instanceof CustomRuleInterface) {
                if (! $rule->process($this->data[$key])) {
                    $isValid &= false;
                    $this->errorMessages[$key][$rule->ruleName()] = $rule->message();
                }
            }

        }

        return (bool) $isValid;
    }

    /**
     * @return bool
     */
    public function success(): bool
    {
        return (bool) count($this->getErrorMessages());
    }

    /**
     * @return bool
     */
    public function fails(): bool
    {
        return count($this->getErrorMessages()) > 0;
    }

    /**
     * @param string $rule
     * @return bool
     */
    public function isRuleDefaultPreset(string $rule): bool
    {
        return in_array($rule, $this->presets);
    }
}