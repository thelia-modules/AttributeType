<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType\Controller;

use AttributeType\Form\AttributeTypeForm;
use AttributeType\Model\AttributeTypeQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Core\HttpFoundation\Response;
use AttributeType\Event\AttributeTypeEvents;
use AttributeType\Event\AttributeTypeEvent;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Model\AttributeQuery;
use Thelia\Core\Translation\Translator;
use AttributeType\AttributeType as AttributeTypeCore;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin", name="attribute_type")
 * Class AttributeTypeAttributeController
 * @package AttributeType\Controller
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class AttributeTypeAttributeController extends AttributeTypeController
{
    /**
     * @param int $attribute_type_id
     * @param int $attribute_id
     * @Route("/attribute-type/{attribute_type_id}/associate/{attribute_id}", name="_assiciation", methods="POST")
     */
    public function associateAction($attribute_type_id, $attribute_id, EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::ATTRIBUTE), null, AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(AttributeTypeForm::getName());

        try {
            $this->validateForm($form, 'POST');

            $eventDispatcher->dispatch(
                $this->getEventAssociation($attribute_type_id, $attribute_id),
                AttributeTypeEvents::ATTRIBUTE_TYPE_ASSOCIATE
            );

            return $this->generateSuccessRedirect($form);
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form
            );

            return $this->viewAttribute($attribute_id);
        }
    }

    /**
     * @param int $attribute_type_id
     * @param int $attribute_id
     * @Route("/attribute-type/{attribute_type_id}/dissociate/{attribute_id}", name="_dissociation", methods="POST")
     */
    public function dissociateAction($attribute_type_id, $attribute_id, EventDispatcherInterface $eventDispatcher)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::ATTRIBUTE), null, AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(AttributeTypeForm::getName());

        try {
            $this->validateForm($form, 'POST');

            $eventDispatcher->dispatch(
                $this->getEventAssociation($attribute_type_id, $attribute_id),
                AttributeTypeEvents::ATTRIBUTE_TYPE_DISSOCIATE
            );

            return $this->generateSuccessRedirect($form);
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form
            );

            return $this->viewAttribute($attribute_id);
        }
    }

    /**
     * @param int $attribute_type_id
     * @param int $attribute_id
     * @return AttributeTypeEvent
     * @throws \Exception
     */
    protected function getEventAssociation($attribute_type_id, $attribute_id)
    {
        if (null === $attribute = AttributeQuery::create()->findPk($attribute_id)) {
            throw new \Exception(Translator::getInstance()->trans(
                "Attribute not found",
                array(),
                AttributeTypeCore::MODULE_DOMAIN
            ));
        }

        if (null === $attributeType = AttributeTypeQuery::create()->findPk($attribute_type_id)) {
            throw new \Exception(Translator::getInstance()->trans(
                "Attribute type not found",
                array(),
                AttributeTypeCore::MODULE_DOMAIN
            ));
        }

        $event = new AttributeTypeEvent($attributeType);
        $event->setAttribute($attribute);

        return $event;
    }
}
