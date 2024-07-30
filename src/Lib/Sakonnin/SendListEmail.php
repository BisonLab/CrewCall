<?php

namespace App\Lib\Sakonnin;

use BisonLab\SakonninBundle\Entity\MessageContext;
use BisonLab\SakonninBundle\Lib\Functions\CommonFunctions;

/*
 */

class SendListEmail
{
    use CommonFunctions;

    public $callback_functions = [];

    /*
     */
    public function execute($options = array())
    {
        $message = $options['message'];
        $this->sendMail($message, null, $options);

        return true;
    }

    public $forward_functions = [
        'sendlistemail' => array(
            'class' => 'App\Lib\Sakonnin\SendListEmail',
            'description' => "Send mail to specified email address and log to Event",
            'attribute_spec' => null,
            'needs_attributes' => false,
        ),
    ];
}
