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

namespace AttributeType\Loop;

use AttributeType\Model\AttributeTypeAvMeta;
use AttributeType\Model\AttributeTypeAvMetaQuery;
use AttributeType\Model\Map\AttributeAttributeTypeTableMap;
use AttributeType\Model\Map\AttributeTypeAvMetaTableMap;
use AttributeType\Model\Map\AttributeTypeTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\AttributeAvailability;
use Thelia\Model\AttributeAv;

/**
 * Class AttributeAvailabilityExtendLoop
 * @package AttributeType\Loop
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class AttributeAvailabilityExtendLoop extends AttributeAvailability implements PropelSearchLoopInterface
{
    /**
     * @param LoopResult $loopResult
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    private function getAttributesMeta(LoopResult $loopResult)
    {
        $attributeAvIds = array();
        $locale = null;

        /** @var AttributeAV $attributeAv */
        foreach ($loopResult->getResultDataCollection() as $attributeAv) {
            $attributeAvIds[] = $attributeAv->getId();
            if ($locale === null) {
                $locale = $attributeAv->getLocale();
            }
        }

        $joinAttributeAttributeType = new Join();

        $joinAttributeAttributeType->addExplicitCondition(
            AttributeTypeAvMetaTableMap::TABLE_NAME,
            'ATTRIBUTE_ATTRIBUTE_TYPE_ID',
            null,
            AttributeAttributeTypeTableMap::TABLE_NAME,
            'ID',
            null
        );

        $joinAttributeAttributeType->setJoinType(Criteria::INNER_JOIN);

        $joinAttributeType = new Join();

        $joinAttributeType->addExplicitCondition(
            AttributeAttributeTypeTableMap::TABLE_NAME,
            'ATTRIBUTE_TYPE_ID',
            null,
            AttributeTypeTableMap::TABLE_NAME,
            'ID',
            null
        );

        $joinAttributeType->setJoinType(Criteria::INNER_JOIN);

        $query = AttributeTypeAvMetaQuery::create()
            ->filterByLocale($locale)
            ->filterByAttributeAvId($attributeAvIds, Criteria::IN)
            ->addJoinObject($joinAttributeAttributeType)
            ->addJoinObject($joinAttributeType);

        $query->withColumn('`attribute_type`.`SLUG`', 'SLUG');

        return $query->find();
    }

    /**
     * @param string $slug
     * @return string
     */
    private function formatSlug($slug)
    {
        return strtoupper(str_replace('-', '_', $slug));
    }

    /**
     * @param LoopResult $loopResult
     * @return LoopResult
     * @throws \Propel\Runtime\Exception\PropelException
     */
    public function parseResults(LoopResult $loopResult)
    {
        $attributesMeta = self::getAttributesMeta($loopResult);

        $slugs = array();

        /** @var AttributeTypeAvMeta $attributeMeta */
        foreach ($attributesMeta as $attributeMeta) {
            $slugs[$attributeMeta->getVirtualColumn('SLUG')] = true;
        }

        /** @var AttributeAV $attributeAv */
        foreach ($loopResult->getResultDataCollection() as $attributeAv) {
            $loopResultRow = new LoopResultRow($attributeAv);
            $loopResultRow
                ->set("ID", $attributeAv->getId())
                ->set("ATTRIBUTE_ID", $attributeAv->getAttributeId())
                ->set("IS_TRANSLATED", $attributeAv->getVirtualColumn('IS_TRANSLATED'))
                ->set("LOCALE", $this->locale)
                ->set("TITLE", $attributeAv->getVirtualColumn('i18n_TITLE'))
                ->set("CHAPO", $attributeAv->getVirtualColumn('i18n_CHAPO'))
                ->set("DESCRIPTION", $attributeAv->getVirtualColumn('i18n_DESCRIPTION'))
                ->set("POSTSCRIPTUM", $attributeAv->getVirtualColumn('i18n_POSTSCRIPTUM'))
                ->set("POSITION", $attributeAv->getPosition())
            ;

            // init slug variable
            foreach ($slugs as $slug => $bool) {
                $loopResultRow->set(
                    self::formatSlug(
                        $slug
                    ),
                    null
                );
            }

            /** @var AttributeTypeAvMeta $attributeMeta */
            foreach ($attributesMeta as $attributeMeta) {
                if ($attributeMeta->getAttributeAvId() === $attributeAv->getId()) {
                    $loopResultRow->set(
                        self::formatSlug(
                            $attributeMeta->getVirtualColumn('SLUG')
                        ),
                        $attributeMeta->getValue()
                    );
                }
            }

            $this->addOutputFields($loopResultRow, $attributeAv);

            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }
}
