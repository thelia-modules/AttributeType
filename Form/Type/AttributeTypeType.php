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
use Symfony\Component\Form\FormBuilderInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class AttributeTypeType
 * @package AttributeType\Form\Type
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class AttributeTypeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $formOptions = ['required' => true];
        
        // Fix for symfony/form 2.8.49
        if (isset($options['allow_file_upload'])) {
            $formOptions['allow_file_upload'] = true;
        }
        
        $builder->add(
            'attribute_type',
            'collection',
            array(
                'type' => 'text',
                'allow_add'    => true,
                'allow_delete' => true,
                'options' => $formOptions
            )
        );
    }

    public function getName()
    {
        return 'attribute_type';
    }
}
