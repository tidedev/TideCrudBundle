<?php
namespace Tide\TideCrudBundle\Helpers;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\PropertyAccess\PropertyAccessor;

class CrudHelper {
	const BASE_TWIGS = "@TideCrud/default";

	private $propertyAccessor;
	private $templating;
	private $em;

	public function __construct(ContainerInterface $container)
	{
		$this->propertyAccessor = $container->get("property_accessor");
		$this->templating = $container->get("templating");
		$this->doctrine = $container->get("doctrine");
	}

	public function renderEntityField($item, $field){
		$propertyAccessor = $this->propertyAccessor;
		try{
			$fieldMetadata["value"] =  $propertyAccessor->getValue($item, $field);
		}
		catch (\Exception $e){
			$fieldMetadata["value"] = null;
		}

		$fieldType = gettype($fieldMetadata["value"]);

		if($fieldMetadata["value"] instanceof \DateTime)
			$fieldType = "datetime";

		return $this->templating->render(self::BASE_TWIGS."/fields/".$fieldType.".html.twig", $fieldMetadata);
	}

	public function paginate($entityClass, QueryBuilder $customDql = null, array $filters = [], array  $searchFields = []){
		if(!$dql = $customDql){
			$dql = $this->createListQueryBuilder($entityClass);
		}


		$dql->setMaxResults(100);
		/*
		$dql->setMaxResults(100);
		$filters["sort"] = "userOpc.person.location.address";
		$filters["order"] = "DESC";
		*/


		if(isset($filters["limit"]))
			$dql->setMaxResults($filters["limit"]);
		if(isset($filters["offset"]))
			$dql->setFirstResult($filters["offset"]);

		//todo: crear query de sort
		if(isset($filters["sort"])){
			$dql = $this->createJoinsQueryBuilder($dql, [$filters["sort"]]);
			$sort = $this->getLastProperty($dql, $filters["sort"]);

			if(isset($filters["order"]))
				$dql->orderBy($sort, $filters["order"]);
			else
				$dql->orderBy($sort, "DESC");
		}

		if(isset($filters["search"])){
			if($filters["search"]){
				$search = $this->getLastProperty($dql, $filters["search"]);
				$dql = $this->createJoinsQueryBuilder($dql, ["folio","person.names","userOpc.person.names"]);
				$this->findByString($dql,$search,$searchFields);
			}
		}

		$paginator = new Paginator($dql, $fetchJoinCollection = true);

		return ["total"=>count($paginator), "rows"=>$dql->getQuery()->getResult()];
	}

	private function getLastProperty($dql, $field){
		if(count(explode('.', $field))>1){
			$fieldParts = explode('.', $field);
			$field = implode(array_slice($fieldParts, count($fieldParts)-2),".");
		}
		else{
			$select = $dql->getDQLPart('select')[0]->getParts()[0];
			$field = $select.".".$field;
		}
		return $field;
	}


	public function tableSerialization($entities, $fields){
		//$fields = ["id", "folio", "person.names", "lastStatusString", "campaign.name", "userOpc.username",  "userOperator.username", "assignedDate"];
		$result = [];
		foreach ($entities as $entity){
			$res = [];
			foreach ($fields as $field){
				$res[$field] = $this->renderEntityField($entity, $field);
			}
			$result[] = $res;
		}
		return $result;
	}

	private function createJoinsQueryBuilder(QueryBuilder $dql, array $fields){

		$select = $dql->getDQLPart('select')[0]->getParts()[0];
		$insertedJoinsAlias = [];
		foreach ($fields as $field){
			$fieldParts = explode('.', $field);
			$numParts = count($fieldParts);
			$parent = $select;
			for ($i=0;$i<$numParts-1;$i++) {
				if ( $i != $numParts ) {

					$joinParts = $dql->getDQLPart("join")[$select];
					$exists = false;
					foreach ($joinParts as $joinPart){
						if($joinPart->getAlias()==$fieldParts[$i])
							$exists = true;
					}

					if(!$exists){
						$insertedJoinsAlias[$fieldParts[$i]] = 1 + isset($insertedJoinsAlias[$fieldParts[$i]])?:$insertedJoinsAlias[$fieldParts[$i]];
						$postfix="";
						if($insertedJoinsAlias[$fieldParts[$i]]>1){
							$postfix = "_".($insertedJoinsAlias[$fieldParts[$i]]-1);
						}
						$dql->leftJoin( $parent . '.' . $fieldParts[$i], $fieldParts[$i].$postfix);
					}
					$parent = $fieldParts[$i];
				}
			}
		}

		return $dql;
	}



	public function createListQueryBuilder($entityClassName)
	{
		$em = $this->doctrine->getManagerForClass($entityClassName);
		$dql = $em->createQueryBuilder()
		                   ->select('entity')
		                   ->from($entityClassName, 'entity');
		return $dql;
	}

	private function findByString(QueryBuilder $dql, $string, $searchFields){
		//$fields = ["person.names", "person.mLastname", "person.pLastname"];
		//$stringsToSearch = explode(" ", $string);
		$orX = $dql->expr()->orX();
		$conditions = [];
		foreach ($searchFields as $field){
			//foreach ($stringsToSearch as $str){
			$conditions[]=$dql->expr()->like($field, "'%$string%'");
			//}
		}
		$orX->addMultiple($conditions);
		//return $dql->andWhere("person.names LIKE '%An%'");
		return $dql->andWhere( $orX );
	}


}