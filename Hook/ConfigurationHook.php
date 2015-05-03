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

namespace AttributeType\Hook;

use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Class ConfigurationHook
 * @package AttributeType\Hook
 * @author Gilles Bourgeat <gbourgeat@openstudio.fr>
 */
class ConfigurationHook extends BaseHook
{
    /**
     * @param HookRenderEvent $event
     */
    public function onConfigurationCatalogTop(HookRenderEvent $event)
    {
        $event->add($this->render(
            'attribute-type/hook/configuration-catalog.html'
        ));
    }
}
