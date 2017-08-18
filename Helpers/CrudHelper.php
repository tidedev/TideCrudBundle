<?php
namespace Tide\TideCrudBundle\Helpers;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\PersistentCollection;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Guzzle\Common\Exception\ExceptionCollection;
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
        if($field=="actions")
            return $this->templating->render(self::BASE_TWIGS."/fields/actions.html.twig", ["entityId"=>$item->getId(),"entityName"=>strtolower((new \ReflectionClass($item))->getShortName())]);

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

        if($fieldMetadata["value"] instanceof PersistentCollection){
            $fieldType = "arrayCollection";
            try{
                $fieldMetadata["value"] = call_user_func([$item, "get".strtoupper($field)."AsString"], []);
            }catch (\Exception $e){
                throw new ExceptionCollection("You have to implement method toString in collection");
            }
        }

        return $this->templating->render(self::BASE_TWIGS."/fields/".$fieldType.".html.twig", $fieldMetadata);
    }

    public function paginate($entityClass, QueryBuilder $customDql = null, array $filters = [], array  $searchFields = []){
        if(!$dql = $customDql){
            $dql = $this->createListQueryBuilder($entityClass);
        }

        $dql->setMaxResults(20);

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
                $dql = $this->createJoinsQueryBuilder($dql, $searchFields);
                $this->findByString($dql,$filters["search"], $searchFields);
            }
        }

        $paginator = new Paginator($dql, true);

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


    public function tableSerialization($entities, $fields, $showActions = true){
        //$fields = ["id", "folio", "person.names", "lastStatusString", "campaign.name", "userOpc.username",  "userOperator.username", "assignedDate"];
        $result = [];
        foreach ($entities as $entity){
            $res = [];
            foreach ($fields as $field){
                $res[$field] = $this->renderEntityField($entity, $field);
            }
            if($showActions)
                $res["actions"] = $this->renderEntityField($entity, "actions");
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
                    $exists = false;
                    if(count($dql->getDQLPart("join"))>0) {
                        $joinParts = $dql->getDQLPart("join")[$select];
                        foreach ($joinParts as $joinPart) {
                            if ($joinPart->getAlias() == $fieldParts[$i])
                                $exists = true;
                        }
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
        $orX = $dql->expr()->orX();
        $conditions = [];
        $composedLike = "CONCAT (";
        foreach ($searchFields as $field){
            if(strpos($field, ".")==false)
                $fieldFormatted = "entity.".$field;
            else{
                $fieldParts = explode('.', $field);
                $points = count($fieldParts);
                if($points<3)
                    $fieldFormatted = $field;
                else
                    $fieldFormatted = $fieldParts[$points-2].".".$fieldParts[$points-1];
            }
            $conditions[]=$dql->expr()->like($fieldFormatted, "'%$string%'");
            $composedLike .= "IFNULL($fieldFormatted,'')";
            if($field != end($searchFields))
                $composedLike .=",' ',";
        }
        $composedLike.=",'')";
        $conditions[]=$dql->expr()->like($composedLike, "'%$string%'");

        $orX->addMultiple($conditions);
        return $dql->andWhere( $orX );
    }

    public function getEntityFieldsMetadata($entity, $fields){
        $fieldsMeta = [];
        $em = $this->doctrine->getManager();
        foreach ($fields as $field){
            $fieldMeta = [];
            $fieldMeta["name"] = $field;
            $fieldMeta["type"] = true;

            $fieldParts = explode(".", $field);
            $type = null;
            $currentClass =  get_class($entity);
            foreach ($fieldParts as $part){
                $meta = $em->getClassMetadata($currentClass);
                if($type = $meta->getTypeOfField($part)){
                    $fieldMeta["type"]=$type;
                    break;
                }
                else{
                    if(isset($meta->associationMappings[$part])){
                        $currentClass = $meta->getAssociationMappings()[$part]["targetEntity"];
                        $fieldMeta["type"] = "association";
                    }else{
                        $fieldMeta["type"]=null;
                    }

                }
            }
            $fieldsMeta[]=$fieldMeta;
        }
        return $fieldsMeta;

    }

}

