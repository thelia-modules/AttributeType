<?php
/*************************************************************************************/
/*      This file is part of the module AttributeType                                */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : gilles.bourgeat@gmail.com                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace AttributeType;

use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;
use Symfony\Component\Finder\Finder;
use Thelia\Core\Template\TemplateDefinition;
use Thelia\Module\BaseModule;
use Thelia\Install\Database;

/**
 * Class AttributeType
 * @package AttributeType
 * @author Gilles Bourgeat <gilles.bourgeat@gmail.com>
 */
class AttributeType extends BaseModule
{
    const MODULE_DOMAIN = "attributetype";

    const RESERVED_SLUG = 'id,attribute_id,id_translater,locale,title,chapo,description,postscriptum,position';

    const ATTRIBUTE_TYPE_AV_IMAGE_FOLDER = 'attribute_type_av_images';

    /** @var string */
    const UPDATE_PATH = __DIR__ . DS . 'Config' . DS . 'update';

    /**
     * @param ConnectionInterface $con
     */
    public function postActivation(ConnectionInterface $con = null): void
    {
        if (!self::getConfigValue('is_initialized', false)) {
            $database = new Database($con);
            $database->insertSql(null, [__DIR__ . "/Config/thelia.sql", __DIR__ . "/Config/insert.sql"]);
            self::setConfigValue('is_initialized', true);
        }
    }

    /**
     * @param $currentVersion
     * @param $newVersion
     * @param ConnectionInterface|null $con
     */
    public function update($currentVersion, $newVersion, ConnectionInterface $con = null): void
    {
        $finder = (new Finder())->files()->name('#.*?\.sql#')->sortByName()->in(self::UPDATE_PATH);
        if ($finder->count() === 0) {
            return;
        }
        $database = new Database($con);
        /** @var \Symfony\Component\Finder\SplFileInfo $updateSQLFile */
        foreach ($finder as $updateSQLFile) {
            if (version_compare($currentVersion, str_replace('.sql', '', $updateSQLFile->getFilename()), '<')) {
                $database->insertSql(null, [$updateSQLFile->getPathname()]);
            }
        }
    }

    /**
     * @return array
     */
    public function getHooks()
    {
        return array(
            array(
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "attribute-type.form-top",
                "title" => array(
                    "fr_FR" => "Module Attribute Type, form haut",
                    "en_US" => "Module Attribute Type, form top",
                ),
                "description" => array(
                    "fr_FR" => "En haut du formulaire de création et de mise à jour",
                    "en_US" => "Top of creation form and update",
                ),
                "active" => true
            ),
            array(
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "attribute-type.form-bottom",
                "title" => array(
                    "fr_FR" => "Module Attribute Type, form bas",
                    "en_US" => "Module Attribute Type, form bottom",
                ),
                "description" => array(
                    "fr_FR" => "En bas du formulaire de création et de mise à jour",
                    "en_US" => "Top of creation form and update",
                ),
                "active" => true
            ),
            array(
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "attribute-type.configuration-top",
                "title" => array(
                    "fr_FR" => "Module Attribute Type, configuration haut",
                    "en_US" => "Module Attribute Type, configuration top",
                ),
                "description" => array(
                    "fr_FR" => "En haut du la page de configuration du module",
                    "en_US" => "At the top of the module's configuration page",
                ),
                "active" => true
            ),
            array(
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "attribute-type.configuration-bottom",
                "title" => array(
                    "fr_FR" => "Module Attribute Type, configuration bas",
                    "en_US" => "Module Attribute Type, configuration bottom",
                ),
                "description" => array(
                    "fr_FR" => "En bas du la page de configuration du module",
                    "en_US" => "At the bottom of the module's configuration page",
                ),
                "active" => true
            ),
            array(
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "attribute-type.list-action",
                "title" => array(
                    "fr_FR" => "Module Attribute Type, liste des actiona",
                    "en_US" => "Module Attribute Type, list action",
                ),
                "description" => array(
                    "fr_FR" => "Action de la liste des types de déclinaisons",
                    "en_US" => "Action from the list of attributes types",
                ),
                "active" => true
            ),
            array(
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "attribute-type.list-action",
                "title" => array(
                    "fr_FR" => "Module Attribute Type, list action",
                    "en_US" => "Module Attribute Type, list action",
                ),
                "description" => array(
                    "fr_FR" => "Action de la liste des dates",
                    "en_US" => "Action from the list of dates",
                ),
                "active" => true
            ),
            array(
                "type" => TemplateDefinition::BACK_OFFICE,
                "code" => "attribute-type.configuration-js",
                "title" => array(
                    "fr_FR" => "Module Attribute Type, configuration js",
                    "en_US" => "Module Attribute Type, configuration js",
                ),
                "description" => array(
                    "fr_FR" => "JS la page de configuration du module",
                    "en_US" => "JS of the module's configuration page",
                ),
                "active" => true
            )
        );
    }

    public static function configureServices(ServicesConfigurator $servicesConfigurator): void
    {
        $servicesConfigurator->load(self::getModuleCode().'\\', __DIR__)
            ->exclude([THELIA_MODULE_DIR . ucfirst(self::getModuleCode()). "/I18n/*"])
            ->autowire(true)
            ->autoconfigure(true);
    }
}
