<?php
namespace Tide\TideCrudBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tide\TideCrudBundle\Helpers\CrudHelper;

class CrudExtension extends \Twig_Extension
{
	private $container;

	public function __construct(ContainerInterface $container)
	{
		$this->container = $container;
	}


	public function getFunctions(){
		return array(
			new \Twig_SimpleFunction('renderEntityField', array($this, 'renderEntityField'), array('is_safe' => array('html'), 'needs_environment' => true))
		);
	}

	public function renderEntityField(\Twig_Environment $twig, $item, $fieldMetadata){
		return $this->container->get("tidecrud.crud_helper")->renderEntityField($item, $fieldMetadata["name"]);
	}


	public function getName()
	{
		return 'tidecrud_extension';
	}
}