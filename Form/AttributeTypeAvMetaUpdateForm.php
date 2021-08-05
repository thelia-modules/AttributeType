<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType\Form;

use AttributeType\AttributeType;
use AttributeType\Form\Type\I18nType;
use AttributeType\Model\AttributeTypeQuery;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints\Callback;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class AttributeTypeAvMetaUpdateForm
 * @package AttributeType\Form
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class AttributeTypeAvMetaUpdateForm extends AttributeTypeForm
{
    /**
     * @return string the name of you form. This name must be unique
     */
    public static function getName()
    {
        return 'attribute_type_av_meta_update';
    }

    /**
     *
     * in this function you add all the fields you need for your Form.
     * Form this you have to call add method on $this->formBuilder attribute :
     *
     */
    protected function buildForm()
    {
        parent::buildForm();

            $this->formBuilder->add(
                'attribute_av',
                CollectionType::class,
                array(
                    'entry_type' => I18nType::class,
                    'constraints' => array(
                        new Callback(
                            array($this, "checkImageSize"),
                    )),
                    'allow_add'    => true,
                    'allow_delete' => true,
                    'label_attr' => array(
                        'for' => 'description'
                    ),
                    'label' => Translator::getInstance()->trans('Description', array(), AttributeType::MODULE_DOMAIN),
                    'entry_options' => array(
                        'required' => true
                    )
                )
            );

            $this->formBuilder->add(
                'attribute_id',
                IntegerType::class,
                array(
                    'constraints' => array(
                        new NotBlank()
                    )
                )
            );
    }

    /**
     * @param $value
     * @param ExecutionContextInterface $context
     */
    public function checkImageSize($value, ExecutionContextInterface $context)
    {
        foreach ($value as $attributeAvId => $attributeAv) {
            foreach ($attributeAv['lang'] as $langId => $lang) {
                foreach ($lang['attribute_type'] as $attributeTypeId => $attributeValue) {
                    if (!$attributeValue instanceof UploadedFile) {
                        continue;
                    }
                    $attributeType = AttributeTypeQuery::create()
                        ->findOneById($attributeTypeId);
                    $size = getimagesize($attributeValue);
                    list($width, $height) = $size;
                    if (null !== $attributeType->getImageMaxWidth() && $width > $attributeType->getImageMaxWidth()) {
                        $context->addViolation(Translator::getInstance()->trans(Translator::getInstance()
                            ->trans(
                                "Your image is too large (maximum %width px)",
                                [
                                    '%width' => $attributeType->getImageMaxWidth(),
                                ]
                            ))
                        );
                    }
                    if (null !== $attributeType->getImageMaxHeight() && $height > $attributeType->getImageMaxHeight()) {
                        $context->addViolation(Translator::getInstance()->trans(Translator::getInstance()
                            ->trans(
                                "Your image is too tall (maximum %height px)",
                                [
                                    '%height' => $attributeType->getImageMaxHeight(),
                                ]
                            ))
                        );
                    }
                    if (null !== $attributeType->getImageRatio() && ($width/$height) !== $attributeType->getImageRatio()) {
                        $context->addViolation(Translator::getInstance()->trans(Translator::getInstance()
                            ->trans(
                                "Bad image ratio (%ratio required)",
                                [
                                    '%ratio' => $attributeType->getImageRatio(),
                                ]
                            ))
                        );
                    }
                }
            }
        }
    }
}
