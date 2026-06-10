<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType\Hook;

use AttributeType\Form\AttributeTypeAvMetaUpdateForm;
use AttributeType\Form\AttributeTypeForm;
use AttributeType\Model\AttributeAttributeType;
use AttributeType\Model\AttributeAttributeTypeQuery;
use AttributeType\Model\AttributeTypeAvMeta;
use AttributeType\Model\AttributeTypeAvMetaQuery;
use AttributeType\Model\AttributeTypeQuery;
use AttributeType\Model\Map\AttributeAttributeTypeTableMap;
use AttributeType\Model\Map\AttributeTypeAvMetaTableMap;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\ActiveQuery\Join;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Form\TheliaFormFactory;
use Thelia\Core\Hook\BaseHook;
use Thelia\Core\Template\Parser\ParserResolver;
use Thelia\Model\AttributeAv;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
 * Class AttributeEditHook
 * @package AttributeType\Hook
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class AttributeEditHook extends BaseHook
{
    public function __construct(
        private readonly TheliaFormFactory $formFactory,
        ?EventDispatcherInterface $dispatcher = null,
        ?ParserResolver $parserResolver = null,
    ) {
        parent::__construct($dispatcher, $parserResolver);
    }

    public static function getSubscribedHooks(): array
    {
        return [
            'attribute-edit.bottom' => [
                ['type' => 'back', 'method' => 'onAttributeEditBottom'],
            ],
            'attribute.edit-js' => [
                ['type' => 'back', 'method' => 'onAttributeEditJs'],
            ],
        ];
    }

    public function onAttributeEditBottom(HookRenderEvent $event): void
    {
        $attributeId = (int) $event->getArgument('attribute_id');

        $data = $this->hydrateForm($attributeId);

        $form = $this->formFactory->createForm(
            AttributeTypeAvMetaUpdateForm::getName(),
            FormType::class,
            $data
        );

        $event->add($this->render(
            'attribute-type/hook/attribute-edit-bottom.html.twig',
            [
                'attribute_id' => $attributeId,
                'form' => $form->createView()->getView(),
                'associate_form' => $this->formFactory->createForm(AttributeTypeForm::getName())->createView()->getView(),
                'form_meta_data' => $data['attribute_av'],
                'associated_types' => $this->getAssociatedTypes($attributeId),
                'available_types' => $this->getAvailableTypes($attributeId),
                'meta_table' => $this->buildMetaTable($attributeId, $data['attribute_av']),
                'duplicate_url' => '/admin/module/attribute-type/duplicate/attribute/' . $attributeId,
            ]
        ));
    }

    public function onAttributeEditJs(HookRenderEvent $event): void
    {
        $event->add($this->render(
            'attribute-type/hook/attribute-edit-js.html.twig',
            [
                'attribute_id' => (int) $event->getArgument('attribute_id'),
            ]
        ));
    }

    /**
     * Attribute types associated with the given attribute (replaces
     * {loop type="attribute_type_loop" attribute_id=...}).
     *
     * @return list<array{id:int,slug:string,title:?string}>
     */
    private function getAssociatedTypes(int $attributeId): array
    {
        $locale = $this->getLocale();

        $types = AttributeTypeQuery::create()
            ->useAttributeAttributeTypeQuery()
                ->filterByAttributeId($attributeId)
            ->endUse()
            ->orderById()
            ->find();

        $result = [];
        foreach ($types as $type) {
            $type->setLocale($locale);
            $result[] = [
                'id' => $type->getId(),
                'slug' => $type->getSlug(),
                'title' => $type->getTitle(),
            ];
        }

        return $result;
    }

    /**
     * Attribute types NOT yet associated with the attribute (replaces the
     * "select" loop with exclude_id).
     *
     * @return list<array{id:int,slug:string,title:?string}>
     */
    private function getAvailableTypes(int $attributeId): array
    {
        $locale = $this->getLocale();

        $excludedIds = AttributeAttributeTypeQuery::create()
            ->filterByAttributeId($attributeId)
            ->select('AttributeTypeId')
            ->find()
            ->getData();

        $query = AttributeTypeQuery::create()->orderById();
        if (!empty($excludedIds)) {
            $query->filterById($excludedIds, Criteria::NOT_IN);
        }

        $result = [];
        foreach ($query->find() as $type) {
            $type->setLocale($locale);
            $result[] = [
                'id' => $type->getId(),
                'slug' => $type->getSlug(),
                'title' => $type->getTitle(),
            ];
        }

        return $result;
    }

    /**
     * Builds the per-language meta-edition table data, replacing the nested
     * {loop type="lang"} / {loop type="attribute_type"} / {loop type="attribute_availability"}
     * in form-meta.html.
     *
     * @param array<int, array{lang: array<int, array{attribute_type: array<int, mixed>}>}> $formMetaData
     * @return array<int, array{
     *     lang_id:int, locale:string, code:string, title:?string,
     *     types: list<array<string,mixed>>,
     *     rows: list<array{attribute_av_id:int, title:?string, values: array<int, mixed>}>
     * }>
     */
    private function buildMetaTable(int $attributeId, array $formMetaData): array
    {
        $langs = LangQuery::create()->orderByPosition()->find();

        // Attribute types associated with this attribute (full data).
        $associatedTypes = AttributeTypeQuery::create()
            ->useAttributeAttributeTypeQuery()
                ->filterByAttributeId($attributeId)
            ->endUse()
            ->orderById()
            ->find();

        $attributeAvs = AttributeAvQuery::create()
            ->filterByAttributeId($attributeId)
            ->orderByPosition()
            ->find();

        $table = [];

        /** @var Lang $lang */
        foreach ($langs as $lang) {
            $locale = $lang->getLocale();
            $langId = $lang->getId();

            $types = [];
            foreach ($associatedTypes as $type) {
                $type->setLocale($locale);
                $types[] = [
                    'id' => $type->getId(),
                    'slug' => $type->getSlug(),
                    'title' => $type->getTitle(),
                    'description' => $type->getDescription(),
                    'css_class' => $type->getCssClass(),
                    'pattern' => $type->getPattern(),
                    'input_type' => $type->getInputType(),
                    'min' => $type->getMin(),
                    'max' => $type->getMax(),
                    'step' => $type->getStep(),
                    'has_attribute_av_value' => (bool) $type->getHasAttributeAvValue(),
                    'is_multilingual_attribute_av_value' => (bool) $type->getIsMultilingualAttributeAvValue(),
                ];
            }

            $rows = [];
            /** @var AttributeAv $attributeAv */
            foreach ($attributeAvs as $attributeAv) {
                $avId = $attributeAv->getId();
                if (!isset($formMetaData[$avId])) {
                    continue;
                }

                $title = AttributeAvI18nQuery::create()
                    ->filterByLocale($locale)
                    ->filterById($avId)
                    ->findOne()
                    ?->getTitle();

                $rows[] = [
                    'attribute_av_id' => $avId,
                    'title' => $title,
                    'values' => $formMetaData[$avId]['lang'][$langId]['attribute_type'] ?? [],
                ];
            }

            $table[] = [
                'lang_id' => $langId,
                'locale' => $locale,
                'code' => $lang->getCode(),
                'title' => $lang->getTitle(),
                'types' => $types,
                'rows' => $rows,
            ];
        }

        return $table;
    }

    private function getLocale(): string
    {
        $request = $this->getRequest();
        if (null !== $request) {
            $lang = $request->getSession()?->get('thelia.admin.edition.lang');
            if (null !== $lang) {
                return $lang->getLocale();
            }

            return $request->getLocale();
        }

        return LangQuery::create()->findOneByByDefault(true)?->getLocale() ?? 'en_US';
    }

    /**
     * @return array|mixed|\Propel\Runtime\Collection\ObjectCollection
     */
    protected function getAttributeTypeAvMetas(AttributeAv $attributeAv): mixed
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

        return AttributeTypeAvMetaQuery::create()
            ->filterByAttributeAvId($attributeAv->getId())
            ->addJoinObject($join)
            ->withColumn('`attribute_attribute_type`.`attribute_type_id`', 'ATTRIBUTE_TYPE_ID')
            ->find();
    }

    /**
     * @return array
     */
    protected function hydrateForm(int $attributeId): array
    {
        $data = ['attribute_av' => []];

        $attributeAvs = AttributeAvQuery::create()->findByAttributeId($attributeId);

        $attributeTypes = AttributeAttributeTypeQuery::create()->findByAttributeId($attributeId);

        $langs = LangQuery::create()->find();

        /** @var AttributeAv $attributeAv */
        foreach ($attributeAvs as $attributeAv) {
            $attributeAvMetas = $this->getAttributeTypeAvMetas($attributeAv);

            $data['attribute_av'][$attributeAv->getId()] = [
                'lang' => [],
            ];

            /** @var Lang $lang */
            foreach ($langs as $lang) {
                $data['attribute_av'][$attributeAv->getId()]['lang'][$lang->getId()] = [
                    'attribute_type' => [],
                ];

                /** @var AttributeTypeAvMeta $attributeAvMeta */
                foreach ($attributeAvMetas as $attributeAvMeta) {
                    /** @var AttributeAttributeType $attributeType */
                    foreach ($attributeTypes as $attributeType) {
                        if ($attributeAvMeta->getLocale() === $lang->getLocale()
                            && (int) ($attributeAvMeta->getVirtualColumn("ATTRIBUTE_TYPE_ID")) === $attributeType->getAttributeTypeId()
                        ) {
                            $data['attribute_av'][$attributeAv->getId()]['lang'][$lang->getId()]['attribute_type'][$attributeType->getAttributeTypeId()] = $attributeAvMeta->getValue();
                        }
                    }
                }
            }
        }

        return $data;
    }
}
