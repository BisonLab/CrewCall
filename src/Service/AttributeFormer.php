<?php

namespace App\Service;

/* 
 * This one looks up in places for forms handling attributes.
 * This is so the users can "edit" attributes in an easier way.
 * Plan once was jsonschema, but I'll do it simpler for now.
 *
 * This is basically for adding custom attributes. If there are attributes
 * every instance need it can just as well be a field in the Entity.
 *
 * Anyway, bo going through this service the underlying way to handle it all
 * can be changed without too much hassle.
 */
class AttributeFormer
{
    private $form_factory;

    public function __construct($form_factory)
    {
        $this->form_factory = $form_factory;
    }

    public function getForms($frog)
    {
        $forms = [];
        if (class_exists('CustomBundle\Lib\AttributeFormer\AttributeFormer')) {
            $cs = new \CustomBundle\Lib\AttributeFormer\AttributeFormer(
                $this->form_factory);
            $forms = $cs->getForms($frog);
        }
        return $forms;
    }

    public function getEditForms($frog)
    {
        $forms = $this->getForms($frog);
        $prepped = [];
        foreach ($forms as $form) {
            $prepped[] = $this->prepEditForm($form, $frog);
        }
        return $prepped;
    }

    public function prepEditForm($form, $frog)
    {
        foreach ($frog->getAttributes() as $key => $val) {
            if (isset($form[$key]))
                $form->get($key)->setData($val);
        }
        return $form->createView();
    }

    public function updateForms(&$frog, $request)
    {
        $forms = $this->getForms($frog);
        foreach ($forms as $form) {
            $form->handleRequest($request);
            $formdata = $form->getData();
            foreach ($formdata as $key => $val) {
                $frog->setAttribute($key, $val);
            }
        }
        return true;
    }
}
