<?php
namespace Tide\TideCrudBundle\Helpers;

use Symfony\Component\Form\Form;

class FormErrorsSerializer {

	private $errors=array();
    public function serializeFormErrors(Form $form, $flat_array = false, $add_form_name = false, $glue_keys = '_')
    {
	    $this->errors['fields'] = array();

        foreach ($form->getErrors() as $error) {
	        $myerror = array();
	        $myerror['field']= 'key';
	        $myerror['error']=$error->getMessage();
	        $this->errors['fields'][] = $myerror;

        }

	    $this->errors['fields'][] = $this->serialize($form);
        if ($flat_array) {
            $errors['fields'] = $this->arrayFlatten($this->errors['fields'],
                $glue_keys, (($add_form_name) ? $form->getName() : ''));
        }

        return $this->errors;
    }

    private function serialize(Form $form)
    {
        $local_errors = array();
        foreach ($form->getIterator() as $key => $child) {

            foreach ($child->getErrors() as $error){
	            $myerror = array();
	            $myerror['field']=$key;
                $myerror['error']=$error->getMessage();
                $local_errors[]=$myerror;
            }

            if (count($child->getIterator()) > 0) {
				$errors = $this->serialize($child);
				if(count($errors)>0)
                    $local_errors[$key] = $errors;
            }
        }

        return $local_errors;
    }

    private function arrayFlatten($array, $separator = "_", $flattened_key = '') {
        $flattenedArray = array();
        foreach ($array as $key => $value) {

            if(is_array($value)) {

                $flattenedArray = array_merge($flattenedArray,
                    $this->arrayFlatten($value, $separator,
                        (strlen($flattened_key) > 0 ? $flattened_key . $separator : "") . $key)
                );

            } else {
                $flattenedArray[(strlen($flattened_key) > 0 ? $flattened_key . $separator : "") . $key] = $value;
            }
        }
        return $flattenedArray;
    }

}