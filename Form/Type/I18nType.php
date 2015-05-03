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

namespace AttributeType\Form\Type;

use AttributeType\AttributeType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Thelia\Core\Translation\Translator;

/**
 * Class I18nType
 * @package AttributeType\Form\Type
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class I18nType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->add(
            'lang',
            'collection',
            array(
                'type' => new AttributeTypeType(),
                'allow_add'    => true,
                'allow_delete' => true,
                'options' => array(
                    'required' => true
                )
            )
        );
    }

    public function getName()
    {
        return 'lang';
    }
}
