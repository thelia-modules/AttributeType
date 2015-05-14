<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType\Model;

use AttributeType\Model\Base\AttributeType as BaseAttributeType;
use AttributeType\Model\Map\AttributeAttributeTypeTableMap;
use AttributeType\Model\Map\AttributeTypeAvMetaTableMap;
use AttributeType\Model\Map\AttributeTypeTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Model\Map\AttributeAvTableMap;

/**
 * Class AttributeType
 * @package AttributeType\Model
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class AttributeType extends BaseAttributeType
{
    /**
     * Returns a value based on the slug, attribute_av_id and locale
     *
     * <code>
     * $value  = AttributeType::getValue('color', 2);
     * </code>
     *
     * @param string $slug
     * @param int $attributeAvId
     * @param string $locale
     * @return string
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getValue($slug, $attributeAvId, $locale = 'en_US')
    {
        return self::getValues([$slug], [$attributeAvId], $locale)[$slug][$attributeAvId];
    }

    /**
     * Returns a set of values
     * If the value does not exist, it is replaced by null
     *
     * <code>
     * $values = AttributeType::getValue(['color','texture'], [4,7]);
     * </code>
     *
     * <sample>
     *  array(
     *  'color' => [4 => '#00000', 7 => '#FFF000'],
     *  'texture' => [4 => null, 7 => 'lines.jpg']
     * )
     * </sample>
     *
     * @param array $slugs[]
     * @param array $attributeAvIds[]
     * @param string $locale
     * @return string
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public static function getValues(array $slugs, array $attributeAvIds, $locale = 'en_US')
    {
        $return = array();

        foreach ($slugs as $slug) {
            $return[$slug] = array();
            foreach ($attributeAvIds as $attributeAvId) {
                $return[$slug][$attributeAvId] = null;
            }
        }

        $query = AttributeTypeAvMetaQuery::create()
            ->filterByLocale($locale)
            ->filterByAttributeAvId($attributeAvIds, Criteria::IN);

        self::addJoinAttributeAttributeType($query);
        self::addJoinAttributeType($query);
        self::addJoinAttributeAv($query);

        $in = implode(
            ',',
            array_map(
                function($v) {
                    return "'" . $v . "'";
                },
                $slugs
            )
        );

        $query
            ->addJoinCondition('attribute_type', "`attribute_type`.`SLUG` IN (" . $in . ")")
            ->addJoinCondition('attribute_av', "`attribute_av`.`ID` = `attribute_type_av_meta`.`ATTRIBUTE_AV_ID`")
            ->withColumn('`attribute_type`.`SLUG`', 'SLUG')
            ->withColumn('`attribute_av`.`ID`', 'ATTRIBUTE_AV_ID');

        $results = $query->find();

        foreach ($results as $result) {
            $return[$result->getVirtualColumn('SLUG')][$result->getVirtualColumn('ATTRIBUTE_AV_ID')]
                = $result->getValue();
        }

        return $return;
    }

    /**
     * @param Criteria $query
     */
    protected static function addJoinAttributeAttributeType(Criteria & $query)
    {
        $join = new Join();

        $join->addExplicitCondition(
            AttributeTypeAvMetaTableMap::TABLE_NAME,
            'ATTRIBUTE_ATTRIBUTE_TYPE_ID',
            null,
            AttributeAttributeTypeTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);

        $query->addJoinObject($join, 'attribute_type_av_meta');
    }

    /**
     * @param Criteria $query
     */
    protected static function addJoinAttributeType(Criteria & $query)
    {
        $join = new Join();

        $join->addExplicitCondition(
            AttributeAttributeTypeTableMap::TABLE_NAME,
            'ATTRIBUTE_TYPE_ID',
            null,
            AttributeTypeTableMap::TABLE_NAME,
            'ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);

        $query->addJoinObject($join, 'attribute_type');
    }

    /**
     * @param Criteria $query
     * @return $this
     */
    protected static function addJoinAttributeAv(Criteria & $query)
    {
        $join = new Join();

        $join->addExplicitCondition(
            AttributeAttributeTypeTableMap::TABLE_NAME,
            'ATTRIBUTE_ID',
            null,
            AttributeAvTableMap::TABLE_NAME,
            'ATTRIBUTE_ID',
            null
        );

        $join->setJoinType(Criteria::INNER_JOIN);

        $query->addJoinObject($join, 'attribute_av');
    }
}
