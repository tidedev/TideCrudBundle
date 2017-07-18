<?php
namespace Tide\TideCrudBundle\Twig;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Tide\TideCrudBundle\Helpers\CrudHelper;

class CrudExtension extends \Twig_Extension
{
	private $crudHelper;

	public function __construct(ContainerInterface $container)
	{
		$this->crudHelper = $container->get("tidecrud.crud_helper");
	}


	public function getFunctions(){
		return array(
			new \Twig_SimpleFunction('renderEntityField', array($this, 'renderEntityField'), array('is_safe' => array('html'), 'needs_environment' => true))
		);
	}

	public function renderEntityField($item, $fieldMetadata){
		return $this->crudHelper->renderEntityField($item, $fieldMetadata);
	}


	public function getName()
	{
		return 'tidecrud_extension';
	}
}