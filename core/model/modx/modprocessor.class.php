<?php
/**
 * modProcessor
 *
 * @package modx
 */
/**
 * Abstracts a MODX processor, handling its response and error formatting.
 *
 * {@inheritdoc}
 *
 * @package modx
 */
abstract class modProcessor {
    /**
     * A reference to the modX object.
     * @var modX $modx
     */
    public $modx = null;
    /**
     * The absolute path to this processor
     * @var string $path
     */
    public $path = '';
    /**
     * The array of properties being passed to this processor
     * @var array $properties
     */
    public $properties = array();
    
    /**
     * Creates a modProcessor object.
     *
     * @param modX $modx A reference to the modX instance
     * @param array $properties An array of properties
     */
    function __construct(modX & $modx,array $properties = array()) {
        $this->modx =& $modx;
        $this->properties = $properties;
    }

    /**
     * Set the path of the processor
     * @param string $path The absolute path
     * @return void
     */
    public function setPath($path) {
        $this->path = $path;
    }

    /**
     * Set the runtime properties for the processor
     * @param array $properties The properties, in array and key-value form, to run on this processor
     * @return void
     */
    public function setProperties($properties) {
        unset($properties['HTTP_MODAUTH']);
        $this->properties = array_merge($this->properties,$properties);
    }

    /**
     * Completely unset a property from the properties array 
     * @param string $key
     * @return void
     */
    public function unsetProperty($key) {
        unset($this->properties[$key]);
    }

    /**
     * Return true here to allow access to this processor.
     * 
     * @return boolean
     */
    public function checkPermissions() { return true; }

    /**
     * Can be used to provide custom methods prior to processing. Return true to tell MODX that the Processor
     * initialized successfully. If you return anything else, MODX will output that return value as an error message.
     * 
     * @return boolean
     */
    public function initialize() { return true; }

    /**
     * Load a collection of Language Topics for this processor.
     * Override this in your derivative class to provide the array of topics to load.
     * @return array
     */
    public function getLanguageTopics() {
        return array();
    }
    
    /**
     * Return a success message from the processor.
     * @param string $msg
     * @param mixed $object
     * @return array|string
     */
    public function success($msg = '',$object = null) {
        return $this->modx->error->success($msg,$object);
    }

    /**
     * Return a failure message from the processor.
     * @param string $msg
     * @param mixed $object
     * @return array|string
     */
    public function failure($msg = '',$object = null) {
        return $this->modx->error->failure($msg,$object);
    }

    /**
     * Return whether or not the processor has errors
     * @return boolean
     */
    public function hasErrors() {
        return $this->modx->error->hasError();
    }

    /**
     * Add an error to the field
     * @param string $key
     * @param string $message
     * @return mixed
     */
    public function addFieldError($key,$message = '') {
        return $this->modx->error->addField($key,$message);
    }

    /**
     * Return the proper instance of the derived class. This can be used to override how MODX loads a processor
     * class; for example, when handling derivative classes with class_key settings.
     *
     * @static
     * @param modX $modx A reference to the modX object.
     * @param string $className The name of the class that is being requested.
     * @param array $properties An array of properties being run with the processor
     * @return The class specified by $className
     */
    public static function getInstance(modX &$modx,$className,$properties = array()) {
        /** @var modProcessor $processor */
        $processor = new $className($modx,$properties);
        return $processor;
    }

    /**
     * Run the processor and return the result. Override this in your derivative class to provide custom functionality.
     * Used here for pre-2.2-style processors.
     * 
     * @return mixed
     */
    abstract public function process();

    /**
     * Run the processor, returning a modProcessorResponse object.
     * @return modProcessorResponse
     */
    public function run() {
        if (!$this->checkPermissions()) {
            $o = $this->failure($this->modx->lexicon('permission_denied'));
        } else {
            $topics = $this->getLanguageTopics();
            foreach ($topics as $topic) {
                $this->modx->lexicon->load($topic);
            }

            $initialized = $this->initialize();
            if ($initialized !== true) {
                $o = $this->failure($initialized);
            } else {
                $o = $this->process();
            }
        }
        $response = new modProcessorResponse($this->modx,$o);
        return $response;
    }

    /**
     * Get a specific property.
     * @param string $k
     * @param mixed $default
     * @return mixed
     */
    public function getProperty($k,$default = null) {
        return array_key_exists($k,$this->properties) ? $this->properties[$k] : $default;
    }

    /**
     * Set a property value
     * 
     * @param string $k
     * @param mixed $v
     * @return void
     */
    public function setProperty($k,$v) {
        $this->properties[$k] = $v;
    }

    /**
     * Special helper method for handling checkboxes. Only set value if passed or $force is true, and check for a
     * not empty value or string 'false'.
     * 
     * @param string $k
     * @param boolean $force
     * @return int|null
     */
    public function setCheckbox($k,$force = false) {
        $v = null;
        if ($force || isset($this->properties[$k])) {
            $v = empty($this->properties[$k]) || $this->properties[$k] === 'false' ? 0 : 1;
            $this->setProperty($k,$v);
        }
        return $v;
    }

    /**
     * Get an array of properties for this processor
     * @return array
     */
    public function getProperties() {
        return $this->properties;
    }

    /**
     * Sets default properties that only are set if they don't already exist in the request
     *
     * @param array $properties
     * @return array The newly merged properties array
     */
    public function setDefaultProperties(array $properties = array()) {
        $this->properties = array_merge($properties,$this->properties);
        return $this->properties;
    }

    /**
     * Return arrays of objects (with count) converted to JSON.
     *
     * The JSON result includes two main elements, total and results. This format is used for list
     * results.
     *
     * @access public
     * @param array $array An array of data objects.
     * @param mixed $count The total number of objects. Used for pagination.
     * @return string The JSON output.
     */
    public function outputArray(array $array,$count = false) {
        if ($count === false) { $count = count($array); }
        return '{"total":"'.$count.'","results":'.$this->modx->toJSON($array).'}';
    }

    /**
     * Converts PHP to JSON with JavaScript literals left in-tact.
     *
     * JSON does not allow JavaScript literals, but this function encodes certain identifiable
     * literals and decodes them back into literals after modX::toJSON() formats the data.
     *
     * @access public
     * @param mixed $data The PHP data to be converted.
     * @return string The extended JSON-encoded string.
     */
    public function toJSON($data) {
        if (is_array($data)) {
            array_walk_recursive($data, array(&$this, '_encodeLiterals'));
        }
        return $this->_decodeLiterals($this->modx->toJSON($data));
    }

    /**
     * Encodes certain JavaScript literal strings for later decoding.
     *
     * @access protected
     * @param mixed &$value A reference to the value to be encoded if it is identified as a literal.
     * @param integer|string $key The array key corresponding to the value.
     */
    protected function _encodeLiterals(&$value, $key) {
        if (is_string($value)) {
            /* properly handle common literal structures */
            if (strpos($value, 'function(') === 0
             || strpos($value, 'this.') === 0
             || strpos($value, 'new Function(') === 0
             || strpos($value, 'Ext.') === 0) {
                $value = '@@' . base64_encode($value) . '@@';
             }
        }
    }

    /**
     * Decodes strings encoded by _encodeLiterals to restore JavaScript literals.
     *
     * @access protected
     * @param string $string The JSON-encoded string with encoded literals.
     * @return string The JSON-encoded string with literals restored.
     */
    protected function _decodeLiterals($string) {
        $pattern = '/"@@(.*?)@@"/';
        $string = preg_replace_callback(
            $pattern,
            create_function('$matches', 'return base64_decode($matches[1]);'),
            $string
        );
        return $string;
    }

    /**
     * Processes a response from a Plugin Event invocation
     *
     * @param array|string $response The response generated by the invokeEvent call
     * @param string $separator The separator for each event response
     * @return string The processed response.
     */
    public function processEventResponse($response,$separator = "\n") {
        if (is_array($response)) {
            $result = false;
            foreach ($response as $msg) {
                if (!empty($msg)) {
                    $result[] = $msg;
                }
            }
            $result = implode($separator,$result);
        } else {
            $result = $response;
        }
        return $result;
    }
}

/**
 * A utility class for pre-2.2-style, or flat file, processors.
 *
 * @package modx
 */
class modDeprecatedProcessor extends modProcessor {
    /**
     * Rather than load a class for processing, include the processor file directly.
     * 
     * {@inheritDoc}
     * @return mixed
     */
    public function process() {
        $modx =& $this->modx;
        $scriptProperties = $this->getProperties();
        $o = include $this->path;
        return $o;
    }
}

/**
 * A utility class used for defining driver-specific processors
 * 
 * @package modx
 */
abstract class modDriverSpecificProcessor extends modProcessor {
    public static function getInstance(modX &$modx,$className,$properties = array()) {
        $className .= '_'.$modx->getOption('dbtype');
        /** @var modProcessor $processor */
        $processor = new $className($modx,$properties);
        return $processor;
    }
}

/**
 * Base class for object-specific processors
 * @abstract
 */
abstract class modObjectProcessor extends modProcessor {
    /** @var xPDOObject|modAccessibleObject $object The object being grabbed */
    public $object;
    /** @var string $objectType The object "type", this will be used in various lexicon error strings */
    public $objectType = 'object';
    /** @var string $classKey The class key of the Object to iterate */
    public $classKey;
    /** @var string $primaryKeyField The primary key field to grab the object by */
    public $primaryKeyField = 'id';
    /** @var string $permission The Permission to use when checking against */
    public $permission = '';
    /** @var array $languageTopics An array of language topics to load */
    public $languageTopics = array();

    public function checkPermissions() {
        return !empty($this->permission) ? $this->modx->hasPermission($this->permission) : true;
    }
    public function getLanguageTopics() {
        return $this->languageTopics;
    }
}

/**
 * A utility abstract class for defining get-based processors
 * @abstract
 */
abstract class modObjectGetProcessor extends modObjectProcessor {
    /** @var boolean $checkViewPermission If set to true, will check the view permission on modAccessibleObjects */
    public $checkViewPermission = true;
    
    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize() {
        $primaryKey = $this->getProperty($this->primaryKeyField,false);
        if (empty($primaryKey)) return $this->modx->lexicon($this->objectType.'_err_ns');
        $this->object = $this->modx->getObject($this->classKey,$primaryKey);
        if (empty($this->object)) return $this->modx->lexicon($this->objectType.'_err_nfs',array($this->primaryKeyField => $primaryKey));

        if ($this->checkViewPermission && $this->object instanceof modAccessibleObject && !$this->object->checkPolicy('view')) {
            return $this->modx->lexicon('access_denied');
        }

        return true;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function process() {
        $this->beforeOutput();
        return $this->cleanup();
    }

    /**
     * Return the response
     * @return array
     */
    public function cleanup() {
        return $this->success('',$this->object->toArray());
    }

    /**
     * Used for adding custom data in derivative types
     * @return void
     */
    public function beforeOutput() { }
}

/**
 * A utility abstract class for defining getlist-based processors
 * @abstract
 */
abstract class modObjectGetListProcessor extends modObjectProcessor {
    /** @var string $defaultSortField The default field to sort by */
    public $defaultSortField = 'name';
    /** @var string $defaultSortDirection The default direction to sort */
    public $defaultSortDirection = 'ASC';
    /** @var boolean $checkListPermission If true and object is a modAccessibleObject, will check list permission */
    public $checkListPermission = true;
    /** @var int $currentIndex The current index of successful iteration */
    public $currentIndex = 0;

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize() {
        $this->setDefaultProperties(array(
            'start' => 0,
            'limit' => 20,
            'sort' => $this->defaultSortField,
            'dir' => $this->defaultSortDirection,
            'combo' => false,
            'query' => '',
        ));
        return true;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function process() {
        $beforeQuery = $this->beforeQuery();
        if ($beforeQuery !== true) {
            return $this->failure($beforeQuery);
        }
        $data = $this->getData();
        $list = $this->iterate($data);
        return $this->outputArray($list,$data['total']);
    }

    /**
     * Allow stoppage of process before the query
     * @return boolean
     */
    public function beforeQuery() {
        return true;
    }

    /**
     * Iterate across the data
     *
     * @param array $data
     * @return array
     */
    public function iterate(array $data) {
        $list = array();
        $list = $this->beforeIteration($list);
        $this->currentIndex = 0;
        /** @var xPDOObject|modAccessibleObject $object */
        foreach ($data['results'] as $object) {
            if ($this->checkListPermission && $object instanceof modAccessibleObject && !$object->checkPolicy('list')) continue;
            $objectArray = $this->prepareRow($object);
            if (!empty($objectArray) && is_array($objectArray)) {
                $list[] = $objectArray;
                $this->currentIndex++;
            }
        }
        $list = $this->afterIteration($list);
        return $list;
    }

    /**
     * Can be used to insert a row before iteration
     * @param array $list
     * @return array
     */
    public function beforeIteration(array $list) {
        return $list;
    }

    /**
     * Can be used to insert a row after iteration
     * @param array $list
     * @return array
     */
    public function afterIteration(array $list) {
        return $list;
    }

    /**
     * Get the data of the query
     * @return array
     */
    public function getData() {
        $data = array();
        $limit = intval($this->getProperty('limit'));
        $start = intval($this->getProperty('start'));

        /* query for chunks */
        $c = $this->modx->newQuery($this->classKey);
        $c = $this->prepareQueryBeforeCount($c);
        $data['total'] = $this->modx->getCount($this->classKey,$c);
        $c = $this->prepareQueryAfterCount($c);

        $sortClassKey = $this->getSortClassKey();
        $sortKey = $this->modx->getSelectColumns($sortClassKey,$this->getProperty('sortAlias',$sortClassKey),'',array($this->getProperty('sort')));
        if (empty($sortKey)) $sortKey = $this->getProperty('sort');
        $c->sortby($sortKey,$this->getProperty('dir'));
        if ($limit > 0) {
            $c->limit($limit,$start);
        }

        $data['results'] = $this->modx->getCollection($this->classKey,$c);
        return $data;
    }

    /**
     * Can be used to provide a custom sorting class key for the default sorting columns
     * @return string
     */
    public function getSortClassKey() {
        return $this->classKey;
    }

    /**
     * Can be used to adjust the query prior to the COUNT statement
     *
     * @param xPDOQuery $c
     * @return xPDOQuery
     */
    public function prepareQueryBeforeCount(xPDOQuery $c) {
        return $c;
    }

    /**
     * Can be used to prepare the query after the COUNT statement
     * 
     * @param xPDOQuery $c
     * @return xPDOQuery
     */
    public function prepareQueryAfterCount(xPDOQuery $c) {
        return $c;
    }

    /**
     * Prepare the row for iteration
     * @param xPDOObject $object
     * @return array
     */
    public function prepareRow(xPDOObject $object) {
        return $object->toArray();
    }
}

/**
 * A utility abstract class for defining create-based processors
 * @abstract
 */
abstract class modObjectCreateProcessor extends modObjectProcessor {
    /** @var string $beforeSaveEvent The name of the event to fire before saving */
    public $beforeSaveEvent = '';
    /** @var string $afterSaveEvent The name of the event to fire after saving */
    public $afterSaveEvent = '';

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize() {
        $this->object = $this->modx->newObject($this->classKey);
        return true;
    }

    /**
     * Process the Object create processor
     * {@inheritDoc}
     * @return mixed
     */
    public function process() {
        /* Run the beforeSet method before setting the fields, and allow stoppage */
        $canSave = $this->beforeSet();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }

        $this->object->fromArray($this->getProperties());

        /* run the before save logic */
        $canSave = $this->beforeSave();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }

        /* run object validation */
        if (!$this->object->validate()) {
            /** @var modValidator $validator */
            $validator = $this->object->getValidator();
            if ($validator->hasMessages()) {
                foreach ($validator->getMessages() as $message) {
                    $this->addFieldError($message['field'],$this->modx->lexicon($message['message']));
                }
            }
        }

        $preventSave = $this->fireBeforeSaveEvent();
        if (!empty($preventSave)) {
            return $this->failure($preventSave);
        }

        $canSave = $this->beforeSave();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }

        /* save element */
        if ($this->object->save() == false) {
            $this->modx->error->checkValidation($this->object);
            return $this->failure($this->modx->lexicon($this->objectType.'_err_save'));
        }

        $this->afterSave();

        $this->fireAfterSaveEvent();
        $this->logManagerAction();
        return $this->cleanup();
    }

    /**
     * Return the success message
     * @return array
     */
    public function cleanup() {
        return $this->success('',$this->object);
    }

    /**
     * Override in your derivative class to do functionality before the fields are set on the object
     * @return boolean
     */
    public function beforeSet() { return !$this->hasErrors(); }

    /**
     * Override in your derivative class to do functionality after save() is run
     * @return boolean
     */
    public function beforeSave() { return !$this->hasErrors(); }

    /**
     * Override in your derivative class to do functionality before save() is run
     * @return boolean
     */
    public function afterSave() { return true; }


    /**
     * Fire before save event. Return true to prevent saving.
     * @return boolean
     */
    public function fireBeforeSaveEvent() {
        $preventSave = false;
        if (!empty($this->beforeSaveEvent)) {
            /** @var boolean|array $OnBeforeFormSave */
            $OnBeforeFormSave = $this->modx->invokeEvent($this->beforeSaveEvent,array(
                'mode'  => modSystemEvent::MODE_NEW,
                'data' => $this->object->toArray(),
                $this->primaryKeyField => 0,
                $this->objectType => &$this->object,
            ));
            if (is_array($OnBeforeFormSave)) {
                $preventSave = false;
                foreach ($OnBeforeFormSave as $msg) {
                    if (!empty($msg)) {
                        $preventSave .= $msg."\n";
                    }
                }
            } else {
                $preventSave = $OnBeforeFormSave;
            }
        }
        return $preventSave;
    }

    /**
     * Fire the after save event
     * @return void
     */
    public function fireAfterSaveEvent() {
        if (!empty($this->afterSaveEvent)) {
            $this->modx->invokeEvent($this->afterSaveEvent,array(
                'mode' => modSystemEvent::MODE_NEW,
                $this->primaryKeyField => $this->object->get($this->primaryKeyField),
                $this->objectType => &$this->object,
            ));
        }
    }

    /**
     * @param array $criteria
     * @return int
     */
    public function doesAlreadyExist(array $criteria) {
        return $this->modx->getCount($this->classKey,$criteria);
    }

    /**
     * Log the removal manager action
     * @return void
     */
    public function logManagerAction() {
        $this->modx->logManagerAction($this->objectType.'_create',$this->classKey,$this->object->get($this->primaryKeyField));
    }
}

/**
 * A utility abstract class for defining update-based processors
 * @abstract
 */
abstract class modObjectUpdateProcessor extends modObjectProcessor {
    public $checkSavePermission = true;
    /** @var string $beforeSaveEvent The name of the event to fire before saving */
    public $beforeSaveEvent = '';
    /** @var string $afterSaveEvent The name of the event to fire after saving */
    public $afterSaveEvent = '';

    public function initialize() {
        $primaryKey = $this->getProperty($this->primaryKeyField,false);
        if (empty($primaryKey)) return $this->modx->lexicon($this->objectType.'_err_ns');
        $this->object = $this->modx->getObject($this->classKey,$primaryKey);
        if (empty($this->object)) return $this->modx->lexicon($this->objectType.'_err_nfs',array($this->primaryKeyField => $primaryKey));

        if ($this->checkSavePermission && $this->object instanceof modAccessibleObject && !$this->object->checkPolicy('save')) {
            return $this->modx->lexicon('access_denied');
        }
        return true;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function process() {
        /* Run the beforeSet method before setting the fields, and allow stoppage */
        $canSave = $this->beforeSet();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }

        $this->object->fromArray($this->getProperties());

        /* Run the beforeSave method and allow stoppage */
        $canSave = $this->beforeSave();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }

        /* run object validation */
        if (!$this->object->validate()) {
            /** @var modValidator $validator */
            $validator = $this->object->getValidator();
            if ($validator->hasMessages()) {
                foreach ($validator->getMessages() as $message) {
                    $this->addFieldError($message['field'],$this->modx->lexicon($message['message']));
                }
            }
        }

        /* run the before save event and allow stoppage */
        $preventSave = $this->fireBeforeSaveEvent();
        if (!empty($preventSave)) {
            return $this->failure($preventSave);
        }

        if ($this->saveObject() == false) {
            return $this->failure($this->modx->lexicon($this->objectType.'_err_save'));
        }
        $this->afterSave();
        $this->fireAfterSaveEvent();
        $this->logManagerAction();
        return $this->cleanup();
    }

    /**
     * Abstract the saving of the object out to allow for transient and non-persistent object updating in derivative
     * classes
     * @return boolean
     */
    public function saveObject() {
        return $this->object->save();
    }

    /**
     * Override in your derivative class to do functionality before the fields are set on the object
     * @return boolean
     */
    public function beforeSet() { return !$this->hasErrors(); }


    /**
     * Override in your derivative class to do functionality before save() is run
     * @return boolean
     */
    public function beforeSave() { return !$this->hasErrors(); }

    /**
     * Override in your derivative class to do functionality after save() is run
     * @return boolean
     */
    public function afterSave() { return true; }

    /**
     * Return the success message
     * @return array
     */
    public function cleanup() {
        return $this->success('',$this->object);
    }


    /**
     * Fire before save event. Return true to prevent saving.
     * @return boolean
     */
    public function fireBeforeSaveEvent() {
        $preventSave = false;
        if (!empty($this->beforeSaveEvent)) {
            /** @var boolean|array $OnBeforeFormSave */
            $OnBeforeFormSave = $this->modx->invokeEvent($this->beforeSaveEvent,array(
                'mode'  => modSystemEvent::MODE_UPD,
                'data' => $this->object->toArray(),
                $this->primaryKeyField => $this->object->get($this->primaryKeyField),
                $this->objectType => &$this->object,
            ));
            if (is_array($OnBeforeFormSave)) {
                $preventSave = false;
                foreach ($OnBeforeFormSave as $msg) {
                    if (!empty($msg)) {
                        $preventSave .= $msg."\n";
                    }
                }
            } else {
                $preventSave = $OnBeforeFormSave;
            }
        }
        return $preventSave;
    }

    /**
     * Fire the after save event
     * @return void
     */
    public function fireAfterSaveEvent() {
        if (!empty($this->afterSaveEvent)) {
            $this->modx->invokeEvent($this->afterSaveEvent,array(
                'mode' => modSystemEvent::MODE_UPD,
                $this->primaryKeyField => $this->object->get($this->primaryKeyField),
                $this->objectType => &$this->object,
            ));
        }
    }

    /**
     * Log the removal manager action
     * @return void
     */
    public function logManagerAction() {
        $this->modx->logManagerAction($this->objectType.'_update',$this->classKey,$this->object->get($this->primaryKeyField));
    }
}

/**
 * A utility abstract class for defining duplicate-based processors
 * @abstract
 */
class modObjectDuplicateProcessor extends modObjectProcessor {
    /** @var boolean $checkSavePermission Whether or not to check the save permission on modAccessibleObjects */
    public $checkSavePermission = true;
    /** @var xPDOObject $newObject The newly duplicated object */
    public $newObject;
    public $nameField = 'name';

    /**
     * {@inheritDoc}
     * @return boolean
     */
    public function initialize() {
        $primaryKey = $this->getProperty($this->primaryKeyField,false);
        if (empty($primaryKey)) return $this->modx->lexicon($this->objectType.'_err_ns');
        $this->object = $this->modx->getObject($this->classKey,$primaryKey);
        if (empty($this->object)) return $this->modx->lexicon($this->objectType.'_err_nfs',array($this->primaryKeyField => $primaryKey));

        if ($this->checkSavePermission && $this->object instanceof modAccessibleObject && !$this->object->checkPolicy('save')) {
            return $this->modx->lexicon('access_denied');
        }

        $this->newObject = $this->modx->newObject($this->classKey);

        return true;
    }

    /**
     * {@inheritDoc}
     * @return mixed
     */
    public function process() {
        /* Run the beforeSet method before setting the fields, and allow stoppage */
        $canSave = $this->beforeSet();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }
        
        $this->newObject->fromArray($this->object->toArray());
        $name = $this->getNewName();
        $this->setNewName($name);

        if ($this->alreadyExists($name)) {
            $this->addFieldError($this->nameField,$this->modx->lexicon($this->objectType.'_err_ae',array('name' => $name)));
        }
        
        $canSave = $this->beforeSave();
        if ($canSave !== true) {
            return $this->failure($canSave);
        }
        
        /* save new chunk */
        if ($this->newObject->save() === false) {
            $this->modx->error->checkValidation($this->newObject);
            return $this->failure($this->modx->lexicon($this->objectType.'_err_duplicate'));
        }

        $this->afterSave();
        $this->logManagerAction();
        return $this->cleanup();
    }

    /**
     * Cleanup and return a response.
     *
     * @return array
     */
    public function cleanup() {
        return $this->success('',$this->newObject);
    }

    /**
     * Override in your derivative class to do functionality before the fields are set on the object
     * @return boolean
     */
    public function beforeSet() { return !$this->hasErrors(); }

    /**
     * Run any logic before the object has been duplicated. May return false to prevent duplication.
     * @return boolean
     */
    public function beforeSave() { return !$this->hasErrors(); }

    /**
     * Run any logic after the object has been duplicated
     * @return boolean
     */
    public function afterSave() { return true; }

    /**
     * Get the new name for the duplicate
     * @return string
     */
    public function getNewName() {
        $name = $this->getProperty($this->nameField);
        $newName = !empty($name) ? $name : $this->modx->lexicon('duplicate_of',array('name' => $this->object->get($this->nameField)));
        return $newName;
    }

    /**
     * Set the new name to the new object
     * @param string $name
     * @return string
     */
    public function setNewName($name) {
        return $this->newObject->set($this->nameField,$name);
    }

    /**
     * Check to see if an object already exists with that name
     * @param string $name
     * @return boolean
     */
    public function alreadyExists($name) {
        return $this->modx->getCount($this->classKey,array(
            $this->nameField => $name,
        )) > 0;

    }

    /**
     * Log a manager action
     * @return void
     */
    public function logManagerAction() {
        $this->modx->logManagerAction($this->objectType.'_duplicate',$this->classKey,$this->newObject->get('id'));
    }
}

/**
 * A utility abstract class for defining remove-based processors
 * @abstract
 */
abstract class modObjectRemoveProcessor extends modObjectProcessor {
    /** @var boolean $checkRemovePermission If set to true, will check the remove permission on modAccessibleObjects */
    public $checkRemovePermission = true;
    /** @var string $beforeRemoveEvent The name of the event to fire before removal */
    public $beforeRemoveEvent = '';
    /** @var string $afterRemoveEvent The name of the event to fire after removal */
    public $afterRemoveEvent = '';

    public function initialize() {
        $primaryKey = $this->getProperty($this->primaryKeyField,false);
        if (empty($primaryKey)) return $this->modx->lexicon($this->objectType.'_err_ns');
        $this->object = $this->modx->getObject($this->classKey,$primaryKey);
        if (empty($this->object)) return $this->modx->lexicon($this->objectType.'_err_nfs',array($this->primaryKeyField => $primaryKey));

        if ($this->checkRemovePermission && $this->object instanceof modAccessibleObject && !$this->object->checkPolicy('remove')) {
            return $this->modx->lexicon('access_denied');
        }
        return true;
    }

    public function process() {
        $canRemove = $this->beforeRemove();
        if ($canRemove !== true) {
            return $this->failure($canRemove);
        }
        $preventRemoval = $this->fireBeforeRemoveEvent();
        if (!empty($preventRemoval)) {
            return $this->failure($preventRemoval);
        }
        
        if ($this->object->remove() == false) {
            return $this->failure($this->modx->lexicon($this->objectType.'_err_remove'));
        }
        $this->afterRemove();
        $this->fireAfterRemoveEvent();
        $this->logManagerAction();
        $this->cleanup();
        return $this->success('',array($this->primaryKeyField => $this->object->get($this->primaryKeyField)));
    }

    /**
     * Can contain pre-removal logic; return false to prevent remove.
     * @return boolean
     */
    public function beforeRemove() { return !$this->hasErrors(); }
    /**
     * Can contain post-removal logic.
     * @return bool
     */
    public function afterRemove() { return true; }

    /**
     * Log the removal manager action
     * @return void
     */
    public function logManagerAction() {
        $this->modx->logManagerAction($this->objectType.'_delete',$this->classKey,$this->object->get($this->primaryKeyField));
    }

    /**
     * After removal, manager action log, and event firing logic
     * @return void
     */
    public function cleanup() {}

    /**
     * If specified, fire the before remove event
     * @return boolean Return false to allow removal; non-empty to prevent it
     */
    public function fireBeforeRemoveEvent() {
        $preventRemove = false;
        if (!empty($this->beforeRemoveEvent)) {
            $response = $this->modx->invokeEvent($this->beforeRemoveEvent,array(
                $this->primaryKeyField => $this->object->get($this->primaryKeyField),
                $this->objectType => &$object,
            ));
            $preventRemove = $this->processEventResponse($response);
        }
        return $preventRemove;
    }

    /**
     * If specified, fire the after remove event
     * @return void
     */
    public function fireAfterRemoveEvent() {
        if (!empty($this->afterRemoveEvent)) {
            $this->modx->invokeEvent($this->afterRemoveEvent,array(
                $this->primaryKeyField => $this->object->get($this->primaryKeyField),
                $this->objectType => &$object,
            ));
        }
    }
}

/**
 * Response class for Processor executions
 *
 * @package modx
 */
class modProcessorResponse {
    /**
     * When there is only a general error
     * @const ERROR_GENERAL
     */
    const ERROR_GENERAL = 'error_general';
    /**
     * When there are only field-specific errors
     * @const ERROR_FIELD
     */
    const ERROR_FIELD = 'error_field';
    /**
     * When there is both field-specific and general errors
     * @const ERROR_BOTH
     */
    const ERROR_BOTH = 'error_both';
    /**
     * The field for the error type
     * @const ERROR_TYPE
     */
    const ERROR_TYPE = 'error_type';

    /**
     * @var modX A reference to the modX object
     */
    public $modx = null;
    /**
     * @var array A reference to the full response
     */
    public $response = null;
    /**
     * @var array A collection of modProcessorResponseError objects for each field-specific error
     */
    public $errors = array();
    /**
     * @var string The error type for this response
     */
    public $error_type = '';

    /**
     * The constructor for modProcessorResponse
     *
     * @param modX $modx A reference to the modX object.
     * @param array $response The array response from the modX.runProcessor method
     */
    function __construct(modX &$modx,$response = array()) {
        $this->modx =& $modx;
        $this->response = $response;
        if ($this->isError()) {
            if (!empty($response['errors']) && is_array($response['errors'])) {
                foreach ($response['errors'] as $error) {
                    $this->errors[] = new modProcessorResponseError($error);
                }
                if (!empty($response['message'])) {
                    $this->error_type = modProcessorResponse::ERROR_BOTH;
                } else {
                    $this->error_type = modProcessorResponse::ERROR_FIELD;
                }
            } else {
                $this->error_type = modProcessorResponse::ERROR_GENERAL;
            }
        }
    }

    /**
     * Returns the type of error for this response
     * @return string The type of error returned
     */
    public function getErrorType() {
        return $this->error_type;
    }

    /**
     * Checks to see if the response is an error
     * @return Returns true if the response was a success, otherwise false
     */
    public function isError() {
        return empty($this->response) || empty($this->response['success']);
    }

    /**
     * Returns true if there is a general status message for the response.
     * @return boolean True if there is a general message
     */
    public function hasMessage() {
        return !empty($this->response['message']) ? true : false;
    }

    /**
     * Gets the general status message for the response.
     * @return string The status message
     */
    public function getMessage() {
        return !empty($this->response['message']) ? $this->response['message'] : '';
    }

    /**
     * Returns the entire response object in array form
     * @return array The array response
     */
    public function getResponse() {
        return $this->response;
    }

    /**
     * Returns true if an object was sent with this response.
     * @return boolean True if an object was sent.
     */
    public function hasObject() {
        return !empty($this->response['object']) ? true : false;
    }

    /**
     * Returns the array object, if is sent in the response
     * @return array The object in the response, usually the object being performed on.
     */
    public function getObject() {
        return !empty($this->response['object']) ? $this->response['object'] : array();
    }

    /**
     * An array of modProcessorResponseError objects for each field-specific error
     * @return array
     */
    public function getFieldErrors() {
        return $this->errors;
    }

    /**
     * Checks to see if there are any field-specific errors in this response
     * @return boolean True if there were field-specific errors
     */
    public function hasFieldErrors() {
        return !empty($this->errors) ? true : false;
    }

    /**
     * Gets all errors and adds them all into an array.
     *
     * @param string $fieldErrorSeparator The separator to use between fieldkey and message for field-specific errors.
     * @return array An array of all errors.
     */
    public function getAllErrors($fieldErrorSeparator = ': ') {
        $errormsgs = array();
        if ($this->hasMessage()) {
            $errormsgs[] = $this->getMessage();
        }
        if ($this->hasFieldErrors()) {
            $errors = $this->getFieldErrors();
            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $errormsgs[] = $error->field.$fieldErrorSeparator.$error->message;
                }
            }
        }
        return $errormsgs;
    }
}
/**
 * An abstraction class of field-specific errors for a processor response
 *
 * @package modx
 */
class modProcessorResponseError {
    /**
     * @var array The error data itself
     */
    public $error = null;
    /**
     * @var string The field key that the error occurred on
     */
    public $field = null;
    /**
     * @var string The message that was sent for the field error
     */
    public $message = '';

    /**
     * The constructor for the modProcessorResponseError class
     *
     * @param array $error An array error response
     */
    function __construct($error = array()) {
        $this->error = $error;
        if (!empty($error['id'])) { $this->field = $error['id']; }
        if (!empty($error['msg'])) { $this->message = $error['msg']; }
    }

    /**
     * Returns the message for the field-specific error
     * @return string
     */
    public function getMessage() {
        return $this->message;
    }

    /**
     * Returns the field key for the field-specific error
     * @return string
     */
    public function getField() {
        return $this->field;
    }

    /**
     * Returns the array data for the field-specific error
     * @return array
     */
    public function getError() {
        return $this->error;
    }
}