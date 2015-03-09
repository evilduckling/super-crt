<?php

function __autoload($class) {

	if(strpos($class, 'View') !== FALSE) {
		require_once(CRT_VIEW_DIRECTORY.'/'.lcfirst(substr($class, 0, -4)).'.view.php');
	}
	if(strpos($class, 'Controller') !== FALSE) {
		require_once(CRT_CONTROLLER_DIRECTORY.'/'.lcfirst(substr($class, 0, -10)).'.ctrl.php');
	}

}

abstract class Object {

	protected $data = array();
	
	public function __get($name) {

		if(strpos($name, 'm') === 0) {
			$handler = new DbHandler();
			return $handler->table(ucfirst(substr($name, 1)));
		}

		return $this->$name;
	}

	public function crtCore() {
		$this->crt();
	}

}

/**
 * Controller
 */
abstract class Controller extends Object {
	
	public $forward = NULL;

	public function getData() {
		return $this->data;
	}

	protected abstract function crt();

}

/**
 * View
 */
abstract class View extends Object {

	public function setData($data = array()) {
		return $this->data = $data;
	}

	protected abstract function crt();

}

/**
 * Ajax View
 */
class AjaxView extends View {

	/**
	 * Flush data through json.
	 */
	protected function crt() {
		echo json_encode($this->data);
	}

}

/**
 * Model
 */
abstract class Bean extends Object {

	protected $database = NULL;

	private $modifiedAttributes= array();

	/** 
	 * Return database name.
	 */
	public function getDatabase() {

		if($this->database === NULL) {
			throw new Exception("Database name must be overridden");
		}
		return $this->database;
	}

	protected function markModified($attribute) {
		array_push($this->modifiedAttributes, $attribute);
	}

	public function getModifiedAttributes() {
		return $this->modifiedAttributes;
	}

	/**
	 * Save a modified Bean to database.
	 */
	public function save() {

		$className = get_class($this);

		if(isset($this->id) and $this->getId() !== NULL) {

			// update database content
			$this->{'m'.$className}
				->field(array_unique($this->modifiedAttributes))
				->whereById((int)$this->getId())
				->update($this);

		} else {

			// Create entry
			$this->{'m'.$className}->insert($this);

			// TODO override ID ?
		}

		$this->modifiedAttributes = array();
	}

}
