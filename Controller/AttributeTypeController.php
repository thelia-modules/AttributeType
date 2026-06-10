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
use AttributeType\Model\AttributeAttributeTypeQuery;
use AttributeType\Model\AttributeType;
use AttributeType\Model\AttributeTypeI18n;
use AttributeType\Model\AttributeTypeQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Exception\PropelException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\Form;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Thelia\Controller\Admin\BaseAdminController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Translation\Translator;
use Thelia\Model\AttributeAvI18n;
use Thelia\Model\AttributeAvI18nQuery;
use Thelia\Model\AttributeAvQuery;
use Thelia\Model\AttributeQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;
use Twig\Environment;

/**
 * Class AttributeTypeController
 * @package AttributeType\Controller
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
#[Route('/admin', name: 'attribute_type')]
class AttributeTypeController extends BaseAdminController
{
    protected $objectName = 'Attribute type';

    #[Route('/attribute-type', name: '_view_all', methods: ['GET'])]
    public function viewAllAction(Environment $twig, RequestStack $requestStack): Response
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::VIEW)) {
            return $response;
        }

        return $this->renderConfiguration($twig, $requestStack);
    }

    /**
     * @param int $id
     */
    #[Route('/attribute-type/{id}', name: '_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function viewAction($id, RequestStack $requestStack, Environment $twig): Response
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::VIEW)) {
            return $response;
        }

        if (null === $attributeType = AttributeTypeQuery::create()->findPk($id)) {
            throw new NotFoundHttpException(Translator::getInstance()->trans(
                "Attribute type not found",
                array(),
                AttributeTypeCore::MODULE_DOMAIN
            ));
        }

        $values = $this->buildFormValues($attributeType);

        if ($requestStack->getCurrentRequest()->isXmlHttpRequest()) {
            return new Response($twig->render(
                '@AttributeTypeModule/backOffice/default-twig/attribute-type/include/form-update.html.twig',
                array_merge($this->getFormContext($requestStack), [
                    'form' => $this->createForm(AttributeTypeUpdateForm::getName())->getForm()->createView(),
                    'values' => $values,
                    'form_error_message' => null,
                ])
            ));
        }

        return $this->renderConfiguration($twig, $requestStack, [
            'update_values' => $values,
        ]);
    }

    #[Route('/attribute-type', name: '_create', methods: ['POST'])]
    public function createAction(EventDispatcherInterface $eventDispatcher, Environment $twig, RequestStack $requestStack): Response
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
            return $this->renderConfiguration($twig, $requestStack, [
                'form_error_message' => $e->getMessage(),
                'force_show_create' => true,
            ]);
        }
    }

    /**
     * @param int $id
     */
    #[Route('/attribute-type/{id}', name: '_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateAction($id, EventDispatcherInterface $eventDispatcher, Environment $twig, RequestStack $requestStack): Response
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
            $updateValues = null;
            if (null !== $attributeType = AttributeTypeQuery::create()->findPk($id)) {
                $updateValues = $this->buildFormValues($attributeType);
            }

            return $this->renderConfiguration($twig, $requestStack, [
                'form_error_message' => $e->getMessage(),
                'update_values' => $updateValues,
                'general_error' => $updateValues === null ? $e->getMessage() : null,
            ]);
        }
    }

    /**
     * @param int $id
     */
    #[Route('/attribute-type/{id}/{method}', name: '_delete', methods: ['POST'], requirements: ['id' => '\d+', 'method' => '_delete'])]
    public function deleteAction($id, EventDispatcherInterface $eventDispatcher, Environment $twig, RequestStack $requestStack): Response
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
            return $this->renderConfiguration($twig, $requestStack, [
                'general_error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param int $id
     */
    #[Route('/attribute-type/{id}/{method}', name: '_copy', methods: ['GET'], requirements: ['id' => '\d+', 'method' => '_copy'])]
    public function copyAction($id, Environment $twig, RequestStack $requestStack): Response
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::CREATE)) {
            return $response;
        }

        if (null === $attributeType = AttributeTypeQuery::create()->findPk($id)) {
            throw new NotFoundHttpException(Translator::getInstance()->trans(
                "Attribute type not found",
                array(),
                AttributeTypeCore::MODULE_DOMAIN
            ));
        }

        $values = $this->buildFormValues($attributeType);
        $values['id'] = null;
        $values['slug'] .= '_' . Translator::getInstance()->trans(
            'copy',
            array(),
            AttributeTypeCore::MODULE_DOMAIN
        );

        return new Response($twig->render(
            '@AttributeTypeModule/backOffice/default-twig/attribute-type/include/form-create.html.twig',
            array_merge($this->getFormContext($requestStack), [
                'form' => $this->createForm(AttributeTypeCreateForm::getName())->getForm()->createView(),
                'values' => $values,
                'force_show' => false,
                'form_error_message' => null,
            ])
        ));
    }

    /**
     * Renders the module configuration page (attribute types list + modals).
     */
    protected function renderConfiguration(Environment $twig, RequestStack $requestStack, array $args = []): Response
    {
        $locale = $this->getEditionLocale($requestStack);

        $attributeTypes = [];
        /** @var AttributeType $attributeType */
        foreach (AttributeTypeQuery::create()->orderById()->find() as $attributeType) {
            $attributeType->setLocale($locale);

            $usedBy = [];
            $attributeIds = AttributeAttributeTypeQuery::create()
                ->filterByAttributeTypeId($attributeType->getId())
                ->select('AttributeId')
                ->find()
                ->getData();

            if ($attributeIds !== []) {
                foreach (AttributeQuery::create()->filterById($attributeIds, Criteria::IN)->orderById()->find() as $attribute) {
                    $attribute->setLocale($locale);
                    $usedBy[] = [
                        'id' => $attribute->getId(),
                        'title' => $attribute->getTitle(),
                    ];
                }
            }

            $attributeTypes[] = [
                'id' => $attributeType->getId(),
                'slug' => $attributeType->getSlug(),
                'title' => $attributeType->getTitle(),
                'description' => $attributeType->getDescription(),
                'used_by' => $usedBy,
            ];
        }

        return new Response($twig->render(
            '@AttributeTypeModule/backOffice/default-twig/attribute-type/configuration.html.twig',
            array_merge($this->getFormContext($requestStack), [
                'attribute_types' => $attributeTypes,
                'create_form' => $this->createForm(AttributeTypeCreateForm::getName())->getForm()->createView(),
                'update_form' => $this->createForm(AttributeTypeUpdateForm::getName())->getForm()->createView(),
                'delete_form' => $this->createForm(AttributeTypeForm::getName())->getForm()->createView(),
                'create_values' => $this->buildFormValues(null),
                'update_values' => null,
                'form_error_message' => null,
                'force_show_create' => false,
                'general_error' => null,
            ], $args)
        ));
    }

    /**
     * Common variables needed by the create/update form fragments.
     */
    protected function getFormContext(RequestStack $requestStack): array
    {
        $langs = [];
        /** @var Lang $lang */
        foreach (LangQuery::create()->filterByActive(1)->orderByPosition()->find() as $lang) {
            $langs[] = [
                'id' => $lang->getId(),
                'code' => $lang->getCode(),
                'locale' => $lang->getLocale(),
                'title' => $lang->getTitle(),
            ];
        }

        return [
            'langs' => $langs,
            'edit_language_id' => $this->getEditionLang($requestStack)?->getId(),
            'input_type_choices' => $this->getInputTypeChoices(),
        ];
    }

    /**
     * Form values array shared by the create/update/copy fragments,
     * either empty defaults or hydrated from an existing attribute type.
     */
    protected function buildFormValues(?AttributeType $attributeType): array
    {
        if (null === $attributeType) {
            return [
                'id' => null,
                'slug' => '',
                'pattern' => '',
                'css_class' => '',
                'has_attribute_av_value' => 0,
                'is_multilingual_attribute_av_value' => 0,
                'input_type' => '',
                'min' => '',
                'max' => '',
                'step' => '',
                'image_max_width' => '',
                'image_max_height' => '',
                'image_ratio' => '',
                'title' => [],
                'description' => [],
            ];
        }

        $title = [];
        $description = [];

        /** @var AttributeTypeI18n $i18n */
        foreach ($attributeType->getAttributeTypeI18ns() as $i18n) {
            if (null !== $lang = LangQuery::create()->findOneByLocale($i18n->getLocale())) {
                $title[$lang->getId()] = $i18n->getTitle();
                $description[$lang->getId()] = $i18n->getDescription();
            }
        }

        return [
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
            'description' => $description,
        ];
    }

    /**
     * Same choice list as AttributeTypeCreateForm::buildForm().
     */
    protected function getInputTypeChoices(): array
    {
        $choices = [];
        foreach (['text', 'boolean', 'textarea', 'color', 'number', 'range', 'url', 'image'] as $type) {
            $choices[] = [
                'value' => $type,
                'label' => Translator::getInstance()->trans('Type ' . $type, array(), AttributeTypeCore::MODULE_DOMAIN),
            ];
        }

        return $choices;
    }

    protected function getEditionLang(RequestStack $requestStack): ?Lang
    {
        $request = $requestStack->getCurrentRequest();
        if (null === $request || !$request->hasSession()) {
            return null;
        }

        return $request->getSession()->get('thelia.admin.edition.lang');
    }

    protected function getEditionLocale(RequestStack $requestStack): string
    {
        return $this->getEditionLang($requestStack)?->getLocale()
            ?? LangQuery::create()->findOneByByDefault(true)?->getLocale()
            ?? 'en_US';
    }

    /**
     * @param Form $form
     * @param int|null $id
     * @return AttributeType
     * @throws \Exception
     */
    protected function hydrateAttributeTypeByForm($form, $id = null): AttributeType
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
     * Error fallback used by the associate/dissociate/meta actions: sends the
     * admin back to the core attribute edit page (rendered by the active
     * back-office theme, Twig or Smarty).
     *
     * @param int $id
     */
    protected function viewAttribute($id): Response
    {
        return $this->generateRedirect(
            URL::getInstance()->absoluteUrl('/admin/configuration/attributes/update', ['attribute_id' => $id])
        );
    }

    /**
     * @throws PropelException
     */
    #[Route('/module/attribute-type/duplicate/attribute/{id}', name: '_duplicate_attribute', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function duplicateAttribute(int $id, Request $request): mixed
    {
        if (null !== $response = $this->checkAuth(array(), 'AttributeType', AccessManager::CREATE)) {
            return $response;
        }

        $currentLang = $request->getSession()?->get("thelia.admin.edition.lang")->getLocale();

        try {
            $attributes = AttributeAvQuery::create()
                ->filterByAttributeId($id)
                ->find()
                ->getData();

            $langs = LangQuery::create()
                ->filterByActive(1)
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

                    $attributeAvI18n = $existing ?? new AttributeAvI18n();
                    $attributeAvI18n
                        ->setId($attribute->getId())
                        ->setTitle($title)
                        ->setLocale($locale)
                        ->save();

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
