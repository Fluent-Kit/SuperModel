<?php

namespace FluentKit\SuperModel;

use Illuminate\Database\Eloquent\Model;
use Validator;
use Hash;

class SuperModel extends Model{

	/*
	 * SuperModel Atts
	 */

	public $rules = array(
		'global' => array(),
		'create' => array(),
		'update' => array()
	);

	public $messages  = array(
		'global' => array(),
		'create' => array(),
		'update' => array()
	);

	protected $attributes_schema = array();

	protected $sanitize_attributes = true;

	protected $autohash_attributes = true;

	protected $autoserialize_attributes = true;

	private $old_attributes = array();

	private $hashed_originals = array();

	private $samerule_originals = array();

	private $errors;

	/*
	 * SuperModel Atts End
	 */

	public static function boot() {
        parent::boot();

        self::creating(function($model){
        	$model->sanitizeAttributes();
        	return $model->validateCreate();
        });

        self::updating(function($model){
        	$model->sanitizeAttributes();
        	return $model->validateUpdate();
        });
    }

    //overload mutator check has __call doesnt work with method exists.
    public function hasSetMutator($key)
	{

		if($this->autohash_attributes){
	       	foreach($this->attributes_schema as $k => $value){
	       		if($value == 'hashed' && $k == $key){
	       			return true;
	       		}
	       	}
       	}

       	if($this->autoserialize_attributes){
	       	foreach($this->attributes_schema as $k => $value){
	       		if($value == 'array' && $k == $key || $value == 'object' && $k == $key){
	       			return true;
	       		}
	       	}
       	}

		return parent::hasSetMutator($key);
	}

	public function hasGetMutator($key)
	{

       	if($this->autoserialize_attributes){
	       	foreach($this->attributes_schema as $k => $value){
	       		if($value == 'array' && $k == $key || $value == 'object' && $k == $key){
	       			return true;
	       		}
	       	}
       	}

		return parent::hasGetMutator($key);
	}


    /**
     * Handle dynamic method calls into the method.
     * Overrided from {@link Eloquent} to implement recognition of the {@link $relationsData} array.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call($method, $parameters) {
        if($this->autohash_attributes){
	       	foreach($this->attributes_schema as $key => $value){
	       		$method_name = 'set'.studly_case($key).'Attribute';
	       		if($value == 'hashed' && $method_name == $method){
	       			$this->hashed_originals[$key] = $parameters[0];
	       			$this->attributes[$key] = Hash::make($parameters[0]);
	       			return;
	       		}
	       	}
       	}

       	if($this->autoserialize_attributes){
	       	foreach($this->attributes_schema as $key => $value){
	       		$method_name = 'set'.studly_case($key).'Attribute';
	       		if($value == 'array' && $method_name == $method){
	       			$this->attributes[$key] = serialize( (array) $parameters[0]);
	       			return;
	       		}
	       		if($value == 'object' && $method_name == $method){
	       			$this->attributes[$key] = serialize( (object) $parameters[0]);
	       			return;
	       		}
	       		$method_name = 'get'.studly_case($key).'Attribute';
	       		if($value == 'array' && $method_name == $method || $value == 'object' && $method_name == $method){
	       			return unserialize($parameters[0]);
	       		}
	       	}
       	}

        return parent::__call($method, $parameters);
    }

    public function sanitizeAttributes(){

    	if(!$this->sanitize_attributes) return;

    	//remove none model atts
    	$this->old_attributes = $this->attributes;

    	$schema = $this->attributes_schema;
        
        if(!isset($schema[$this->getKeyName()])){
            $this->attributes_schema[$this->getKeyName()] = 'int';
            $schema = $this->attributes_schema;
        }
        
    	foreach($this->attributes as $key => $attribute){
    		if(!isset($schema[$key])){
    			unset($this->attributes[$key]);
    		}else{
    			$this->attributes[$key] = $this->sanitizeAttribute($attribute, $schema[$key]);
    		}
    	}
        /*
        foreach($this->rules as $scope => $fields){
            foreach($fields as $key => $rules){
                $rules = explode('|', $rules);
                if(in_array('confirmed', $rules)){
                    $this->attributes[$key.'_confirmation'] = $this->attributes[$key];   
                }
            }
        }
        */
    }

    private function sanitizeAttribute( $value, $type = 'string'){
    	switch($type){
    		case 'int':
    			return (int) $value;
    		break;
    		case 'bool':
    			return (bool) $value;
    		break;
    		default:
    			return $value;
    		break;
    	}
    }


    //build custom messages array based on scope
    private function buildMessages( $scope = 'create' ){
    	$custom_messages = $this->messages['global'];
    	foreach($this->messages[$scope] as $key => $value){
    		$custom_messages[$key] = $value;
    	}
    	return $custom_messages;
    }

    //turn rules array into string based to make it much easier to proccess, must be done to make building scope rules easier
    private function normalizeRules(){
    	foreach($this->rules as $scope => $rules){
    		foreach($rules as $field => $rule){
    			if(is_array($rule)){
    				$this->rules[$scope][$field] = implode('|', $rule);
    			}
    		}
    	}
    }

    //auto add props to unique rules - allows you to define a custom rule with additional where clauses or builds from model info, this turns rules into array format, but later normalized to string based
    private function buildUniqueRules() {

		$rulescopes = $this->rules;

		foreach($rulescopes as $scope => &$rules){

	        foreach ($rules as $field => &$ruleset) {
	            // If $ruleset is a pipe-separated string, switch it to array
	            $ruleset = (is_string($ruleset))? explode('|', $ruleset) : $ruleset;

	            foreach ($ruleset as &$rule) {
					if (str_contains($rule, 'unique') && str_contains($rule, '{id}') == false) {
						$params = explode(',', $rule);

						$uniqueRules = array();

						// Append table name if needed
						$table = explode(':', $params[0]);
						if (count($table) == 1)
							$uniqueRules[1] = $this->table;
						else
							$uniqueRules[1] = $table[1];

						// Append field name if needed
						if (count($params) == 1)
							$uniqueRules[2] = $field;
						else
							$uniqueRules[2] = $params[1];

						$uniqueRules[3] = $this->getKey();
				        $uniqueRules[4] = $this->getKeyName();

						$rule = 'unique:' . implode(',', $uniqueRules);
					}elseif(str_contains($rule, 'unique') && str_contains($rule, '{id}')){  
						$rule = str_replace('{id}', $this->getKey(), $rule);
					} // end if str_contains
	              
	            } // end foreach ruleset
	        }

    	}
        
        $this->rules = $rulescopes;
    }
    
    
    private function buildIfDirtyRules() {

		$rulescopes = $this->rules;

		foreach($rulescopes as $scope => &$rules){

	        foreach ($rules as $field => &$ruleset) {
	            // If $ruleset is a pipe-separated string, switch it to array
	            $ruleset = (is_string($ruleset))? explode('|', $ruleset) : $ruleset;

	            foreach ($ruleset as &$rule) {
					if (str_contains($rule, '_if_dirty')) {
                        if($this->isDirty($field) || !$this->exists){
						  $rule = str_replace('_if_dirty', '', $rule);
                        }else{
                            $rule = '';
                        }
					}
	            } // end foreach ruleset
	        }
    	}
        $this->rules = $rulescopes;
    }

    private function buildValidationRules($scope = 'create'){
    	$this->buildUniqueRules();
        $this->buildIfDirtyRules();
    	$this->normalizeRules();
    	$rules = $this->rules['global'];
    	foreach($this->rules[$scope] as $key => $value){
    		if(isset($rules[$key])){
    			$rules[$key] .= '|'.$value;
    		}else{
    			$rules[$key] = $value;
    		}
    	}
    	return $rules;
    }

    //we merge the original input first for confirmed fields that are needed to check (as if not in the schema they get removed)
    //we then add the actual model values which replace any original values
    //and finally we replace actual values with none-hashed values if there are any (so validation is done on plain text versions of the fields)
    private function buildValidationValues(){
        return array_merge($this->old_attributes, $this->attributes, $this->hashed_originals);
    }

    //get validator based on scope
    private function getValidator($scope = 'create'){
    	$rules = $this->buildValidationRules($scope);

    	$custom_messages = $this->buildMessages($scope);

    	$validation_values = $this->buildValidationValues();

    	return Validator::make($validation_values, $rules, $custom_messages);
    }

    //pre save check (if desired) does the same job but doesnt attempt to save
    public function validates(){
    	if($this->exists){
    		return $this->validateUpdate();
    	}else{
    		return $this->validateCreate();
    	}
    }

    //check validation on new instance
    public function validateCreate(){

    	$validator = $this->getValidator('create');

    	if($validator->fails()){
    		$this->errors = $validator->messages();
    		return false;
    	}

    	return true;
    }

    //check validation on existing instance
    public function validateUpdate(){

    	$validator = $this->getValidator('update');

    	if($validator->fails()){
    		$this->errors = $validator->messages();
    		return false;
    	}	

    	return true;
    }

    //get the errors bag
    public function errors(){
    	return $this->errors;
    }

}