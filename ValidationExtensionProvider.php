<?php
namespace tdhsmith\validation-extensions;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use Validator;

class ValidatorExtensionProvider extends ServiceProvider
{
    // Define rule message constants
    // TODO: This might be best in the resource files, but I kind of
    // like the curstom rules being self-contained too. Hrmm...
    static $requiredOrEmptyArrayMessage = "The :attribute field is required.";
    static $carbonDateMessage           = "The :attribute field could not be parsed as a Carbon datetime.";
    const REQUIRED_XOR_FAILURE_MESSAGE = 'Exactly one of the :attribute or :values fields must be present.';
    
    /**
     * Bootstrap the service and extend the validator with any custom rules we want.
     *
     * @return void
     */
    public function boot()
    {
        Validator::extendImplicit('required_or_empty_array', function ($attribute, $value, $parameters) {
            // Copied direct from Laravel foundation, with only the Countable case removed
            if (is_null($value)) {
                return false;
            } elseif (is_string($value) && trim($value) === '') {
                return false;
            } elseif ($value instanceof File) {
                return (string) $value->getPath() != '';
            }
            return true;
        }, self::$requiredOrEmptyArrayMessage);
        Validator::extend('carbon_date', function ($attribute, $value, $parameters) {
            $modelInstance = new User;
            try {
                $datetime = $modelInstance->fromDateTime($value);
            } catch (InvalidArgumentException $ex) {
                return false;
            }
            return true;
        }, self::$carbonDateMessage);
        
        Validator::extendImplicit('required_xor', function ($attribute, $value, $parameters, $validator) {
            // To examine values of attributes other than the one this rule applied to
            // we need the getValue function, which requires data and files arrays.
            $this->setup($validator);
            return $this->validateRequiredXOR($attribute, $value, $parameters);
        }, self::REQUIRED_XOR_FAILURE_MESSAGE);
        
        // An improved version of unique that ignores entries with the id sent in the data
        Validator::extend('dynamicUnique', function ($attribute, $value, $parameters, $validator) {
            $this->setup($validator);
            return $this->validateUnique($attribute, $value, $parameters);
        });
    }
    /**
     * Register the service provider.
     * We don't actually need to register anything, but this is an abstract method,
     * so we must provide an (empty) implementation here.
     *
     * @return void
     */
    public function register() {}
    
    
    protected function validateRequiredXOR($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'required_xor');
        
        if ($this->validateRequired($attribute, $value)) {
            return !$this->validateRequired($parameters[0], $this->getValue($parameters[0]));
        } else {
            return $this->validateRequired($parameters[0], $this->getValue($parameters[0]));
        }
    }
    
    // The following are all functions copied from the Laravel 5.2 standard Validator.
    // Since they are all protected, we have to recreate them to use them in this
    // service provider (or do weird inheritance trickery).
    protected function validateRequired($attribute, $value)
    {
        if (is_null($value)) {
            return false;
        } elseif (is_string($value) && trim($value) === '') {
            return false;
        } elseif ((is_array($value) || $value instanceof Countable) && count($value) < 1) {
            return false;
        } elseif ($value instanceof File) {
            return (string) $value->getPath() != '';
        }
        return true;
    }
    protected function requireParameterCount($count, $parameters, $rule)
    {
        if (count($parameters) < $count) {
            throw new InvalidArgumentException("Validation rule $rule requires at least $count parameters.");
        }
    }
    // The files and data are protected on the Validator,
    // so to access them, we need to use the public getters.
    private function setup(ValidatorContract $validator)
    {
        $this->validator = $validator;
        $this->files     = $validator->getFiles();
        $this->data      = $validator->getData();
    }
    protected function getValue($attribute)
    {
        if (! is_null($value = Arr::get($this->data, $attribute))) {
            return $value;
        } elseif (! is_null($value = Arr::get($this->files, $attribute))) {
            return $value;
        }
    }
    
    protected function validateUnique($attribute, $value, $parameters)
    {
        $this->requireParameterCount(1, $parameters, 'unique');
        
        
        list($connection, $table) = $this->parseTable($parameters[0]);
        // The second parameter position holds the name of the column that needs to
        // be verified as unique. If this parameter isn't specified we will just
        // assume that this column to be verified shares the attribute's name.
        $column = isset($parameters[1])
                    ? $parameters[1] : $attribute; // TODO: guessColumnForQuery from L5.2
        list($idColumn, $id) = [null, null];
        if (isset($parameters[2])) {
            list($idColumn, $id) = $this->getUniqueIds($parameters);
            if (preg_match('/\[(.*)\]/', $id, $matches)) {
                $id = $this->getValue($matches[1]);
            }
            if (strtolower($id) == 'null') {
                $id = null;
            }
        }
        
        // The presence verifier is responsible for counting rows within this store
        // mechanism which might be a relational database or any other permanent
        // data store like Redis, etc. We will use it to determine uniqueness.
        $verifier = $this->validator->getPresenceVerifier();
        $verifier->setConnection($connection);
        
        // TODO: support extra conditions (that's the [] on the next line)
        // $extra = $this->getUniqueExtra($parameters);
        return $verifier->getCount(
            $table, $column, $value, $id, $idColumn, []
        ) == 0;
    }
    
    protected function parseTable($table)
    {
        return Str::contains($table, '.') ? explode('.', $table, 2) : [null, $table];
    }
    protected function getUniqueIds($parameters)
    {
        $idColumn = isset($parameters[3]) ? $parameters[3] : 'id';
        return [$idColumn, $parameters[2]];
    }
}