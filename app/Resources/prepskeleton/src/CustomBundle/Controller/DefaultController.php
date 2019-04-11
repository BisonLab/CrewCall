<?php

namespace CustomBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/ping", name="ping")
     */
    public function pingAction(Request $request)
    {
        $response = $request->request->all();
        $response = array_merge($response, $request->query->all());
        $response['response'] = "ACK";
        $response['code'] = "42";
        return $this->returnRestData($request, $response);
    }
}
