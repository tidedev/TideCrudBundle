<?php

namespace Tide\TideCrudBundle\Controller;

use Doctrine\Orm\EntityRepository;

use Doctrine\ORM\QueryBuilder;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\Form\Extension\Core\Type\FormType;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

abstract class CrudController extends Controller
{

    const DEFAULT_TEMPLATES = [
    "list"=>"@TideCrud/default/list.html.twig",
    "show"=>"@TideCrud/default/show.html.twig",
    "new"=>"@TideCrud/default/new.html.twig",
    "edit"=>"@TideCrud/default/edit.html.twig",
    ];

	/**
	 * @return array
	 */
	abstract function getListFields();

	/**
	 * @return array
	 */
	abstract function getShowFields();

	/**
	 * @return array
	 */
	abstract function getSearchFields();


	/**
	 * @return object
	 */
	abstract function getNewEntity();

	/**
	 * @return FormType
	 */
	abstract function getEntityFormClass();

	/**
	 * @return EntityRepository
	 */
	abstract function getRepository();

	public function getFieldsMetadata($fields){
		$fieldsArray = [];
		foreach ($fields as $field){
			$type = $this->getEntityManager()->getClassMetadata(get_class($this->getNewEntity()))->getTypeOfField($field);
			$fieldsArray[] =["name"=>$field, "type"=>$type];
		}
		return $fieldsArray;

	}

    /**
     * @return array
     */
    public function getCustomTwigs(){
        return [];
    }

	/**
	 * @return array
	 */
	private function getCrudTwigs(){

	    $merge = array_merge(self::DEFAULT_TEMPLATES, $this->getCustomTwigs());
		return $merge;
	}

	public function prePersistNew($entity, Request $request){
		//Implementar este metodo para hacer algo con la entidad antes de persistirla en base de datos
	}

	public function postPersistNew($entity, Request $request){
		//Implementar este metodo para hacer algo con la entidad después de persistirla en base de datos
	}

	public function prePersistEdit($entity, Request $request){
		//Implementar este metodo para hacer algo con la entidad antes de persistirla en base de datos
	}

	public function postPersistEdit($entity, Request $request){
		//Implementar este metodo para hacer algo con la entidad después de persistirla en base de datos
	}

	/**
	 * @return \Doctrine\Common\Persistence\ObjectManager|object
	 */
	public function getEntityManager(){
		return $this->getDoctrine()->getManager();
	}

	public function getEntityName(){
		return strtolower((new \ReflectionClass($this->getNewEntity()))->getShortName());
	}

	public function getEntityClassName(){
		return (new \ReflectionClass($this->getNewEntity()))->getName();
	}
	/**
	 * Lists all entities.
	 */
	public function listAction(Request $request, $responseType="html", QueryBuilder $customDql = null)
	{
		$filters = $this->getRequestFilters($request);
		$pagination = $this->get("tidecrud.crud_helper")->paginate($this->getEntityClassName(),$customDql, $filters);

		if($responseType=="html"){
			return $this->render($this->getCrudTwigs()["list"], array(
				'entities' => $pagination["rows"],
				'entityName' => $this->getEntityName(),
				'fields' => $this->getFieldsMetadata($this->getListFields())
				));
		}
		else{
			return new JsonResponse( ["total"=>$pagination["total"], "rows"=>$this->get("tidecrud.crud_helper")->tableSerialization($pagination["rows"], $this->getListFields())] );
		}

	}


	public function newAction(Request $request, $options= null)
	{
	    if(!isset($options["form"]))
	        $form = $this->getEntityFormClass();

		$entity = $this->getNewEntity();
		$form = $this->createForm($form, $entity, ["action"=>$this->generateUrl($this->getEntityName().'_new'), "method"=>"post"]);
		$form->handleRequest($request);
		$translator = $this->get('translator');

		if(!$request->request->get("rebuild")) {
			if ( $form->isSubmitted() ) {
				if ( $form->isValid() ) {
					$em = $this->getDoctrine()->getManager();
					$this->prePersistNew($entity, $request);
					$em->persist( $entity );
					$em->flush();
					$this->postPersistNew($entity, $request);
					$response = array( 'response' => 'success', 'message' => ucfirst($translator->trans($this->getEntityName()))." ".$translator->trans('creado') );
                    $response["entity"] = json_decode($this->get("serializer")->serialize($entity, 'json', SerializationContext::create()->setGroups(array('main'))));
					return new JsonResponse($response);
				} else {
					$errors = $this->get( 'tidecrud.form_serializer' )->serializeFormErrors( $form, true, true );
					return new JsonResponse( array( 'response' => 'error', 'errors' => $errors ) );
				}
			}
		}

		return $this->render($this->getCrudTwigs()["new"], array(
			'entity' => $entity,
			'entityName' => $this->getEntityName(),
			'form' => $form->createView(),
		));
	}

	/**
	 * Finds and displays an entity.
	 * @param $id integer
	 * @return Response
	 */
	public function showAction($id)
	{
		$entity = $this->getRepository()->find($id);
		return $this->render($this->getCrudTwigs()["show"], array(
			'entity' => $entity,
			'entityName' => $this->getEntityName(),
			'fields' => $this->getFieldsMetadata($this->getShowFields())
		));
	}

	/**
	 * Displays a form to edit an existing entity.
	 * @param $id integer
	 * @return Response
	 */
	public function editAction(Request $request, $id)
	{
		$entity = $this->getRepository()->find($id);
		$editForm = $this->createForm($this->getEntityFormClass(), $entity, ["action"=>$this->generateUrl($this->getEntityName().'_edit', ["id"=>$entity->getId()]), "method"=>"post"]);
		$editForm->handleRequest($request);

		if(!$request->request->get("rebuild")) {

			if ( $editForm->isSubmitted() ) {
				if ( $editForm->isValid() ) {
					$this->prePersistEdit($entity,$request);
					$this->getDoctrine()->getManager()->flush();
					$this->postPersistEdit($entity,$request);
					$translator = $this->get('translator');
					return new JsonResponse( array( 'response' => 'success', 'message' => ucfirst($translator->trans($this->getEntityName())). $translator->trans('actualizado') ) );
				} else {
					$errors = $this->get( 'tidecrud.form_serializer' )->serializeFormErrors( $editForm, true, true );

					return new JsonResponse( array( 'response' => 'error', 'errors' => $errors ) );
				}
			}
		}

		return $this->render($this->getCrudTwigs()["edit"], array(
			'entity' => $entity,
			'entityName' => $this->getEntityName(),
			'edit_form' => $editForm->createView()
		));
	}

	/**
	 * @param $id integer
	 * @return Response
	 */
	public function deleteAction(Request $request, $id)
	{
		$entity = $this->getRepository()->find($id);
		$em = $this->getDoctrine()->getManager();
		$em->remove($entity);
		$em->flush();
		$translator = $this->get('translator');
		return new JsonResponse(array('response' => 'success', 'message' =>  ucfirst($translator->trans($this->getEntityName())). $translator->trans('eliminado') ));
	}

	private function  getRequestFilters(Request $request){
		//todo: validar los filtros
		$filters = [];
		/*
	     * @QueryParam(name="order", description="Filter ballots by folio ASC or DESC", requirements="asc|desc", strict=true,nullable=true,  allowBlank=true, default="ASC")
		 * @QueryParam(name="sort", description="Filter ballots by field ASC or DESC", strict=true,nullable=true,  allowBlank=true)
		 * @QueryParam(name="limit", description="Limit the numer of users returned", requirements="\d+", strict=true,nullable=true,  allowBlank=true, default=null)
		 * @QueryParam(name="offset", description="Start offset", requirements="\d+", strict=true,nullable=true,  allowBlank=true, default=null)
		 * @QueryParam(name="search", description="Start offset", allowBlank=true, default=null)
		 */
		$filters["order"] = $request->query->get("order");
		$filters["sort"] = $request->query->get("sort");
		$filters["limit"] = $request->query->get("limit");
		$filters["offset"] = $request->query->get("offset");
		$filters["search"] = $request->query->get("search");
		return $filters;
	}

}
