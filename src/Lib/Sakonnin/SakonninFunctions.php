<?php

namespace App\Lib\Sakonnin;

/*
 */

class SakonninFunctions implements \BisonLab\SakonninBundle\Lib\Sakonnin\SakonninFunctionsInterface
{
    protected $container;

    public $callback_functions = array();

    public function __construct($container, $options = array())
    {
        $this->container = $container;
    }

    public function getCallbackFunctions()
    {
        return $this->callback_functions;
    }

    public function getForwardFunctions()
    {
        return $this->forward_functions;
    }
}
