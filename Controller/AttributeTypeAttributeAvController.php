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
use AttributeType\Model\AttributeAttributeType;
use AttributeType\Model\AttributeAttributeTypeQuery;
use AttributeType\Model\AttributeTypeAvMeta;
use AttributeType\Model\AttributeTypeAvMetaQuery;
use AttributeType\Model\AttributeTypeQuery;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Thelia\Core\Security\AccessManager;
use Thelia\Core\Security\Resource\AdminResources;
use Thelia\Files\Exception\ProcessFileException;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;

/**
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
     */
    public function updateMetaAction($attribute_id)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::ATTRIBUTE), null, AccessManager::UPDATE)) {
            return $response;
        }

        $form = $this->createForm("attribute_type_av_meta.update");

        try {
            $formUpdate = $this->validateForm($form);

            $attributeAvs = $formUpdate->get('attribute_av')->getData();

            foreach ($attributeAvs as $attributeAvId => $attributeAv) {
                foreach ($attributeAv['lang'] as $langId => $lang) {
                    foreach ($lang['attribute_type'] as $attributeTypeId => $value) {
                        $values = [];
                        $values[$langId] = $value;
                        $attributeType = AttributeTypeQuery::create()
                            ->findOneById($attributeTypeId);

                        if ($attributeType->getInputType() === "image") {
                            if (null === $value) {
                                continue;
                            }

                            $uploadedFileName = $this->uploadFile($value);
                            $values[$langId] = $uploadedFileName;

                            if (!$attributeType->getIsMultilingualAttributeAvValue()) {
                                $activeLangs = LangQuery::create()
                                    ->filterByActive(1)
                                    ->find();
                                /** @var Lang $lang */
                                foreach ($activeLangs as $lang) {
                                    $values[$lang->getId()] = $uploadedFileName;
                                }
                            }
                        }

                         foreach ($values as $langId => $langValue) {
                             $this->dispatchEvent(
                                 $this->getAttributeAttributeType($attributeTypeId, $attribute_id),
                                 $attributeAvId,
                                 $langId,
                                 $langValue
                             );
                         }
                    }
                }
            }

            $this->resetUpdateForm();
            return $this->generateSuccessRedirect($form);
        } catch (\Exception $e) {
            $this->setupFormErrorContext(
                $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)),
                $e->getMessage(),
                $form,
                $e
            );

            return $this->viewAttribute($attribute_id);
        }
    }

    public function deleteMetaAction($attribute_id, $attribute_type_id, $attribute_av_id, $lang_id)
    {
        if (null !== $response = $this->checkAuth(array(AdminResources::ATTRIBUTE), null, AccessManager::DELETE)) {
            return $response;
        }
        $form = $this->createForm("attribute_type.delete");
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
                $this->getTranslator()->trans("%obj modification", array('%obj' => $this->objectName)),
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
    protected function dispatchEvent(AttributeAttributeType $attributeAttributeType, $attributeAvId, $langId, $value)
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

        $this->dispatch(
            $eventName,
            (new AttributeTypeAvMetaEvent($attributeAvMeta))
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
            $message = $this->getTranslator()
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
            $message = $this->getTranslator()
                ->trans(
                    'Only files having the following mime type are allowed: %types%',
                    [ '%types%' => implode(', ', array_keys($validMimeTypes))]
                );
            throw new ProcessFileException($message, 415);
        }
        $regex = "#^(.+)\.(".implode("|", $validMimeTypes[$mimeType]).")$#i";
        $realFileName = $file->getClientOriginalName();
        if (!preg_match($regex, $realFileName)) {
            $message = $this->getTranslator()
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

    protected function resetUpdateForm() {
        $this->getParserContext()->remove(AttributeTypeAvMetaUpdateForm::class.':form');
        $theliaFormErrors = $this->getRequest()->getSession()->get('thelia.form-errors');
        unset($theliaFormErrors[AttributeTypeAvMetaUpdateForm::class.':form']);
        $this->getRequest()->getSession()->set('thelia.form-errors', $theliaFormErrors);
    }
}
