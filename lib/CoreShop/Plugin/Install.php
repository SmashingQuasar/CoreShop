<?php
/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

namespace CoreShop\Plugin;

use CoreShop\Plugin;
use CoreShop\Config;

use Pimcore\File;
use Pimcore\Model\Document;
use Pimcore\Model\Object;
use Pimcore\Model\Object\Folder;
use Pimcore\Model\User;
use Pimcore\Model\Staticroute;

use Pimcore\Model\Tool\Setup;
use Pimcore\Tool;

class Install
{
    /**
     * @var User
     */
    protected $_user;

    /**
     * executes some install SQL
     *
     * @param $fileName
     */
    public function executeSQL($fileName) {
        $file = PIMCORE_PLUGINS_PATH . "/CoreShop/install/sql/$fileName.sql";;

        $setup = new Setup();
        $setup->insertDump($file);
    }

    /**
     * creates a mew Class if it doesn't exists
     *
     * @param $className
     * @return mixed|Object\ClassDefinition
     */
    public function createClass($className)
    {
        $class = Object\ClassDefinition::getByName($className);

        if (!$class)
        {
            $jsonFile = PIMCORE_PLUGINS_PATH . "/CoreShop/install/class-$className.json";
            $json = file_get_contents($jsonFile);

            $result = Plugin::getEventManager()->trigger("install.class.getClass.$className", $this, array("className" => $className, "json" => $json), function($v) {
                return ($v instanceof Object\ClassDefinition);
            });

            if ($result->stopped()) {
                return $result->last();
            }

            $class = Object\ClassDefinition::create();
            $class->setName($className);
            $class->setUserOwner($this->_getUser()->getId());

            $result = Plugin::getEventManager()->trigger('install.class.preCreate', $this, array("className" => $className, "json" => $json), function($v) {
                return !preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', preg_replace('/"(\\.|[^"\\\\])*"/', '', $v));
            });

            if ($result->stopped()) {
                $resultJson = $result->last();

                if($resultJson)
                {
                    $json = $resultJson;
                }
            }

            Object\ClassDefinition\Service::importClassDefinitionFromJson($class, $json, true);

            return $class;
        }

        return $class;
    }

    /**
     * Removes a class definition
     *
     * @param $name
     */
    public function removeClass($name)
    {
        $class = Object\ClassDefinition::getByName($name);
        if ($class) {
            $class->delete();
        }
    }

    /**
     * Creates a new ObjectBrick
     *
     * @param $name
     * @param null $jsonPath
     * @return mixed|Object\Objectbrick\Definition
     */
    public function createObjectBrick($name, $jsonPath = null)
    {
        try {
            $objectBrick = Object\Objectbrick\Definition::getByKey($name);
        } 
        catch (\Exception $e) {
            if($jsonPath == null)
                $jsonPath = PIMCORE_PLUGINS_PATH . "/CoreShop/install/fieldcollection-$name.json";
            
            $objectBrick = new Object\Objectbrick\Definition();
            $objectBrick->setKey($name);
            
            $json = file_get_contents($jsonPath);
            
            $result = Plugin::getEventManager()->trigger('install.objectbrick.preCreate', $this, array("objectbrickName" => $name, "json" => $json), function($v) {
                return !preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', preg_replace('/"(\\.|[^"\\\\])*"/', '', $v));
            });
    
            if ($result->stopped()) {
                $resultJson = $result->last();
                
                if($resultJson)
                {
                    $json = $resultJson;
                }
            }
            
            Object\ClassDefinition\Service::importObjectBrickFromJson($objectBrick, $json, true);
        }
        
        return $objectBrick;
    }

    /**
     * Removes an ObjectBrick
     *
     * @param $name
     * @return bool
     */
    public function removeObjectBrick($name)
    {
        try
        {
            $brick = Object\Objectbrick\Definition::getByKey($name);

            if ($brick) {
                $brick->delete();
            }
        } 
        catch(\Exception $e)
        {
            return false;
        }
        
        return true;
    }

    /**
     * Creates a FieldCollection
     *
     * @param $name
     * @param null $jsonPath
     * @return mixed|null|Object\Fieldcollection\Definition
     */
    public function createFieldCollection($name, $jsonPath = null)
    {
        try {
            $fieldCollection = Object\Fieldcollection\Definition::getByKey($name);
        } 
        catch (\Exception $e) {
            if($jsonPath == null)
                $jsonPath = PIMCORE_PLUGINS_PATH . "/CoreShop/install/fieldcollection-$name.json";
                
            $fieldCollection = new Object\Fieldcollection\Definition();
            $fieldCollection->setKey($name);
            
            $json = file_get_contents($jsonPath);

            $result = Plugin::getEventManager()->trigger('install.fieldcollection.preCreate', $this, array("fieldcollectionName" => $name, "json" => $json), function($v) {
                return !preg_match('/[^,:{}\\[\\]0-9.\\-+Eaeflnr-u \\n\\r\\t]/', preg_replace('/"(\\.|[^"\\\\])*"/', '', $v));
            });
    
            if ($result->stopped()) {
                $resultJson = $result->last();
                
                if($resultJson)
                {
                    $json = $resultJson;
                }
            }
            
            Object\ClassDefinition\Service::importFieldCollectionFromJson($fieldCollection, $json, true);
        }
        
        return $fieldCollection;
    }

    /**
     * Removes a FieldCollection
     *
     * @param $name
     * @return bool
     */
    public function removeFieldcollection($name)
    {
        try
        {
            $fc = Object\Fieldcollection\Definition::getByKey($name);

            if ($fc) {
                $fc->delete();
            }
        } 
        catch(\Exception $e)
        {
            return false;
        }
        
        return true;
    }

    /**
     * Create needed CoreShop Folders
     *
     * @return Object\AbstractObject|Folder
     */
    public function createFolders()
    {
        $root = Folder::getByPath("/coreshop");
        $products = Folder::getByPath("/coreshop/products");
        $categories = Folder::getByPath("/coreshop/categories");
        $cart = Folder::getByPath("/coreshop/carts");

        if(!$root instanceof Folder)
        {
            $root = Folder::create(array(
                'o_parentId' => 1,
                'o_creationDate' => time(),
                'o_userOwner' => $this->_getUser()->getId(),
                'o_userModification' => $this->_getUser()->getId(),
                'o_key' => 'coreshop',
                'o_published' => true,
            ));
        }
        
        if(!$products instanceof Folder)
        {
            Folder::create(array(
                'o_parentId' => $root->getId(),
                'o_creationDate' => time(),
                'o_userOwner' => $this->_getUser()->getId(),
                'o_userModification' => $this->_getUser()->getId(),
                'o_key' => 'products',
                'o_published' => true,
            ));
        }
        
        if(!$categories instanceof Folder)
        {
            Folder::create(array(
                'o_parentId' => $root->getId(),
                'o_creationDate' => time(),
                'o_userOwner' => $this->_getUser()->getId(),
                'o_userModification' => $this->_getUser()->getId(),
                'o_key' => 'categories',
                'o_published' => true,
            ));
        }
        
        if(!$cart instanceof Folder)
        {
            Folder::create(array(
                'o_parentId' => $root->getId(),
                'o_creationDate' => time(),
                'o_userOwner' => $this->_getUser()->getId(),
                'o_userModification' => $this->_getUser()->getId(),
                'o_key' => 'carts',
                'o_published' => true,
            ));
        }

        return $root;
    }

    /**
     * Remove CoreShop Folders
     */
    public function removeFolders()
    {
        $blogFolder = Folder::getByPath('/coreshop');
        if ($blogFolder) {
            $blogFolder->delete();
        }
    }

    /**
     * Creates CustomView for CoreShop if it doesn't exist
     *
     * @param $rootFolder
     * @param array $classIds
     * @return bool
     * @throws \Zend_Config_Exception
     */
    public function createCustomView($rootFolder, array $classIds)
    {
        $customViews = Tool::getCustomViewConfig();
        
        if (!$customViews) {
            $customViews = array();
            $customViewId = 1;
        } else {
            $last = end($customViews);
            $customViewId = $last['id'] + 1;
        }

        $alreadyDefined = FALSE;

        // does custom view already exists?
        if( !empty( $customViews ) ) {

            foreach($customViews as $view) {

                if( $view['name'] == 'CoreShop') {
                    $alreadyDefined = TRUE;
                    break;
                }
            }
        }

        if( $alreadyDefined === TRUE )
            return false;

        $customViews[] = array(
            'name' => 'CoreShop',
            'condition' => '',
            'icon' => '/pimcore/static/img/icon/cart.png',
            'id' => $customViewId,
            'rootfolder' => $rootFolder->getFullPath(),
            'showroot' => false,
            'classes' => implode(',', $classIds),
        );
        $writer = new \Zend_Config_Writer_Xml(array(
            'config' => new \Zend_Config(array('views'=> array('view' => $customViews))),
            'filename' => PIMCORE_CONFIGURATION_DIRECTORY . '/customviews.xml'
        ));
        $writer->write();

        return true;
    }

    /**
     * installs some data based from an XML File
     *
     * @param $xml
     */
    public function installObjectData($xml) {
        $file = PIMCORE_PLUGINS_PATH . "/CoreShop/install/data/objects/$xml.xml";

        if(file_exists($file)) {
            $config = new \Zend_Config_Xml($file);
            $config = $config->toArray();
            $coreShopNamespace = "\\CoreShop\\Model\\";

            foreach($config['objects'] as $class=>$amounts) {
                $class = $coreShopNamespace . $class;

                foreach($amounts as $values) {
                    if(Tool::classExists($class)) {
                        $object = new $class();

                        foreach ($values as $key => $value) {
                            //Localized Value
                            $setter = "set" . ucfirst($key);

                            if (is_array($value)) {
                                foreach ($value as $lang => $val) {
                                    $object->$setter($val, $lang);
                                }
                            } else {
                                $object->$setter($value);
                            }
                        }

                        $object->save();
                    }
                }
            }
        }
    }

    /**
     * Creates some Documents with Data based from XML file
     *
     * @param $xml
     * @throws \Exception
     */
    public function installDocuments($xml) {
        $dataPath = PIMCORE_PLUGINS_PATH . "/CoreShop/install/data/documents";
        $file = $dataPath . "/$xml.xml";

        if(file_exists($file))
        {
            $config = new \Zend_Config_Xml($file);
            $config = $config->toArray();

            if(array_key_exists("documents", $config))
            {
                $validLanguages = explode(",", \Pimcore\Config::getSystemConfig()->general->validLanguages);

                foreach($validLanguages as $language)
                {
                    $languageDocument = Document::getByPath("/" . $language);

                    if(!$languageDocument instanceof Document) {
                        $languageDocument = new Document\Page();
                        $languageDocument->setParent(Document::getById(1));
                        $languageDocument->setKey($language);
                        $languageDocument->save();
                    }

                    foreach($config["documents"] as $value)
                    {
                        foreach($value as $doc)
                        {
                            $document = Document::getByPath("/" . $language . "/" . $doc['path'] . "/" . $doc['key']);

                            if(!$document)
                            {
                                $class = "Pimcore\\Model\\Document\\" . ucfirst($doc['type']);

                                if(Tool::classExists($class))
                                {
                                    $document = new $class();
                                    $document->setParent(Document::getByPath("/" . $language . "/" . $doc['path']));
                                    $document->setKey($doc['key']);
                                    $document->setProperty("language", $language, 'text', true);

                                    if($document instanceof Document\PageSnippet) {
                                        if(array_key_exists("action", $doc))
                                            $document->setAction($doc['action']);

                                        if(array_key_exists("controller", $doc))
                                            $document->setController($doc['controller']);

                                        if(array_key_exists("module", $doc))
                                            $document->setModule($doc['module']);
                                    }

                                    $document->save();

                                    if(array_key_exists("content", $doc)) {
                                        foreach($doc['content'] as $fieldLanguage=>$fields) {
                                            if($fieldLanguage !== $language)
                                                continue;

                                            foreach($fields['field'] as $field) {
                                                $key = $field['key'];
                                                $type = $field['type'];
                                                $content = null;

                                                if(array_key_exists("file", $field)) {
                                                    $file = $dataPath . "/" . $field['file'];

                                                    if(file_exists($file))
                                                        $content = file_get_contents($file);
                                                }

                                                if(array_key_exists("value", $field)) {
                                                    $content = $field['value'];
                                                }

                                                if($content) {
                                                    if($type === "objectProperty") {
                                                        $document->setValue($key, $content);
                                                    }
                                                    else {
                                                        $document->setRawElement($key, $type, $content);
                                                    }
                                                }
                                            }
                                        }

                                        $document->save();
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Removes CoreShop CustomView
     *
     * @throws \Zend_Config_Exception
     */
    public function removeCustomView()
    {
        $customViews = Tool::getCustomViewConfig();
        if ($customViews) {
            foreach ($customViews as $key => $view) {
                if ($view['name'] == 'CoreShop') {
                    unset($customViews[$key]);
                    break;
                }
            }
            $writer = new \Zend_Config_Writer_Xml(array(
                'config' => new \Zend_Config(array('views'=> array('view' => $customViews))),
                'filename' => PIMCORE_CONFIGURATION_DIRECTORY . '/customviews.xml'
            ));
            $writer->write();
        }
    }

    /**
     * set isInstalled true in CoreShop Config
     *
     * @throws \Zend_Config_Exception
     */
    public function setConfigInstalled() {
        $oldConfig = Config::getConfig();
        $oldValues = $oldConfig->toArray();

        $oldValues['isInstalled'] = true;

        $config = new \Zend_Config($oldValues, true);
        $writer = new \Zend_Config_Writer_Xml(array(
            "config" => $config,
            "filename" => CORESHOP_CONFIGURATION
        ));
        $writer->write();
    }

    /**
     * Creates CoreShop Static Routes
     */
    public function createStaticRoutes()
    {
        $conf = new \Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/CoreShop/install/staticroutes.xml');
        
        foreach ($conf->routes->route as $def) {
            $route = Staticroute::create();
            $route->setName($def->name);
            $route->setPattern($def->pattern);
            $route->setReverse($def->reverse);
            $route->setModule($def->module);
            $route->setController($def->controller);
            $route->setAction($def->action);
            $route->setVariables($def->variables);
            $route->setPriority($def->priority);
            $route->save();
        }
    }

    /**
     * Installs the Theme
     *
     * @param string $template
     * @param bool $installDemoData
     * @throws \CoreShop\Exception\ThemeNotFoundException
     */
    public function installTheme($template = "default", $installDemoData = true)
    {
        Plugin::enableTheme($template);

        if($installDemoData) {
            Plugin::getTheme()->installDemoData();
        }
    }

    /**
     * Install Demo Theme Data
     */
    public function installThemeDemo()
    {
        Plugin::getTheme()->installDemoData();
    }

    /**
     * Remove CoreShop Static Routes
     */
    public function removeStaticRoutes()
    {
        $conf = new \Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/CoreShop/install/staticroutes.xml');
        
        foreach ($conf->routes->route as $def) {
            $route = Staticroute::getByName($def->name);
            if ($route) {
                $route->delete();
            }
        }
    }

    /**
     * Create CoreShop Config
     */
    public function createConfig()
    {
        if(!is_file(CORESHOP_CONFIGURATION))
        {
            copy(PIMCORE_PLUGINS_PATH . '/CoreShop/install/coreshop-config.xml', CORESHOP_CONFIGURATION);
        }
    }

    /**
     * Remove CoreShop Config
     */
    public function removeConfig()
    {
        if(is_file(CORESHOP_CONFIGURATION))
        {
            unlink(CORESHOP_CONFIGURATION);
        }
    }

    /**
     * Creates CoreShop Image Thumbnails
     */
    public function createImageThumbnails()
    {
        recurse_copy(PIMCORE_PLUGINS_PATH . "/CoreShop/install/thumbnails/image", PIMCORE_WEBSITE_PATH . "/var/config/imagepipelines", true);
    }

    /**
     * Removes CoreShop Image Thumbnails
     */
    public function removeImageThumbnails()
    {
        foreach (glob(PIMCORE_WEBSITE_PATH . "/var/config/imagepipelines/coreshop_*.xml") as $filename) 
        {
            unlink($filename);
        }
    }

/*
    public function createDocTypes()
    {
        $conf = new Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/Blog/install/doctypes.xml');
        foreach ($conf->doctypes->doctype as $def) {
            $docType = Document_DocType::create();
            $docType->setName($def->name);
            $docType->setType($def->type);
            $docType->setModule($def->module);
            $docType->setController($def->controller);
            $docType->setAction($def->action);
            $docType->save();
        }
    }
    public function removeDocTypes()
    {
        $conf = new Zend_Config_Xml(PIMCORE_PLUGINS_PATH . '/Blog/install/doctypes.xml');
        $names = array();
        foreach ($conf->doctypes->doctype as $def) {
            $names[] = $def->name;
        }
        $list = new Document_DocType_List();
        $list->load();
        foreach ($list->docTypes as $docType) {
            if (in_array($docType->name, $names)) {
                $docType->delete();
            }
        }
    }
*/
    /**
     * @return User
     */
    protected function _getUser()
    {
        if (!$this->_user) {
            $this->_user = \Zend_Registry::get('pimcore_admin_user');
        }
        return $this->_user;
    }
}