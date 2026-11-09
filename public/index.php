<?php
/**
 * Front controller: toda petición entra por aquí.
 */
declare(strict_types=1);

define('APP_PATH', dirname(__DIR__) . '/app');

require APP_PATH . '/core/App.php';

App::run();
