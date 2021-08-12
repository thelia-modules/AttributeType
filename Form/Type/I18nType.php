<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType\Form\Type;

use AttributeType\AttributeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\FormBuilderInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class I18nType
 * @package AttributeType\Form\Type
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class I18nType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'lang',
            CollectionType::class,
            array(
                'entry_type' => AttributeTypeType::class,
                'allow_add'    => true,
                'allow_delete' => true,
                'entry_options' => array(
                    'required' => true
                )
            )
        );
    }

    public static function getName()
    {
        return 'lang';
    }
}
