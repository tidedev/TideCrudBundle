<?php
namespace Tide\CrudBundle\DependencyInjection;

use Symfony\Component\PropertyAccess\PropertyAccessor;

class CrudExtension extends \Twig_Extension
{
	/** @var PropertyAccessor */
	private $propertyAccessor;

	/** @var \Twig_Environment */
	private $twig;

	public function __construct(PropertyAccessor $propertyAccessor)
	{
		$this->propertyAccessor = $propertyAccessor;
	}


	public function getFunctions(){
		return array(
			new \Twig_SimpleFunction('renderEntityField', array($this, 'renderEntityField'), array('is_safe' => array('html'), 'needs_environment' => true))
		);
	}

	public function renderEntityField(\Twig_Environment $twig, $item, $fieldMetadata){
		$this->twig = $twig;
		$fieldName = $fieldMetadata['name'];
		$fieldMetadata["value"] =  $this->propertyAccessor->getValue($item, $fieldName);
		$fieldType = gettype($fieldMetadata["value"]);
		return $twig->render("@MDCCrud/default/fields/".$fieldType.".html.twig", $fieldMetadata);
	}


	public function getName()
	{
		return 'mdccrud_extension';
	}
}