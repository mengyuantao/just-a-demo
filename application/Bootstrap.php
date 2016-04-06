<?php

class Bootstrap extends Yaf_Bootstrap_Abstract {

    public function _initLoader() {
        Yaf_Loader::import(APP_PATH . "/vendor/autoload.php");
    }
}
