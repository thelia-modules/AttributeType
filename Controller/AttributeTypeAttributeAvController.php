<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType\Controller;

use AttributeType\AttributeType;
use AttributeType\Event\AttributeTypeEvents;
use AttributeType\Event\AttributeTypeAvMetaEvent;
use AttributeType\Form\AttributeTypeAvMetaUpdateForm;
use AttributeType\Form\AttributeTypeForm;
use AttributeType\Form\AttributeTypeUpdateForm;
use AttributeType\Model\AttributeAttributeType;
use AttributeType\Model\AttributeAttributeTypeQuery;
use AttributeType\Model\AttributeTypeAvMeta;
use AttributeType\Model\AttributeTypeAvMetaQuery;
use AttributeType\Model\AttributeTypeQuery;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Core\Template\ParserContext;
use Thelia\Core\Translation\Translator;
use Thelia\Files\Exception\ProcessFileException;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/admin", name="attribute_type")
 * Class AttributeTypeAttributeAvController
 * @package AttributeType\Controller
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class AttributeTypeAttributeAvController extends AttributeTypeController
{
    /** @var Lang[] */
    protected $langs = array();

    /** @var AttributeAttributeType[] */
    protected $attributeAttributeTypes = array();

    /**
     * @param int $attribute_id
     * @return null|\Symfony\Component\HttpFoundation\Response|\Thelia\Core\HttpFoundation\Response
     * @Route("/attribute/{attribute_id}/attribute-av/meta", name="_update_meta", methods="POST")
     */
    public function updateMetaAction($attribute_id, EventDispatcherInterface $eventDispatcher, ParserContext $parserContext, RequestStack $requestStack)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::ATTRIBUTE), null, AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm(AttributeTypeAvMetaUpdateForm::getName());

        try {
            $formUpdate = $this->validateForm($form);

            $attributeAvs = $formUpdate->get('attribute_av')->getData();

            foreach ($attributeAvs as $attributeAvId => $attributeAv) {
                foreach ($attributeAv['lang'] as $attrLangId => $lang) {
                    foreach ($lang['attribute_type'] as $attributeTypeId => $value) {
                        $values = [];
                        $values[$attrLangId] = $value;
                        $attributeType = AttributeTypeQuery::create()
                            ->findOneById($attributeTypeId);

                        if ($attributeType->getInputType() === "image") {
                            if (null === $value) {
                                continue;
                            }

                            $uploadedFileName = $this->uploadFile($value);
                            $values[$attrLangId] = $uploadedFileName;

                            if (!$attributeType->getIsMultilingualAttributeAvValue()) {
                                $activeLangs = LangQuery::create()
                                    ->filterByActive(1)
                                    ->find();
                                /** @var Lang $lang */
                                foreach ($activeLangs as $activeLang) {
                                    $values[$activeLang->getId()] = $uploadedFileName;
                                }
                            }
                        }

                         foreach ($values as $langId => $langValue) {
                             $this->dispatchEvent(
                                 $this->getAttributeAttributeType($attributeTypeId, $attribute_id),
                                 $attributeAvId,
                                 $langId,
                                 $langValue,
                                 $eventDispatcher
                             );
                         }
                    }
                }
            }

            $this->resetUpdateForm($parserContext, $requestStack->getCurrentRequest());
            return $this->generateSuccessRedirect($form);
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form,
                $e
            );

            return $this->viewAttribute($attribute_id);
        }
    }

    /**
     * @Route("/attribute-type-av-meta/{attribute_id}/{attribute_type_id}/{attribute_av_id}/{lang_id}/{method}", name="_delete_meta", methods="POST")
     */
    public function deleteMetaAction($attribute_id, $attribute_type_id, $attribute_av_id, $lang_id)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::ATTRIBUTE), null, AccessManager::DELETE)) {
            return $response;
        }
        $form = $this->createForm(AttributeTypeForm::getName());
        try {
            $this->validateForm($form);
            $attributeType = AttributeTypeQuery::create()
                ->findOneById($attribute_type_id);
            $attributeAttributeType =  $this->getAttributeAttributeType($attribute_type_id, $attribute_id);
            $eventName = AttributeTypeEvents::ATTRIBUTE_TYPE_AV_META_DELETE;
            $attributeAvMetaQuery = AttributeTypeAvMetaQuery::create()
                ->filterByAttributeAvId($attribute_av_id)
                ->filterByAttributeAttributeTypeId($attributeAttributeType->getId());
            if ($attributeType->getIsMultilingualAttributeAvValue()) {
                $attributeAvMetaQuery->filterByLocale($this->getLocale($lang_id));
            }
            $attributeAvMetas = $attributeAvMetaQuery->find();
            foreach ($attributeAvMetas as $attributeAvMeta) {
                $this->dispatch(
                    $eventName,
                    (new AttributeTypeAvMetaEvent($attributeAvMeta))
                );
            }
            $this->resetUpdateForm();
            return $this->generateSuccessRedirect($form);
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                Translator::getInstance()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form,
                $e
            );
            return $this->viewAttribute($attribute_id);
        }
    }

    /**
     * @param AttributeAttributeType $attributeAttributeType
     * @param int $attributeAvId
     * @param int $langId
     * @param string $value
     * @throws \Exception
     */
    protected function dispatchEvent(AttributeAttributeType $attributeAttributeType, $attributeAvId, $langId, $value, EventDispatcherInterface $eventDispatcher)
    {
        $eventName = AttributeTypeEvents::ATTRIBUTE_TYPE_AV_META_UPDATE;

        $attributeAvMeta = AttributeTypeAvMetaQuery::create()
            ->filterByAttributeAvId($attributeAvId)
            ->filterByAttributeAttributeTypeId($attributeAttributeType->getId())
            ->filterByLocale($this->getLocale($langId))
            ->findOne();

        // create if not exist
        if ($attributeAvMeta === null) {
            $eventName = AttributeTypeEvents::ATTRIBUTE_TYPE_AV_META_CREATE;

            $attributeAvMeta = (new AttributeTypeAvMeta())
                ->setAttributeAvId($attributeAvId)
                ->setAttributeAttributeTypeId($attributeAttributeType->getId())
                ->setLocale($this->getLocale($langId));
        }

        $attributeAvMeta->setValue($value);

        $eventDispatcher->dispatch(
            (new AttributeTypeAvMetaEvent($attributeAvMeta)),
            $eventName
        );
    }

    /**
     * @param int $attributeTypeId
     * @param int $attributeId
     * @return AttributeAttributeType
     * @throws \Exception
     */
    protected function getAttributeAttributeType($attributeTypeId, $attributeId)
    {
        if (!isset($this->attributeAttributeTypes[$attributeTypeId])) {
            $this->attributeAttributeTypes[$attributeTypeId] = AttributeAttributeTypeQuery::create()
                ->filterByAttributeTypeId($attributeTypeId)
                ->filterByAttributeId($attributeId)
                ->findOne();

            if ($this->attributeAttributeTypes[$attributeTypeId] === null) {
                throw new \Exception('AttributeAttributeType not found');
            }
        }

        return $this->attributeAttributeTypes[$attributeTypeId];
    }

    /**
     * @param int $langId
     * @return string
     * @throws \Exception
     */
    protected function getLocale($langId)
    {
        if (!isset($this->langs[$langId])) {
            $this->langs[$langId] = LangQuery::create()->findPk($langId);

            if ($this->langs[$langId] === null) {
                throw new \Exception('Lang not found');
            }
        }

        return $this->langs[$langId]->getLocale();
    }

    /**
     * @param UploadedFile $file
     * @return string
     */
    protected function uploadFile(UploadedFile $file)
    {
        if ($file->getError() == UPLOAD_ERR_INI_SIZE) {
            $message = Translator::getInstance()
                ->trans(
                    'File is too large, please retry with a file having a size less than %size%.',
                    array('%size%' => ini_get('upload_max_filesize')),
                    'core'
                );
            throw new ProcessFileException($message, 403);
        }
        $validMimeTypes = [
            'image/jpeg' => ["jpg", "jpeg"],
            'image/png' => ["png"],
            'image/gif' => ["gif"]
        ];
        $mimeType = $file->getMimeType();
        if (!isset($validMimeTypes[$mimeType])) {
            $message = Translator::getInstance()
                ->trans(
                    'Only files having the following mime type are allowed: %types%',
                    [ '%types%' => implode(', ', array_keys($validMimeTypes))]
                );
            throw new ProcessFileException($message, 415);
        }
        $regex = "#^(.+)\.(".implode("|", $validMimeTypes[$mimeType]).")$#i";
        $realFileName = $file->getClientOriginalName();
        if (!preg_match($regex, $realFileName)) {
            $message = Translator::getInstance()
                ->trans(
                    "There's a conflict between your file extension \"%ext\" and the mime type \"%mime\"",
                    [
                        '%mime' => $mimeType,
                        '%ext' => $file->getClientOriginalExtension()
                    ]
                );
            throw new ProcessFileException($message, 415);
        }
        $fileSystem = new Filesystem();
        $fileSystem->mkdir(THELIA_WEB_DIR. DS .AttributeType::ATTRIBUTE_TYPE_AV_IMAGE_FOLDER);
        $fileName = $this->generateUniqueFileName().'_'.$realFileName;
        $file->move(THELIA_WEB_DIR. DS .AttributeType::ATTRIBUTE_TYPE_AV_IMAGE_FOLDER, $fileName);
        return DS . AttributeType::ATTRIBUTE_TYPE_AV_IMAGE_FOLDER. DS .$fileName;
    }

    /**
     * @return string
     */
    protected function generateUniqueFileName()
    {
        // md5() reduces the similarity of the file names generated by
        // uniqid(), which is based on timestamps
        return substr(md5(uniqid()), 0, 10);
    }

    protected function resetUpdateForm(ParserContext $parserContext, Request $request) {
        $parserContext->remove(AttributeTypeAvMetaUpdateForm::class.':form');
        $theliaFormErrors = $request->getSession()->get('thelia.form-errors');
        unset($theliaFormErrors[AttributeTypeAvMetaUpdateForm::class.':form']);
        $request->getSession()->set('thelia.form-errors', $theliaFormErrors);
    }
}
