<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType\Controller;

use AttributeType\AttributeType as AttributeTypeCore;
use AttributeType\Event\AttributeTypeEvent;
use AttributeType\Event\AttributeTypeEvents;
use AttributeType\Form\AttributeTypeCreateForm;
use AttributeType\Form\AttributeTypeForm;
use AttributeType\Form\AttributeTypeUpdateForm;
use AttributeType\Model\AttributeType;
use AttributeType\Model\AttributeTypeI18n;
use AttributeType\Model\AttributeTypeQuery;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Annotation\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AttributeAvI18n;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * @Route("/admin", name="attribute_type")
 * Class AttributeTypeController
 * @package AttributeType\Controller
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class AttributeTypeController extends BaseAdminController
{
    protected $objectName = 'Attribute type';

    /**
     * @param array $params
     * @return Response
     * @Route("/module/AttributeType", name="_config", methods="GET")
     * @Route("/attribute-type", name="_view_all", methods="GET")
     */
    public function viewAllAction($params = array())
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::VIEW)) {
            return $response;
        }

        return $this->render("attribute-type/configuration", $params);
    }

    /**
     * @param int $id
     * @return Response
     * @throws \Exception
     * @Route("/attribute-type/{id}", name="_view", methods="GET")
     */
    public function viewAction($id, ParserContext $parserContext, RequestStack $requestStack)
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::VIEW)) {
            return $response;
        }

        if (null === $attributeType = AttributeTypeQuery::create()->findPk($id)) {
            throw new \Exception(Translator::getInstance()->trans(
                "Attribute type not found",
                array(),
                AttributeTypeCore::MODULE_DOMAIN
            ));
        }

        $title = array();
        $description = array();

        /** @var AttributeTypeI18n $i18n */
        foreach ($attributeType->getAttributeTypeI18ns() as $i18n) {
            if (null !== $lang = LangQuery::create()->findOneByLocale($i18n->getLocale())) {
                $title[$lang->getId()] = $i18n->getTitle();
                $description[$lang->getId()] = $i18n->getDescription();
            }
        }

        $form = $this->createForm(AttributeTypeUpdateForm::getName(), FormType::class, array(
            'id' => $attributeType->getId(),
            'slug' => $attributeType->getSlug(),
            'pattern' => $attributeType->getPattern(),
            'css_class' => $attributeType->getCssClass(),
            'has_attribute_av_value' => $attributeType->getHasAttributeAvValue(),
            'is_multilingual_attribute_av_value' => $attributeType->getIsMultilingualAttributeAvValue(),
            'input_type' => $attributeType->getInputType(),
            'min' => $attributeType->getMin(),
            'max' => $attributeType->getMax(),
            'step' => $attributeType->getStep(),
            'image_max_width' => $attributeType->getImageMaxWidth(),
            'image_max_height' => $attributeType->getImageMaxHeight(),
            'image_ratio' => $attributeType->getImageRatio(),
            'title' => $title,
            'description' => $description
        ));

        $parserContext->addForm($form);

        if ($requestStack->getCurrentRequest()->isXmlHttpRequest()) {
            return $this->render("attribute-type/include/form-update");
        } else {
            return $this->viewAllAction(array(
                'attribute_type_id' => $id
            ));
        }
    }

    /**
     * @Route("/attribute-type", name="_create", methods="POST")
     */
    public function createAction(EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::CREATE)) {
            return $response;
        }

        $form = $this->createForm(AttributeTypeCreateForm::getName());

        try {
            $eventDispatcher->dispatch(
                new AttributeTypeEvent($this->hydrateAttributeTypeByForm(
                    $this->validateForm($form, 'POST')
                )),
                AttributeTypeEvents::ATTRIBUTE_TYPE_CREATE
            );

            return $this->generateSuccessRedirect($form);
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form
            );

            return $this->viewAllAction();
        }
    }

    /**
     * @param int $id
     * @Route("/attribute-type/{id}", name="_update", methods="POST")
     */
    public function updateAction($id, EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(AttributeTypeUpdateForm::getName());

        try {
            $eventDispatcher->dispatch(
                new AttributeTypeEvent(
                    $this->hydrateAttributeTypeByForm(
                        $this->validateForm($form, 'POST'),
                        $id
                    )
                ),
                AttributeTypeEvents::ATTRIBUTE_TYPE_UPDATE,
            );

            return $this->generateSuccessRedirect($form);

        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form
            );

            return $this->viewAllAction(array(
                'attribute_type_id' => $id
            ));
        }
    }

    /**
     * @param int $id
     * @Route("/attribute-type/{id}/{method}", name="_delete", methods="POST")
     */
    public function deleteAction($id, EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::DELETE)) {
            return $response;
        }

        $form = $this->createForm(AttributeTypeForm::getName());

        try {
            $this->validateForm($form, 'POST');

            if (null === $attributeType = AttributeTypeQuery::create()->findPk($id)) {
                throw new \Exception(Translator::getInstance()->trans(
                    "Attribute type not found",
                    array(),
                    AttributeTypeCore::MODULE_DOMAIN
                ));
            }

            $eventDispatcher->dispatch(
                new AttributeTypeEvent($attributeType),
                AttributeTypeEvents::ATTRIBUTE_TYPE_DELETE
            );

            return $this->generateSuccessRedirect($form);

        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form
            );

            return $this->viewAllAction();
        }
    }

    /**
     * @param int $id
     * @return Response
     * @throws \Exception
     * @Route("/attribute-type/{id}/{method}", name="_copy", methods="GET")
     */
    public function copyAction($id, ParserContext $parserContext)
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::CREATE)) {
            return $response;
        }

        if (null === $attributeType = AttributeTypeQuery::create()->findPk($id)) {
            throw new \Exception(Translator::getInstance()->trans(
                "Attribute type not found",
                array(),
                AttributeTypeCore::MODULE_DOMAIN
            ));
        }

        $title = array();
        $description = array();

        /** @var AttributeTypeI18n $i18n */
        foreach ($attributeType->getAttributeTypeI18ns() as $i18n) {
            if (null !== $lang = LangQuery::create()->findOneByLocale($i18n->getLocale())) {
                $title[$lang->getId()] = $i18n->getTitle();
                $description[$lang->getId()] = $i18n->getDescription();
            }
        }

        $form = $this->createForm(AttributeTypeCreateForm::getName(), 'form', array(
            'slug' => $attributeType->getSlug() . '_' . Translator::getInstance()->trans(
                    'copy',
                    array(),
                    AttributeTypeCore::MODULE_DOMAIN
                ),
            'pattern' => $attributeType->getPattern(),
            'css_class' => $attributeType->getCssClass(),
            'has_attribute_av_value' => $attributeType->getHasAttributeAvValue(),
            'is_multilingual_attribute_av_value' => $attributeType->getIsMultilingualAttributeAvValue(),
            'input_type' => $attributeType->getInputType(),
            'min' => $attributeType->getMin(),
            'max' => $attributeType->getMax(),
            'step' => $attributeType->getStep(),
            'image_max_width' => $attributeType->getImageMaxWidth(),
            'image_max_height' => $attributeType->getImageMaxHeight(),
            'image_ratio' => $attributeType->getImageRatio(),
            'title' => $title,
            'description' => $description
        ));

        $parserContext->addForm($form);

        return $this->render("attribute-type/include/form-create");
    }

    /**
     * @param Form $form
     * @param int|null $id
     * @return AttributeType
     * @throws \Exception
     */
    protected function hydrateAttributeTypeByForm($form, $id = null)
    {
        $data = $form->getData();

        if ($id !== null) {
            if (null === $attributeType = AttributeTypeQuery::create()->findPk($id)) {
                throw new \Exception(Translator::getInstance()->trans(
                    "Attribute type not found",
                    array(),
                    AttributeTypeCore::MODULE_DOMAIN
                ));
            }
        } else {
            $attributeType = new AttributeType();
        }

        $attributeType
            ->setSlug($data['slug'])
            ->setPattern($data['pattern'])
            ->setCssClass($data['css_class'])
            ->setHasAttributeAvValue(isset($data['has_attribute_av_value']) && (int) $data['has_attribute_av_value'] ? 1 : 0)
            ->setIsMultilingualAttributeAvValue(isset($data['is_multilingual_attribute_av_value']) && (int) $data['is_multilingual_attribute_av_value'] ? 1 : 0)
            ->setInputType($data['input_type'])
            ->setMin($data['min'])
            ->setMax($data['max'])
            ->setStep($data['step'])
            ->setImageMaxWidth($data['image_max_width'])
            ->setImageMaxHeight($data['image_max_height'])
            ->setImageRatio($data['image_ratio']);

        foreach ($data['title'] as $langId => $title) {
            $attributeType
                ->setLocale(LangQuery::create()->findPk($langId)->getLocale())
                ->setTitle($title)
                ->setDescription($data['description'][$langId]);
        }

        return $attributeType;
    }

    /**
     * @param int $id
     * @return Response
     */
    protected function viewAttribute($id)
    {
        return $this->render("attribute-edit", array(
            'attribute_id' => $id
        ));
    }

    /**
     * @throws PropelException
     */
    #[Route('/module/attribute-type/duplicate/attribute/{id}', name: 'attributetype_duplicate', methods: ['POST'])]
    public function duplicateAttribute(int $id, Request $request): mixed
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::CREATE)) {
            return $response;
        }

        $currentLang = $request->getSession()?->get("thelia.current.admin_lang")->getLocale();

        try {
            $attributes = AttributeAvQuery::create()
                ->filterByAttributeId($id)
                ->find()
                ->getData();

            $langs = LangQuery::create()
                ->find()
                ->getData();

            $locales = array_filter(
                array_map(static fn($lang) => $lang->getLocale(), $langs),
                static fn($locale) => $locale !== $currentLang
            );

            foreach ($attributes as $attribute) {
                $title = AttributeAvI18nQuery::create()
                    ->filterByLocale($currentLang)
                    ->filterById($attribute->getId())
                    ->findOne()
                    ?->getTitle();

                foreach ($locales as $locale) {
                    $existing = AttributeAvI18nQuery::create()
                        ->filterByLocale($locale)
                        ->filterById($attribute->getId())
                        ->findOne();

                    if ($existing === null || $existing->getTitle() === null || $existing->getTitle() === '') {
                        $attributeAvI18n = $existing ?? new AttributeAvI18n();
                        $attributeAvI18n
                            ->setId($attribute->getId())
                            ->setTitle($title)
                            ->setLocale($locale)
                            ->save();
                    }
                }
            }
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()?->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage()
            );
        }

        return $this->generateRedirect(URL::getInstance()?->absoluteUrl("/admin/configuration/attributes/update?attribute_id=" . $id));
    }
}