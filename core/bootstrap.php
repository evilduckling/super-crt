<?php

/**
 *
 *  Bootstrap CRT application.
 *
 *
 */

ignore_user_abort(TRUE);
mb_internal_encoding('UTF-8');

define("CRT_CORE_DIRECTORY", dirname(__FILE__));
define("CRT_ROOT_DIRECTORY", realpath(CRT_CORE_DIRECTORY.'/..'));
define("CRT_APPLICATION_DIRECTORY", CRT_ROOT_DIRECTORY.'/application');
define("CRT_CONF_DIRECTORY", CRT_ROOT_DIRECTORY.'/conf');
define("CRT_LIB_DIRECTORY", CRT_ROOT_DIRECTORY.'/lib');

define("CRT_MODEL_DIRECTORY", CRT_APPLICATION_DIRECTORY.'/model');
define("CRT_VIEW_DIRECTORY", CRT_APPLICATION_DIRECTORY.'/view');
define("CRT_CONTROLLER_DIRECTORY", CRT_APPLICATION_DIRECTORY.'/controller');

require_once(CRT_CORE_DIRECTORY.'/core.php');
require_once(CRT_CORE_DIRECTORY.'/misc.php');
require_once(CRT_CONF_DIRECTORY.'/crt.php');
require_once(CRT_CORE_DIRECTORY.'/dbWrapper.php');
require_once(CRT_LIB_DIRECTORY.'/translation.php');

$controllerRequested = str_replace('?'.$_SERVER['QUERY_STRING'], '', $_SERVER['REQUEST_URI']);

if($controllerRequested !== NULL) {

	if(substr($controllerRequested, -1) === '/') {
		$controllerRequested .= 'index';
	}
	if(strpos($controllerRequested, '/') === 0) {
		$controllerRequested = substr($controllerRequested, 1);
	}

	$pieces = explode('/', $controllerRequested);
	$className = ucfirst(array_pop($pieces));
	$controllerFileName = CRT_CONTROLLER_DIRECTORY.'/'.$controllerRequested.'.ctrl.php';
	$viewFileName = CRT_VIEW_DIRECTORY.'/'.$controllerRequested.'.view.php';

	if(is_file($controllerFileName) === FALSE) {
		$viewFileName = CRT_VIEW_DIRECTORY.'/error.view.php';
		$className = 'Error';
	} else {
		require_once($controllerFileName);
	}

	$controllerClassName = $className.'Controller';
	$controllerClass = new $controllerClassName;
	$controllerClass->crtCore();

	// Handle possible redirection
	if($controllerClass->forward !== NULL) {
		header('Location: '.$controllerClass->forward);
	}

	$data = $controllerClass->getData();

	if(is_file($viewFileName)) {
		require_once($viewFileName);
	} else if($className !== 'Error') {
		throw new Exception("File: ".$viewFileName." does not exist.");
	}

	$viewClassName = $className.'View';
	$viewClass = new $viewClassName;
	$viewClass->setData($data);
	$viewClass->crtCore();

} else {
	throw new Exception("Missing URL parameters");
}

if(isset($_GET['dump']) and $_GET['dump'] === '1') {
	DbHandler::dump();
}

unset($controllerClass);
unset($viewClass);

