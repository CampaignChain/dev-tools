<?php
/*
 * This file is part of the CampaignChain package.
 *
 * (c) Sandro Groganz <sandro@campaignchain.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CampaignChain\GeneratorBundle\Generator;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\DependencyInjection\Container;
use Sensio\Bundle\GeneratorBundle\Generator\BundleGenerator;

class ModuleGenerator extends BundleGenerator
{

    private $filesystem;
    
    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
        return parent::__construct($filesystem);
    }

    public function generate($namespace, $bundleName, $dir, $format, $structure)
    {
        return parent::generate($namespace, $bundleName, $dir, $format, $structure);
    }
    
    public function generateConf($namespace, $bundleName, $dir, $moduleType, $moduleName, $moduleNameSuffix,  $moduleDescription, $packageLicense, $packageWebsite, $vendorName, $authorName, $authorEmail, $packageName, $operationOwnsLocation, $metricsForOperation, $channelsForActivity, $hooksForActivity, $routing)
    {
    
        $dir .= '/'.strtr($namespace, '\\', '/');
        $namespace = str_replace("\\", "\\\\", $namespace);
        $this->setSkeletonDirs(__DIR__ . '/../Resources/skeleton');
        $parameters = array(
            'namespace' => $namespace,
            'bundle_name'  => $bundleName,
            'module_type' => $moduleType, 
            'module_name' => $moduleName, 
            'module_name_suffix' => $moduleNameSuffix, 
            'module_description' => $moduleDescription, 
            'package_license' => $packageLicense, 
            'package_website' => $packageWebsite, 
            'vendor_name' => $vendorName, 
            'author_name' => $authorName, 
            'author_email' => $authorEmail,
            'package_name' => $packageName, 
            'package_description' => $moduleDescription,
            'owns_location' => $operationOwnsLocation,
            'channels_for_activity' => explode(',', $channelsForActivity), 
            'hooks_for_activity' => !empty($hooksForActivity) ? explode(',', $hooksForActivity) : null, 
            'metrics_for_operation' => !empty($metricsForOperation) ? explode(',', $metricsForOperation) : null, 
            'gen_routing' => $routing
        );
        
        if ($routing == 'yes') {
            $parameters['route_prefix'] = str_replace(array('/', '-'), '_', $packageName);
        }
        
        $derivedClassName = implode('', array_map('ucwords', explode('-', $moduleName . '-' . $moduleNameSuffix)));
        $parameters['class_name'] = $derivedClassName;
        
        $this->renderFile('config/campaignchain.yml.twig', $dir.'/campaignchain.yml', $parameters);
        $this->renderFile('config/composer.json.twig', $dir.'/composer.json', $parameters);
        $this->renderFile('config/config.yml.twig', $dir.'/Resources/config/config.yml', $parameters);
        if (strtolower($moduleType) == 'operation') {
            $this->renderFile('job/Report.php.twig', $dir.'/Job/' . $derivedClassName . 'Report.php', $parameters);        
            $this->renderFile('form/OperationType.php.twig', $dir.'/Form/Type/' . $derivedClassName . 'OperationType.php', $parameters);        
            $this->renderFile('entity/Entity.php.twig', $dir.'/Entity/' . $derivedClassName . '.php', $parameters);
            $this->renderFile('views/fields.html.twig', $dir.'/Resources/views/Form/fields.html.twig', $parameters);        
            $this->renderFile('views/read.html.twig', $dir.'/Resources/views/read' . ((!empty($moduleNameSuffix)) ? '_' . strtolower(str_replace('-', '_', $moduleNameSuffix)) : '') . '.html.twig', $parameters);        
            $this->renderFile('public/css/base.css.twig', $dir.'/Resources/public/css/' . strtolower($moduleName) . ((!empty($moduleNameSuffix)) ? '_' . strtolower(str_replace('-', '_', $moduleNameSuffix)) : '') . '.css', $parameters);        
        }
        if (strtolower($moduleType) == 'activity') {
            $this->renderFile('controller/ActivityController.php.twig', $dir.'/Controller/' . $derivedClassName . 'Controller.php', $parameters);        
        }
        if (strtolower($moduleType) == 'channel') {
            $this->renderFile('controller/ChannelController.php.twig', $dir.'/Controller/' . $derivedClassName . 'Controller.php', $parameters);
        }          
        if ($routing == 'yes') {
            // overwrite the default routing.yml file created by the Symfony generator
            $this->renderFile('config/routing.yml.twig', $dir.'/Resources/config/routing.yml', $parameters);
        } else {
            // remove the default routing.yml file created by the Symfony generator
            $this->filesystem->remove($dir.'/Resources/config/routing.yml');
        }
    }    
}

